<?php

declare(strict_types=1);

namespace App\PipBoy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Locates an optional replacement image for the STAT figure. Events licensed
 * to use official artwork drop it into public/images/stat-figure.{svg,png,webp};
 * without it the built-in original line-art figure is shown.
 */
final class StatFigure
{
    public const array CANDIDATES = ['images/stat-figure.svg', 'images/stat-figure.png', 'images/stat-figure.webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDirectory,
    ) {
    }

    /** Public path of the replacement image, relative to the web root, or null for the built-in figure. */
    public function imagePath(): ?string
    {
        foreach (self::CANDIDATES as $candidate) {
            if (is_file($this->publicDirectory.'/'.$candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
