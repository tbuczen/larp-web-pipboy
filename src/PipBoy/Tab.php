<?php

declare(strict_types=1);

namespace App\PipBoy;

/** A PipBoy main tab with its sub-tabs; a tab is only built when it has at least one screen. */
final readonly class Tab
{
    /** @param non-empty-list<Screen> $screens */
    public function __construct(
        public string $key,
        public string $label,
        public array $screens,
    ) {
    }

    public function screen(?string $key): ?Screen
    {
        if ($key === null) {
            return $this->screens[0];
        }

        foreach ($this->screens as $screen) {
            if ($screen->key === $key) {
                return $screen;
            }
        }

        return null;
    }
}
