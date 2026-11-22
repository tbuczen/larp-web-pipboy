<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A one-time password reset link issued by an organizer and passed to the
 * player outside the app. Only a hash of the token is stored.
 */
#[ORM\Entity]
#[ORM\Table(name: 'password_reset_token')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $issuedBy;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    public function __construct(User $user, string $tokenHash, DateTimeImmutable $expiresAt, ?User $issuedBy)
    {
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->issuedBy = $issuedBy;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isUsableAt(DateTimeImmutable $time): bool
    {
        return $this->usedAt === null && $time < $this->expiresAt;
    }

    public function markUsed(): void
    {
        $this->usedAt = new DateTimeImmutable();
    }
}
