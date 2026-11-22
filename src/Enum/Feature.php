<?php

declare(strict_types=1);

namespace App\Enum;

enum Feature: string
{
    case NOTES = 'notes';
    case CHAT = 'chat';
    case MAP = 'map';
    case HOLODISKS = 'holodisks';
    case CONDITIONS = 'conditions';
    case MOTION = 'motion';

    public function label(): string
    {
        return match ($this) {
            self::NOTES => 'Notes',
            self::CHAT => 'Messages',
            self::MAP => 'Map',
            self::HOLODISKS => 'Holodisks',
            self::CONDITIONS => 'Conditions',
            self::MOTION => 'Motion detector',
        };
    }
}
