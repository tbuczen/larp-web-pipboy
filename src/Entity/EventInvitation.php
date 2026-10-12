<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class EventInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Larp $larp = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\Regex('/^[a-f0-9]{64}$/D')]
    private string $tokenHash = '';

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $acceptedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+7 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLarp(): ?Larp
    {
        return $this->larp;
    }

    public function setLarp(Larp $larp): self
    {
        $this->larp = $larp;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt ??= new DateTimeImmutable();
    }

    public function getAcceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function markAcceptedAt(DateTimeImmutable $acceptedAt): void
    {
        $this->acceptedAt = $acceptedAt;
    }

    public function isAvailableAt(DateTimeImmutable $time): bool
    {
        return $this->acceptedAt === null && $this->revokedAt === null && $this->expiresAt > $time;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
