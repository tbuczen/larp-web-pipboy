<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Feature;
use App\Enum\RegistrationStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'player_user_larp_unique', columns: ['user_id', 'larp_id'])]
#[ORM\UniqueConstraint(name: 'player_pipboy_larp_unique', columns: ['pipboy_number', 'larp_id'])]
#[UniqueEntity(fields: ['user', 'larp'])]
#[UniqueEntity(fields: ['pipboyNumber', 'larp'])]
class Player
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Larp $larp = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $pipboyNumber = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: RegistrationStatus::class)]
    private RegistrationStatus $status = RegistrationStatus::PENDING;

    /** @var Collection<int, PlayerFeatureAccess> */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: PlayerFeatureAccess::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $featureAccesses;

    public function __construct()
    {
        $this->featureAccesses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getPipboyNumber(): string
    {
        return $this->pipboyNumber;
    }

    public function setPipboyNumber(string $pipboyNumber): self
    {
        $this->pipboyNumber = trim($pipboyNumber);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    /** @return Collection<int, PlayerFeatureAccess> */
    public function getFeatureAccesses(): Collection
    {
        return $this->featureAccesses;
    }

    public function canUse(Feature $feature): bool
    {
        if ($this->status !== RegistrationStatus::APPROVED) {
            return false;
        }

        return $this->hasFeatureGrant($feature);
    }

    private function hasFeatureGrant(Feature $feature): bool
    {
        return $this->featureAccesses->exists(
            static fn (int $key, PlayerFeatureAccess $access): bool => $access->getFeature() === $feature,
        );
    }

    public function grantFeature(Feature $feature): self
    {
        if (!$this->hasFeatureGrant($feature)) {
            (new PlayerFeatureAccess())->setFeature($feature)->setPlayer($this);
        }

        return $this;
    }

    public function revokeFeature(Feature $feature): self
    {
        foreach ($this->featureAccesses as $access) {
            if ($access->getFeature() === $feature) {
                $this->featureAccesses->removeElement($access);
            }
        }

        return $this;
    }

    public function addFeatureAccess(PlayerFeatureAccess $access): self
    {
        if (!$this->featureAccesses->contains($access)) {
            $this->featureAccesses->add($access);
        }

        if ($access->getPlayer() !== $this) {
            $access->setPlayer($this);
        }

        return $this;
    }

    public function removeFeatureAccess(PlayerFeatureAccess $access): self
    {
        $this->featureAccesses->removeElement($access);

        return $this;
    }

    public function getStatus(): RegistrationStatus
    {
        return $this->status;
    }

    public function setStatus(RegistrationStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function approve(): void
    {
        $this->status = RegistrationStatus::APPROVED;
    }

    public function reject(): void
    {
        $this->status = RegistrationStatus::REJECTED;
    }

    public function __toString(): string
    {
        return $this->pipboyNumber.' — '.$this->name;
    }
}
