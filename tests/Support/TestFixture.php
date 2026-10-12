<?php

declare(strict_types=1);

namespace App\Tests\Support;

final class TestFixture
{
    public const ADMIN_EMAIL = 'admin@pipboy.test';
    public const PLAYER_EMAIL = 'player@pipboy.test';
    public const ORGANIZER_EMAIL = 'organizer@pipboy.test';
    public const PENDING_PLAYER_EMAIL = 'pending@pipboy.test';
    public const REGISTERED_EMAIL = 'new-registration@pipboy.test';
    public const INVALID_PASSWORD_EMAIL = 'invalid-password@pipboy.test';
    public const INVALID_CSRF_EMAIL = 'invalid-csrf@pipboy.test';
    public const INVITED_EMAIL = 'invited@pipboy.test';
    public const GET_ONLY_EMAIL = 'get-only@pipboy.test';
    public const ADMIN_INVITED_EMAIL = 'admin-invited@pipboy.test';
    public const UNRELATED_INVITATION_EMAIL = 'unrelated-invitation@pipboy.test';
    public const PASSWORD = 'LocalTestPassword-42';
    public const LARP_NAME = 'Codeception fixtures event';
    public const OTHER_LARP_NAME = 'Codeception unrelated event';
    public const PENDING_CHARACTER_NAME = 'Pending test character';
    public const CREATED_LARP_NAME = 'Codeception expedition';
    public const EDITED_LARP_NAME = 'Codeception expedition revised';
    public const CSRF_LARP_NAME = 'Codeception rejected CSRF event';

    public static int $larpId;
    public static int $otherLarpId;
    /** @var array<string, string> */
    public static array $invitationTokens = [];
}
