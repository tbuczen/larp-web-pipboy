<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\HolodiskMediaType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[UniqueEntity(fields: ['code'], message: 'This code is already used by another holodisk.')]
class Holodisk
{
    /** Generated codes avoid characters that are easy to misread (0/O, 1/I/L). */
    private const string CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const int GENERATED_CODE_LENGTH = 12;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Larp $larp = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $content = '';

    /** Random file name in the protected holodisk storage; never a public path. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $mediaFilename = null;

    #[ORM\Column(length: 10, nullable: true, enumType: HolodiskMediaType::class)]
    private ?HolodiskMediaType $mediaType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mediaMimeType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mediaOriginalName = null;

    /**
     * Secret printed in the holodisk's QR code (and readable as text). Scanning
     * it unlocks the holodisk once for an eligible player. Stored normalized:
     * upper case, without spaces or hyphens.
     */
    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z0-9]{6,64}$/', message: 'Use 6 to 64 letters or digits (spaces and hyphens are ignored).')]
    private string $code;

    /** Set from the admin form to replace the code, invalidating printed copies. */
    private bool $regenerateCode = false;

    /** Upload received from the admin form; stored by HolodiskMediaStorage, never persisted itself. */
    #[Assert\File(
        maxSize: '15M',
        mimeTypes: HolodiskMediaType::ACCEPTED_MIME_TYPES,
        mimeTypesMessage: 'Upload a JPEG, PNG, WebP or GIF image, or an MP3 file.',
    )]
    private ?File $mediaFile = null;

    /** Set from the admin form to delete the current media. */
    private bool $removeMedia = false;

    public function __construct()
    {
        $this->code = self::generateCode();
    }

    public static function generateCode(): string
    {
        $code = '';
        for ($i = 0; $i < self::GENERATED_CODE_LENGTH; ++$i) {
            $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $code;
    }

    /** The same normalization applies to codes entered by organizers and to scanned text. */
    public static function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/u', '', $code));
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = self::normalizeCode((string) $code);

        return $this;
    }

    public function isRegenerateCode(): bool
    {
        return $this->regenerateCode;
    }

    public function setRegenerateCode(bool $regenerateCode): self
    {
        $this->regenerateCode = $regenerateCode;

        return $this;
    }

    /** Applies a pending regeneration from the admin form. */
    public function applyCodeRegeneration(): void
    {
        if ($this->regenerateCode) {
            $this->code = self::generateCode();
            $this->regenerateCode = false;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function hasMedia(): bool
    {
        return $this->mediaFilename !== null;
    }

    public function getMediaFilename(): ?string
    {
        return $this->mediaFilename;
    }

    public function getMediaType(): ?HolodiskMediaType
    {
        return $this->mediaType;
    }

    public function getMediaMimeType(): ?string
    {
        return $this->mediaMimeType;
    }

    public function getMediaOriginalName(): ?string
    {
        return $this->mediaOriginalName;
    }

    public function attachMedia(string $filename, HolodiskMediaType $type, string $mimeType, string $originalName): self
    {
        $this->mediaFilename = $filename;
        $this->mediaType = $type;
        $this->mediaMimeType = $mimeType;
        $this->mediaOriginalName = mb_substr($originalName, 0, 255);

        return $this;
    }

    public function detachMedia(): self
    {
        $this->mediaFilename = $this->mediaType = $this->mediaMimeType = $this->mediaOriginalName = null;

        return $this;
    }

    public function getMediaFile(): ?File
    {
        return $this->mediaFile;
    }

    public function setMediaFile(?File $mediaFile): self
    {
        $this->mediaFile = $mediaFile;

        return $this;
    }

    public function isRemoveMedia(): bool
    {
        return $this->removeMedia;
    }

    public function setRemoveMedia(bool $removeMedia): self
    {
        $this->removeMedia = $removeMedia;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
