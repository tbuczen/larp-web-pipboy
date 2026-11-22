<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * The last position a player shared for the motion detector. Only one row
 * per player is kept (no history); it is deleted when the player stops
 * sharing and is ignored once stale. Other players never receive the
 * coordinates, only rounded offsets from their own position.
 */
#[ORM\Entity]
#[ORM\Table(name: 'player_position')]
#[ORM\Index(name: 'player_position_recorded_at', columns: ['recorded_at'])]
class PlayerPosition
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $player;

    #[ORM\Column]
    private float $latitude;

    #[ORM\Column]
    private float $longitude;

    /** Reported accuracy radius in metres. */
    #[ORM\Column]
    private int $accuracy;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $recordedAt;

    public function __construct(Player $player, float $latitude, float $longitude, int $accuracy)
    {
        $this->player = $player;
        $this->update($latitude, $longitude, $accuracy);
    }

    public function update(float $latitude, float $longitude, int $accuracy): void
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->accuracy = $accuracy;
        $this->recordedAt = new DateTimeImmutable();
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getAccuracy(): int
    {
        return $this->accuracy;
    }

    public function getRecordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
