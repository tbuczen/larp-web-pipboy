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
    public const HOLODISK_READER_EMAIL = 'holodisk-reader@pipboy.test';
    public const HOLODISK_TITLE = 'Codeception holodisk';
    public const SCAN_HOLODISK_TITLE = 'Codeception scannable holodisk';
    public const SCAN_HOLODISK_CODE = 'SCANTESTDISK1';
    public const NOTE_TITLE = 'Codeception note';
    public const STAYS_PENDING_EMAIL = 'stays-pending@pipboy.test';
    /** Approved players with chat in the fixture LARP; Dave has chat in the other LARP. */
    public const CHAT_PLAYERS = [
        'alice' => ['email' => 'chat-alice@pipboy.test', 'number' => 'CHAT-ALICE', 'name' => 'Alice of the Wastes'],
        'bob' => ['email' => 'chat-bob@pipboy.test', 'number' => 'CHAT-BOB', 'name' => 'Bob the Courier'],
        'carol' => ['email' => 'chat-carol@pipboy.test', 'number' => 'CHAT-CAROL', 'name' => 'Carol Scrapper'],
        'dave' => ['email' => 'chat-dave@pipboy.test', 'number' => 'CHAT-DAVE', 'name' => 'Dave Elsewhere'],
    ];
    /** Alice's second membership: the same account in the other LARP. */
    public const ALICE_ELSEWHERE_NUMBER = 'ALICE-ABROAD';
    public const ALICE_ELSEWHERE_NAME = 'Alice abroad';
    public const CONDITION_NAME = 'Codeception sprained ankle';
    public const ORGANIZER_INVITED_EMAIL = 'organizer-invited@pipboy.test';
    public const RESET_EMAIL = 'forgetful@pipboy.test';
    public const PASSWORD = 'LocalTestPassword-42';
    public const LARP_NAME = 'Codeception fixtures event';
    public const OTHER_LARP_NAME = 'Codeception unrelated event';
    public const PENDING_CHARACTER_NAME = 'Pending test character';
    public const CREATED_LARP_NAME = 'Codeception expedition';
    public const EDITED_LARP_NAME = 'Codeception expedition revised';
    public const CSRF_LARP_NAME = 'Codeception rejected CSRF event';

    public static int $larpId;
    public static int $playerId;
    public static int $holodiskId;
    public static int $scanHolodiskId;
    public static int $readerPlayerId;
    public static int $pendingPlayerId;
    public static int $staysPendingPlayerId;
    /** @var array<string, int> */
    public static array $chatPlayerIds = [];
    public static int $otherLarpId;
    public static int $resetPlayerId;
    public static int $aliceElsewherePlayerId;
    /** @var array<string, string> */
    public static array $invitationTokens = [];
}
