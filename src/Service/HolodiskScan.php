<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Holodisk;
use App\Entity\Player;

/** Outcome of a successful scan: the player it unlocked for, and whether it was new. */
final readonly class HolodiskScan
{
    public function __construct(
        public Player $player,
        public Holodisk $holodisk,
        public bool $newlyUnlocked,
    ) {
    }
}
