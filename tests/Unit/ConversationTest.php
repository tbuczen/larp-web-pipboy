<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ChatMessage;
use App\Entity\Conversation;
use App\Entity\Larp;
use App\Entity\Player;
use App\Enum\ChatStatus;
use DomainException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ConversationTest extends TestCase
{
    private Larp $larp;

    protected function setUp(): void
    {
        $this->larp = new Larp();
    }

    public function testRequestsWaitForTheReceiver(): void
    {
        [$alice, $bob] = [$this->player(7, 'ALICE'), $this->player(3, 'BOB')];
        $chat = Conversation::request($alice, $bob);

        self::assertSame(ChatStatus::PENDING, $chat->getStatus());
        self::assertTrue($chat->awaitsAnswerFrom($bob));
        self::assertFalse($chat->awaitsAnswerFrom($alice));
        self::assertFalse($chat->canWrite($alice), 'Only the opening message is allowed before acceptance.');

        $chat->accept($bob);
        self::assertTrue($chat->canWrite($alice));
        self::assertTrue($chat->canWrite($bob));
    }

    public function testRequestersCannotAcceptTheirOwnRequest(): void
    {
        [$alice, $bob] = [$this->player(1, 'ALICE'), $this->player(2, 'BOB')];
        $chat = Conversation::request($alice, $bob);

        $this->expectException(DomainException::class);
        $chat->accept($alice);
    }

    public function testDeclinedRequestsStillLookPendingToTheRequester(): void
    {
        [$alice, $bob] = [$this->player(1, 'ALICE'), $this->player(2, 'BOB')];
        $chat = Conversation::request($alice, $bob);
        $chat->decline($bob);

        self::assertSame(ChatStatus::DECLINED, $chat->getStatus());
        self::assertSame(ChatStatus::PENDING, $chat->statusFor($alice));
        self::assertTrue($chat->isVisibleTo($alice));
        self::assertFalse($chat->isVisibleTo($bob), 'The receiver no longer sees a declined request.');
        self::assertFalse($chat->canWrite($alice));
    }

    public function testCharacterNamesNeedMutualConsentAndRealNamesNeverAppear(): void
    {
        [$alice, $bob] = [$this->player(1, 'ALICE', 'Vera Coil'), $this->player(2, 'BOB', 'Rusty')];
        $chat = Conversation::request($alice, $bob);
        $chat->accept($bob);

        $chat->setRevealsName($alice, true);
        self::assertFalse($chat->namesRevealed());
        self::assertSame('PB BOB', $chat->labelFor($alice));
        self::assertSame('PB ALICE', $chat->labelFor($bob));

        $chat->setRevealsName($bob, true);
        self::assertTrue($chat->namesRevealed());
        self::assertSame('PB BOB · Rusty', $chat->labelFor($alice));
        self::assertSame('PB ALICE · Vera Coil', $chat->labelFor($bob));

        // Either side can withdraw consent.
        $chat->setRevealsName($bob, false);
        self::assertSame('PB ALICE', $chat->labelFor($bob));
    }

    public function testClosingStopsWritingAndHidesNames(): void
    {
        [$alice, $bob] = [$this->player(1, 'ALICE'), $this->player(2, 'BOB')];
        $chat = Conversation::request($alice, $bob);
        $chat->accept($bob);
        $chat->setRevealsName($alice, true);
        $chat->setRevealsName($bob, true);
        $chat->close($bob);

        self::assertSame(ChatStatus::CLOSED, $chat->statusFor($alice));
        self::assertFalse($chat->canWrite($alice));
        self::assertFalse($chat->namesRevealed());
        self::assertTrue($chat->isVisibleTo($bob), 'History stays readable after closing.');
    }

    public function testPlayersCannotChatWithThemselvesOrAcrossEvents(): void
    {
        $alice = $this->player(1, 'ALICE');
        try {
            Conversation::request($alice, $alice);
            self::fail('Self-requests must be rejected.');
        } catch (DomainException) {
        }

        $stranger = $this->player(2, 'STRANGER', larp: new Larp());
        $this->expectException(DomainException::class);
        Conversation::request($alice, $stranger);
    }

    public function testOnlyParticipantsCanWriteMessagesOfReasonableLength(): void
    {
        [$alice, $bob, $eve] = [$this->player(1, 'ALICE'), $this->player(2, 'BOB'), $this->player(3, 'EVE')];
        $chat = Conversation::request($alice, $bob);

        self::assertSame('Hello?', (new ChatMessage($chat, $alice, '  Hello?  '))->getBody());

        foreach ([[$eve, 'Hi'], [$alice, '   '], [$alice, str_repeat('x', ChatMessage::MAX_LENGTH + 1)]] as [$sender, $body]) {
            try {
                new ChatMessage($chat, $sender, $body);
                self::fail('The message must be rejected.');
            } catch (DomainException) {
            }
        }
    }

    public function testReadMarksOnlyMoveForward(): void
    {
        [$alice, $bob] = [$this->player(1, 'ALICE'), $this->player(2, 'BOB')];
        $chat = Conversation::request($alice, $bob);
        $chat->markReadUpTo($bob, 10);
        $chat->markReadUpTo($bob, 4);

        self::assertSame(10, $chat->lastReadId($bob));
        self::assertSame(0, $chat->lastReadId($alice));
    }

    private function player(int $id, string $number, string $name = 'Character', ?Larp $larp = null): Player
    {
        $player = (new Player())->setLarp($larp ?? $this->larp)->setPipboyNumber($number)->setName($name);
        (new ReflectionProperty(Player::class, 'id'))->setValue($player, $id);

        return $player;
    }
}
