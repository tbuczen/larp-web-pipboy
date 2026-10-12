<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use DomainException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'player_holodisk_unique', columns: ['player_id', 'holodisk_id'])]
#[UniqueEntity(fields: ['player', 'holodisk'])]
class HolodiskAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Player $player = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Holodisk $holodisk = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $grantedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    public function __construct()
    {
        $this->grantedAt = new DateTimeImmutable();
    }

    public static function grant(Player $player, Holodisk $holodisk): self
    {
        $access = (new self())->setPlayer($player)->setHolodisk($holodisk);

        if (!$access->belongsToSameLarp()) {
            throw new DomainException('A player can only unlock holodisks from their own LARP.');
        }

        return $access;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): self
    {
        $this->player = $player;

        return $this;
    }

    public function getHolodisk(): ?Holodisk
    {
        return $this->holodisk;
    }

    public function setHolodisk(Holodisk $holodisk): self
    {
        $this->holodisk = $holodisk;

        return $this;
    }

    public function getGrantedAt(): DateTimeImmutable
    {
        return $this->grantedAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt ??= new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function setRevoked(bool $revoked): self
    {
        $revoked ? $this->revoke() : $this->restore();

        return $this;
    }

    public function restore(): void
    {
        $this->revokedAt = null;
    }

    public function isActive(): bool
    {
        return $this->revokedAt === null && $this->belongsToSameLarp();
    }

    #[Assert\Callback]
    public function validateLarp(ExecutionContextInterface $context): void
    {
        if ($this->player !== null && $this->holodisk !== null && !$this->belongsToSameLarp()) {
            $context->buildViolation('The holodisk must belong to the player’s LARP.')
                ->atPath('holodisk')
                ->addViolation();
        }
    }

    private function belongsToSameLarp(): bool
    {
        $playerLarp = $this->player?->getLarp();
        $holodiskLarp = $this->holodisk?->getLarp();

        if ($playerLarp === null || $holodiskLarp === null) {
            return false;
        }

        return $playerLarp === $holodiskLarp
            || ($playerLarp->getId() !== null && $playerLarp->getId() === $holodiskLarp->getId());
    }
}
