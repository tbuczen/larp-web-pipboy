<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * The game map of one LARP: an uploaded image aligned to real coordinates by
 * three corners (top-left, top-right, bottom-left). Three corners describe
 * any placement of the image: position, scale, rotation and slight skew.
 * Players' devices show their GPS position on it; positions are never stored.
 */
#[ORM\Entity]
#[ORM\Table(name: 'larp_map')]
class LarpMap
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Larp $larp;

    /** Random file name in the protected map storage; never a public path. */
    #[ORM\Column(length: 64)]
    private string $imageFilename;

    #[ORM\Column(length: 100)]
    private string $imageMimeType;

    #[ORM\Column]
    private int $imageWidth;

    #[ORM\Column]
    private int $imageHeight;

    /** @var array{0: float, 1: float}|null [latitude, longitude] */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $topLeft = null;

    /** @var array{0: float, 1: float}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $topRight = null;

    /** @var array{0: float, 1: float}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $bottomLeft = null;

    /**
     * Light maps (drawn, scanned, street maps) are inverted so their lines glow
     * on a dark screen; dark maps such as aerial photos are only tinted.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $invertColors = true;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(Larp $larp, string $filename, string $mimeType, int $width, int $height)
    {
        $this->larp = $larp;
        $this->replaceImage($filename, $mimeType, $width, $height);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLarp(): Larp
    {
        return $this->larp;
    }

    public function getImageFilename(): string
    {
        return $this->imageFilename;
    }

    public function getImageMimeType(): string
    {
        return $this->imageMimeType;
    }

    public function getImageWidth(): int
    {
        return $this->imageWidth;
    }

    public function getImageHeight(): int
    {
        return $this->imageHeight;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Keeps the alignment, so a corrected version of the same map stays in place. */
    public function replaceImage(string $filename, string $mimeType, int $width, int $height): void
    {
        if ($width < 1 || $height < 1) {
            throw new DomainException('The map image has no size.');
        }
        $this->imageFilename = $filename;
        $this->imageMimeType = $mimeType;
        $this->imageWidth = $width;
        $this->imageHeight = $height;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @param array{0: float, 1: float} $topLeft     [latitude, longitude]
     * @param array{0: float, 1: float} $topRight
     * @param array{0: float, 1: float} $bottomLeft
     */
    public function align(array $topLeft, array $topRight, array $bottomLeft): void
    {
        $corners = array_map(self::coordinate(...), [$topLeft, $topRight, $bottomLeft]);

        // The corners must span an area: collinear or repeated points cannot place an image.
        [[$lat0, $lng0], [$lat1, $lng1], [$lat2, $lng2]] = $corners;
        if (abs(($lat1 - $lat0) * ($lng2 - $lng0) - ($lng1 - $lng0) * ($lat2 - $lat0)) < 1e-12) {
            throw new DomainException('The map corners must not lie on one line.');
        }

        [$this->topLeft, $this->topRight, $this->bottomLeft] = $corners;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isInvertColors(): bool
    {
        return $this->invertColors;
    }

    public function setInvertColors(bool $invertColors): void
    {
        $this->invertColors = $invertColors;
    }

    public function isAligned(): bool
    {
        return $this->topLeft !== null && $this->topRight !== null && $this->bottomLeft !== null;
    }

    /** @return array{topLeft: array{0: float, 1: float}, topRight: array{0: float, 1: float}, bottomLeft: array{0: float, 1: float}}|null */
    public function corners(): ?array
    {
        return $this->isAligned() ? ['topLeft' => $this->topLeft, 'topRight' => $this->topRight, 'bottomLeft' => $this->bottomLeft] : null;
    }

    /**
     * @param array<mixed> $point
     * @return array{0: float, 1: float}
     */
    private static function coordinate(array $point): array
    {
        $lat = $point[0] ?? null;
        $lng = $point[1] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            throw new DomainException('Map corners need a latitude between -90 and 90 and a longitude between -180 and 180.');
        }

        return [round((float) $lat, 7), round((float) $lng, 7)];
    }
}
