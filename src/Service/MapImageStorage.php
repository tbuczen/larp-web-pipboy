<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Larp;
use App\Entity\LarpMap;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Keeps LARP map images outside the public web root, under random names. They
 * are served only through the access-checked map image route.
 */
final class MapImageStorage
{
    /** Detected content type => stored extension. */
    public const array ACCEPTED_MIME_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/storage/maps')]
        private readonly string $directory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /** Stores the upload and creates the LARP's map, or swaps the image of its existing map. */
    public function store(Larp $larp, ?LarpMap $map, UploadedFile $upload): LarpMap
    {
        // Trust the detected content type, not the browser's claim or the file name.
        $mimeType = (string) $upload->getMimeType();
        $extension = self::ACCEPTED_MIME_TYPES[$mimeType] ?? throw new InvalidArgumentException('Unsupported map image type.');
        $size = @getimagesize($upload->getPathname());
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new InvalidArgumentException('The map image could not be read.');
        }

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $this->filesystem->mkdir($this->directory, 0o750);
        $upload->move($this->directory, $filename);

        if ($map === null) {
            return new LarpMap($larp, $filename, $mimeType, $size[0], $size[1]);
        }

        $previous = $map->getImageFilename();
        $map->replaceImage($filename, $mimeType, $size[0], $size[1]);
        $this->filesystem->remove($this->pathOf($previous));

        return $map;
    }

    public function pathFor(LarpMap $map): ?string
    {
        $path = $this->pathOf($map->getImageFilename());

        return is_file($path) ? $path : null;
    }

    private function pathOf(string $filename): string
    {
        // Stored names are generated here; refuse anything that could leave the directory.
        if (preg_match('/^[a-f0-9]{32}\.[a-z]{3,4}$/D', $filename) !== 1) {
            throw new InvalidArgumentException('Invalid map image file name.');
        }

        return $this->directory.'/'.$filename;
    }
}
