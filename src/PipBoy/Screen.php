<?php

declare(strict_types=1);

namespace App\PipBoy;

/** One sub-tab of a PipBoy main tab, e.g. DATA › HOLODISKS. */
final readonly class Screen
{
    public function __construct(
        public string $key,
        public string $label,
    ) {
    }
}
