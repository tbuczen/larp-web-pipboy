<?php

declare(strict_types=1);

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Appends each public file's modification time to its URL (?v=...). The host
 * caches static files for a week, so without this a deploy could leave
 * browsers running old CSS or JavaScript against new pages.
 */
final class FileModifiedVersionStrategy implements VersionStrategyInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDirectory,
    ) {
    }

    public function getVersion(string $path): string
    {
        $file = $this->publicDirectory.'/'.ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $modified = is_file($file) ? filemtime($file) : false;

        return $modified === false ? '' : (string) $modified;
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        return $version === '' ? $path : $path.(str_contains($path, '?') ? '&' : '?').'v='.$version;
    }
}
