<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Holodisk;
use App\Enum\HolodiskMediaType;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Keeps holodisk media outside the public web root, under random names. Files
 * are served only through the access-checked holodisk media route.
 */
final class HolodiskMediaStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/storage/holodisks')]
        private readonly string $directory,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /** Applies a pending upload or removal from the admin form to the holodisk and the storage. */
    public function sync(Holodisk $holodisk): void
    {
        $upload = $holodisk->getMediaFile();

        if ($upload instanceof UploadedFile) {
            $this->replace($holodisk, $upload);
        } elseif ($holodisk->isRemoveMedia()) {
            $this->remove($holodisk);
        }

        $holodisk->setMediaFile(null)->setRemoveMedia(false);
    }

    public function remove(Holodisk $holodisk): void
    {
        if ($holodisk->getMediaFilename() !== null) {
            $this->filesystem->remove($this->pathOf($holodisk->getMediaFilename()));
        }

        $holodisk->detachMedia();
    }

    public function pathFor(Holodisk $holodisk): ?string
    {
        $filename = $holodisk->getMediaFilename();

        return $filename !== null && is_file($this->pathOf($filename)) ? $this->pathOf($filename) : null;
    }

    private function replace(Holodisk $holodisk, UploadedFile $upload): void
    {
        // Trust the detected content type, not the browser's claim or the file name.
        $mimeType = (string) $upload->getMimeType();
        $type = HolodiskMediaType::fromMimeType($mimeType);
        $extension = array_search($mimeType, HolodiskMediaType::ACCEPTED_MIME_TYPES, true);

        if ($type === null || $extension === false) {
            throw new InvalidArgumentException('Unsupported holodisk media type.');
        }

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $this->filesystem->mkdir($this->directory, 0o750);
        $upload->move($this->directory, $filename);

        $this->remove($holodisk);
        $holodisk->attachMedia($filename, $type, $mimeType, $upload->getClientOriginalName());
    }

    private function pathOf(string $filename): string
    {
        // Stored names are generated here; refuse anything that could leave the directory.
        if (preg_match('/^[a-f0-9]{32}\.[a-z0-9]{3,4}$/D', $filename) !== 1) {
            throw new InvalidArgumentException('Invalid holodisk media file name.');
        }

        return $this->directory.'/'.$filename;
    }
}
