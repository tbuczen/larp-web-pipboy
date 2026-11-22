<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Holodisk;
use App\Entity\HolodiskAccess;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\Feature;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

/**
 * Unlocks a holodisk for the scanning account's player in the holodisk's LARP.
 * A code unlocks once per player; a grant revoked by organizers stays revoked.
 * Unknown codes, other events' codes and players without approval or the
 * holodisk feature all get the same answer, so codes cannot be probed.
 */
final class HolodiskScanner
{
    public const string NO_DATA = 'No readable holodisk data in that code.';
    public const string REVOKED = 'This holodisk can no longer be read on your PipBoy.';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function unlock(User $user, string $scanned): HolodiskScan
    {
        $code = Holodisk::normalizeCode(self::codeFrom($scanned));
        $holodisk = $code === '' ? null : $this->entityManager->getRepository(Holodisk::class)->findOneBy(['code' => $code]);
        $player = $holodisk === null ? null : $this->entityManager->getRepository(Player::class)->findOneBy(['user' => $user, 'larp' => $holodisk->getLarp()]);

        if (!$holodisk instanceof Holodisk || !$player instanceof Player || !$player->canUse(Feature::HOLODISKS)) {
            throw new DomainException(self::NO_DATA);
        }

        $access = $this->entityManager->getRepository(HolodiskAccess::class)->findOneBy(['player' => $player, 'holodisk' => $holodisk]);
        if ($access instanceof HolodiskAccess) {
            return $access->isActive() ? new HolodiskScan($player, $holodisk, false) : throw new DomainException(self::REVOKED);
        }

        $this->entityManager->persist(HolodiskAccess::grant($player, $holodisk));
        $this->entityManager->flush();

        return new HolodiskScan($player, $holodisk, true);
    }

    /** A scanned QR holds the scan link; typed or imported codes are the code itself. */
    public static function codeFrom(string $scanned): string
    {
        $scanned = trim($scanned);
        if (preg_match('~/scan/([A-Za-z0-9-]+)/?(?:[?#].*)?$~D', $scanned, $match) === 1) {
            return $match[1];
        }

        return mb_substr($scanned, 0, 100);
    }
}
