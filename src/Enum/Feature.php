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
}
