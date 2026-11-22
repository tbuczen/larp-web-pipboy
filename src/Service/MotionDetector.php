<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Player;
use App\Entity\PlayerPosition;
use App\Enum\Feature;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

/**
 * Motion detector (proof of concept). Players opt in to sharing their
 * position while the PipBoy is open. A sharing player sees other sharing
 * players of the same LARP as anonymous blips: only offsets in metres from
 * their own position, rounded, within the chosen range. Nobody who does not
 * share sees anything, and no identities or coordinates are ever returned.
 */
final class MotionDetector
{
    /** Positions older than this are not shown. */
    public const int FRESH_SECONDS = 60;
    /** Positions older than this are deleted. */
    public const int KEEP_SECONDS = 7200;
    public const array RANGES = [50, 100, 250];
    private const int ROUND_TO_METRES = 5;
    private const int MAX_ACCURACY = 500;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function share(Player $player, mixed $latitude, mixed $longitude, mixed $accuracy): void
    {
        $this->assertCanUse($player);
        if (!is_numeric($latitude) || !is_numeric($longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            throw new DomainException('Invalid position.');
        }
        $metres = is_numeric($accuracy) ? (int) round((float) $accuracy) : self::MAX_ACCURACY;
        $metres = max(0, min(self::MAX_ACCURACY, $metres));

        $position = $this->positionOf($player);
        if ($position === null) {
            $this->entityManager->persist(new PlayerPosition($player, (float) $latitude, (float) $longitude, $metres));
        } else {
            $position->update((float) $latitude, (float) $longitude, $metres);
        }
        $this->entityManager->flush();
        $this->deleteStale();
    }

    public function stop(Player $player): void
    {
        $position = $this->positionOf($player);
        if ($position !== null) {
            $this->entityManager->remove($position);
            $this->entityManager->flush();
        }
    }

    public function isSharing(Player $player): bool
    {
        $position = $this->positionOf($player);

        return $position !== null && $position->getRecordedAt() > new DateTimeImmutable('-'.self::FRESH_SECONDS.' seconds');
    }

    /**
     * Other sharing players within range, as offsets from the viewer:
     * x = metres east, y = metres north. Empty unless the viewer shares too.
     *
     * @return list<array{x: int, y: int}>
     */
    public function blips(Player $viewer, int $range): array
    {
        $this->assertCanUse($viewer);
        $range = in_array($range, self::RANGES, true) ? $range : self::RANGES[1];
        $own = $this->positionOf($viewer);
        $fresh = new DateTimeImmutable('-'.self::FRESH_SECONDS.' seconds');
        if ($own === null || $own->getRecordedAt() <= $fresh) {
            return [];
        }

        /** @var list<PlayerPosition> $others */
        $others = $this->entityManager->createQueryBuilder()
            ->select('position', 'player')->from(PlayerPosition::class, 'position')
            ->innerJoin('position.player', 'player')
            ->andWhere('player.larp = :larp AND player != :viewer AND position.recordedAt > :fresh')
            ->setParameter('larp', $viewer->getLarp())->setParameter('viewer', $viewer)->setParameter('fresh', $fresh)
            ->getQuery()->getResult();

        $blips = [];
        foreach ($others as $other) {
            if (!$other->getPlayer()->canUse(Feature::MOTION)) {
                continue;
            }
            [$east, $north] = self::offset($own, $other);
            if (hypot($east, $north) <= $range) {
                $blips[] = ['x' => self::rounded($east), 'y' => self::rounded($north)];
            }
        }
        // Shuffle so the order does not identify anyone between polls.
        shuffle($blips);

        return $blips;
    }

    /**
     * Equirectangular approximation: accurate to well under a metre at event distances.
     *
     * @return array{0: float, 1: float} metres east, metres north
     */
    public static function offset(PlayerPosition $from, PlayerPosition $to): array
    {
        $metresPerDegree = 111_320.0;
        $north = ($to->getLatitude() - $from->getLatitude()) * $metresPerDegree;
        $east = ($to->getLongitude() - $from->getLongitude()) * $metresPerDegree * cos(deg2rad(($from->getLatitude() + $to->getLatitude()) / 2));

        return [$east, $north];
    }

    private static function rounded(float $metres): int
    {
        return (int) (round($metres / self::ROUND_TO_METRES) * self::ROUND_TO_METRES);
    }

    private function positionOf(Player $player): ?PlayerPosition
    {
        return $this->entityManager->getRepository(PlayerPosition::class)->findOneBy(['player' => $player]);
    }

    private function deleteStale(): void
    {
        $this->entityManager->createQueryBuilder()->delete(PlayerPosition::class, 'position')
            ->where('position.recordedAt < :old')->setParameter('old', new DateTimeImmutable('-'.self::KEEP_SECONDS.' seconds'))
            ->getQuery()->execute();
    }

    private function assertCanUse(Player $player): void
    {
        if (!$player->canUse(Feature::MOTION)) {
            throw new DomainException('The motion detector is not enabled for your PipBoy.');
        }
    }
}
