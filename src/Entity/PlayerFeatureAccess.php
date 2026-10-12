<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Feature;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'player_feature_unique', columns: ['player_id', 'feature'])]
#[UniqueEntity(fields: ['player', 'feature'])]
class PlayerFeatureAccess
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'featureAccesses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Player $player = null;

    #[ORM\Column(length: 30, enumType: Feature::class)]
    private Feature $feature = Feature::NOTES;

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
        if ($this->player !== $player) {
            $this->player?->removeFeatureAccess($this);
            $this->player = $player;
            $player->addFeatureAccess($this);
        }

        return $this;
    }

    public function getFeature(): Feature
    {
        return $this->feature;
    }

    public function setFeature(Feature $feature): self
    {
        $this->feature = $feature;

        return $this;
    }

    public function __toString(): string
    {
        return ($this->player ? (string) $this->player : 'Player').' / '.$this->feature->value;
    }
}
