<?php

declare(strict_types=1);

namespace App\Enum;

/** Media a holodisk can carry besides its text. */
enum HolodiskMediaType: string
{
    case IMAGE = 'image';
    case AUDIO = 'audio';

    /** MIME types accepted for upload, keyed by the extension used in storage. */
    public const array ACCEPTED_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'mp3' => 'audio/mpeg',
    ];

    public static function fromMimeType(string $mimeType): ?self
    {
        return match (true) {
            !in_array($mimeType, self::ACCEPTED_MIME_TYPES, true) => null,
            str_starts_with($mimeType, 'image/') => self::IMAGE,
            default => self::AUDIO,
        };
    }
}
