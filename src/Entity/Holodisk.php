<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\HolodiskMediaType;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Holodisk
{
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

    /** Upload received from the admin form; stored by HolodiskMediaStorage, never persisted itself. */
    #[Assert\File(
        maxSize: '15M',
        mimeTypes: HolodiskMediaType::ACCEPTED_MIME_TYPES,
        mimeTypesMessage: 'Upload a JPEG, PNG, WebP or GIF image, or an MP3 file.',
    )]
    private ?File $mediaFile = null;

    /** Set from the admin form to delete the current media. */
    private bool $removeMedia = false;

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
