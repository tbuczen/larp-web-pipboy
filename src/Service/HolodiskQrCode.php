<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Holodisk;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * QR codes for printing: each holds the absolute scan link, so any phone
 * camera opens the PipBoy, and the PipBoy scanner reads the code from it.
 */
final class HolodiskQrCode
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function scanUrl(Holodisk $holodisk): string
    {
        return $this->urlGenerator->generate('app_holodisk_scan_link', ['code' => $holodisk->getCode()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /** SVG data URI; vector output prints sharply at any size. */
    public function dataUri(Holodisk $holodisk): string
    {
        return (new Builder(
            writer: new SvgWriter(),
            data: $this->scanUrl($holodisk),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 12,
        ))->build()->getDataUri();
    }

    /** Groups of four characters are easier to read aloud and type. */
    public static function displayCode(string $code): string
    {
        return implode('-', str_split($code, 4));
    }
}
