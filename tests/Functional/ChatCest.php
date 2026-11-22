<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class ChatCest
{
    public function playersChatAfterTheReceiverAcceptsARequest(FunctionalTester $I): void
    {
        // Alice requests a chat with Bob's PipBoy number.
        $conversation = $this->request($I, 'alice', 'CHAT-BOB', 'Hello Bob, over.');
        $I->see('Waiting for PB CHAT-BOB to accept your request.');
        $I->dontSeeElement('[data-chat-form]');

        // Bob sees only Alice's number and the opening message, then accepts.
        $this->signIn($I, 'bob');
        $I->amOnPage($this->messages('bob'));
        $I->see('PB CHAT-ALICE', '.pip-list');
        $I->see('Request', '.pip-list');
        $I->see('1', '.pip-tab .pip-badge');
        $I->amOnPage($this->messages('bob', $conversation));
        $I->see('Hello Bob, over.', '.pip-chat-thread');
        $I->see('PB CHAT-ALICE wants to start a direct chat with you.');
        $I->click('Accept');
        $I->submitForm('[data-chat-form]', ['message' => 'Reading you, Alice.']);
        $I->see('Reading you, Alice.', '.pip-chat-msg--mine');

        // Alice polls and receives Bob's reply; names stay hidden until both agree.
        $this->signIn($I, 'alice');
        $I->amOnPage($this->messages('alice', $conversation).'/poll?after=0');
        $poll = json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR);
        $I->assertSame('active', $poll['status']);
        $I->assertSame(['Hello Bob, over.', 'Reading you, Alice.'], array_column($poll['messages'], 'body'));
        $I->assertSame([true, false], array_column($poll['messages'], 'mine'));
        $I->assertSame('PB CHAT-BOB', $poll['label']);

        $I->amOnPage($this->messages('alice', $conversation));
        $I->click('Share my character name');
        $I->see('PB CHAT-BOB', 'h2');
        $I->dontSee('Bob the Courier');
        $this->signIn($I, 'bob');
        $I->amOnPage($this->messages('bob', $conversation));
        $I->click('Share my character name');
        $I->see('PB CHAT-ALICE · Alice of the Wastes', 'h2');
        // Real (organizer-only) names never appear.
        $I->dontSee('Test Account');

        // Closing ends the chat for both.
        $I->click('Close chat');
        $I->see('This chat is closed.');
        $this->signIn($I, 'alice');
        $I->amOnPage($this->messages('alice', $conversation));
        $I->see('This chat is closed.');
        $I->dontSeeElement('[data-chat-form]');
        $I->see('Closed', '.pip-list');
    }

    public function declinedRequestsLookPendingAndCannotBeRepeated(FunctionalTester $I): void
    {
        $conversation = $this->request($I, 'carol', 'CHAT-BOB', 'Trade some caps?');

        $this->signIn($I, 'bob');
        $I->amOnPage($this->messages('bob', $conversation));
        $I->click('Decline');
        $I->dontSee('PB CHAT-CAROL', '.pip-list');
        $I->amOnPage($this->messages('bob', $conversation));
        $I->seeResponseCodeIs(404);

        $this->signIn($I, 'carol');
        $I->amOnPage($this->messages('carol', $conversation));
        $I->see('Waiting for PB CHAT-BOB to accept your request.');
        $I->amOnPage($this->messages('carol'));
        $I->submitForm('.pip-chat-new', ['pipboy' => 'CHAT-BOB', 'message' => 'Please answer.']);
        $I->see('Your request to this PipBoy is waiting for an answer.', '[role="alert"]');
    }

    public function onlyParticipantsCanReadAChat(FunctionalTester $I): void
    {
        $conversation = $this->request($I, 'alice', 'CHAT-CAROL', 'Private, Carol.');

        $this->signIn($I, 'bob');
        $I->amOnPage($this->messages('bob', $conversation));
        $I->seeResponseCodeIs(404);
        $I->amOnPage($this->messages('bob', $conversation).'/poll?after=0');
        $I->seeResponseCodeIs(404);
        // Not even through someone else's PipBoy.
        $I->amOnPage($this->messages('alice', $conversation));
        $I->seeResponseCodeIs(404);
    }

    public function numbersOutsideTheLarpOrWithoutChatDoNotAnswer(FunctionalTester $I): void
    {
        $this->signIn($I, 'alice');
        foreach (['CHAT-DAVE', 'TEST-READER', 'CHAT-ALICE', 'NO-SUCH-NUMBER'] as $number) {
            $I->amOnPage($this->messages('alice'));
            $I->submitForm('.pip-chat-new', ['pipboy' => $number, 'message' => 'Anyone?']);
            $I->see('No PipBoy with that number answers.', '[role="alert"]');
        }
    }

    public function chatRequiresTheFeatureAndValidTokens(FunctionalTester $I): void
    {
        // The holodisk reader has no chat feature.
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage('/pipboy/'.TestFixture::$readerPlayerId.'/comms/messages');
        $I->seeResponseCodeIs(404);

        $this->signIn($I, 'carol');
        $I->amOnPage($this->messages('carol'));
        $I->submitForm('.pip-chat-new', ['pipboy' => 'CHAT-ALICE', 'message' => 'Forged', '_token' => 'invalid']);
        $I->seeResponseCodeIs(403);
    }

    private function request(FunctionalTester $I, string $from, string $number, string $message): int
    {
        $this->signIn($I, $from);
        $I->amOnPage($this->messages($from));
        $I->submitForm('.pip-chat-new', ['pipboy' => $number, 'message' => $message]);
        $I->seeResponseCodeIs(200);

        return (int) $I->grabFromCurrentUrl('~/comms/messages/(\d+)$~');
    }

    private function signIn(FunctionalTester $I, string $who): void
    {
        $I->amOnPage('/logout');
        $I->signInAs(TestFixture::CHAT_PLAYERS[$who]['email']);
    }

    private function messages(string $who, ?int $conversation = null): string
    {
        return '/pipboy/'.TestFixture::$chatPlayerIds[$who].'/comms/messages'.($conversation !== null ? '/'.$conversation : '');
    }
}
