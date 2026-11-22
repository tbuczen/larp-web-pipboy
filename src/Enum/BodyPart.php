<?php

declare(strict_types=1);

namespace App\Enum;

/** Body regions shown on the STAT figure; a condition may affect one of them. */
enum BodyPart: string
{
    case HEAD = 'head';
    case TORSO = 'torso';
    case LEFT_ARM = 'left_arm';
    case RIGHT_ARM = 'right_arm';
    case LEFT_LEG = 'left_leg';
    case RIGHT_LEG = 'right_leg';

    public function label(): string
    {
        return match ($this) {
            self::HEAD => 'Head',
            self::TORSO => 'Torso',
            self::LEFT_ARM => 'Left arm',
            self::RIGHT_ARM => 'Right arm',
            self::LEFT_LEG => 'Left leg',
            self::RIGHT_LEG => 'Right leg',
        };
    }
}
