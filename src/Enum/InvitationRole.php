<?php

declare(strict_types=1);

namespace App\Enum;

/** What accepting an invitation makes the invited account within its LARP. */
enum InvitationRole: string
{
    case PLAYER = 'player';
    case ORGANIZER = 'organizer';
    case SUPER_ADMIN = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::PLAYER => 'Player',
            self::ORGANIZER => 'Organizer',
            self::SUPER_ADMIN => 'Superadministrator',
        };
    }

    /** The security role granted on acceptance; players get a pending player instead. */
    public function securityRole(): ?string
    {
        return match ($this) {
            self::PLAYER => null,
            self::ORGANIZER => 'ROLE_ORGANIZER',
            self::SUPER_ADMIN => 'ROLE_SUPER_ADMIN',
        };
    }

    /** Only superadministrators may invite organizers or superadministrators. */
    public function requiresSuperAdminToIssue(): bool
    {
        return $this !== self::PLAYER;
    }
}
