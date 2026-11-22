<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ChatStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * A direct chat between two players of the same LARP. It starts as a request
 * with an opening message; the receiver accepts or declines. A declined
 * request keeps looking pending to the requester, and the pair can never
 * start another conversation (one row per pair, enforced in the database).
 * Participants are identified by PipBoy number; character names are shown
 * only while both have agreed. Real names never appear.
 */
#[ORM\Entity]
#[ORM\Table(name: 'chat_conversation')]
#[ORM\UniqueConstraint(name: 'chat_conversation_pair', columns: ['player_a_id', 'player_b_id'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** The participant with the lower id; player_a_id < player_b_id keeps one row per pair. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $playerA;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $playerB;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $requestedBy;

    #[ORM\Column(length: 10, enumType: ChatStatus::class)]
    private ChatStatus $status = ChatStatus::PENDING;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $lastActivityAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $playerARevealsName = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $playerBRevealsName = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $playerALastReadId = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $playerBLastReadId = 0;

    private function __construct(Player $requester, Player $receiver, DateTimeImmutable $now)
    {
        if ($requester->getId() === null || $receiver->getId() === null) {
            throw new DomainException('Both players must be saved before they can chat.');
        }
        if ($requester->getId() === $receiver->getId()) {
            throw new DomainException('You cannot send a request to your own PipBoy.');
        }
        if ($requester->getLarp() !== $receiver->getLarp()) {
            throw new DomainException('Players can only chat within their own LARP.');
        }

        [$this->playerA, $this->playerB] = $requester->getId() < $receiver->getId() ? [$requester, $receiver] : [$receiver, $requester];
        $this->requestedBy = $requester;
        $this->createdAt = $this->lastActivityAt = $now;
    }

    public static function request(Player $requester, Player $receiver, DateTimeImmutable $now = new DateTimeImmutable()): self
    {
        return new self($requester, $receiver, $now);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ChatStatus
    {
        return $this->status;
    }

    public function getLastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function involves(Player $player): bool
    {
        return $player === $this->playerA || $player === $this->playerB
            || ($player->getId() !== null && in_array($player->getId(), [$this->playerA->getId(), $this->playerB->getId()], true));
    }

    public function otherParticipant(Player $player): Player
    {
        $this->assertParticipant($player);

        return $this->isPlayerA($player) ? $this->playerB : $this->playerA;
    }

    public function isRequestedBy(Player $player): bool
    {
        return $this->requestedBy === $player || ($player->getId() !== null && $this->requestedBy->getId() === $player->getId());
    }

    /** The status as this participant may see it: a declined request still looks pending to its requester. */
    public function statusFor(Player $player): ChatStatus
    {
        $this->assertParticipant($player);

        return $this->status === ChatStatus::DECLINED ? ChatStatus::PENDING : $this->status;
    }

    /** Declined requests disappear for the receiver who declined them. */
    public function isVisibleTo(Player $player): bool
    {
        return $this->involves($player) && ($this->status !== ChatStatus::DECLINED || $this->isRequestedBy($player));
    }

    public function awaitsAnswerFrom(Player $player): bool
    {
        return $this->status === ChatStatus::PENDING && $this->involves($player) && !$this->isRequestedBy($player);
    }

    public function canWrite(Player $player): bool
    {
        return $this->status === ChatStatus::ACTIVE && $this->involves($player);
    }

    public function accept(Player $receiver, DateTimeImmutable $now = new DateTimeImmutable()): void
    {
        $this->answer($receiver, ChatStatus::ACTIVE, $now);
    }

    public function decline(Player $receiver, DateTimeImmutable $now = new DateTimeImmutable()): void
    {
        $this->answer($receiver, ChatStatus::DECLINED, $now);
    }

    public function close(Player $participant, DateTimeImmutable $now = new DateTimeImmutable()): void
    {
        $this->assertParticipant($participant);
        if ($this->status !== ChatStatus::ACTIVE) {
            throw new DomainException('Only an active chat can be closed.');
        }
        $this->status = ChatStatus::CLOSED;
        $this->playerARevealsName = $this->playerBRevealsName = false;
        $this->lastActivityAt = $now;
    }

    public function recordMessage(DateTimeImmutable $sentAt): void
    {
        $this->lastActivityAt = $sentAt;
    }

    public function setRevealsName(Player $participant, bool $reveals): void
    {
        $this->assertParticipant($participant);
        if ($this->status !== ChatStatus::ACTIVE) {
            throw new DomainException('Names can only be shared in an active chat.');
        }
        $this->isPlayerA($participant) ? $this->playerARevealsName = $reveals : $this->playerBRevealsName = $reveals;
    }

    public function revealsName(Player $participant): bool
    {
        $this->assertParticipant($participant);

        return $this->isPlayerA($participant) ? $this->playerARevealsName : $this->playerBRevealsName;
    }

    /** Character names are shown only while both participants agree. */
    public function namesRevealed(): bool
    {
        return $this->status === ChatStatus::ACTIVE && $this->playerARevealsName && $this->playerBRevealsName;
    }

    /** How the other participant is labelled for this viewer: PipBoy number, plus character name after mutual consent. */
    public function labelFor(Player $viewer): string
    {
        $other = $this->otherParticipant($viewer);
        $label = 'PB '.$other->getPipboyNumber();

        return $this->namesRevealed() ? $label.' · '.$other->getName() : $label;
    }

    public function lastReadId(Player $participant): int
    {
        $this->assertParticipant($participant);

        return $this->isPlayerA($participant) ? $this->playerALastReadId : $this->playerBLastReadId;
    }

    public function markReadUpTo(Player $participant, int $messageId): void
    {
        $this->assertParticipant($participant);
        if ($this->isPlayerA($participant)) {
            $this->playerALastReadId = max($this->playerALastReadId, $messageId);
        } else {
            $this->playerBLastReadId = max($this->playerBLastReadId, $messageId);
        }
    }

    private function answer(Player $receiver, ChatStatus $answer, DateTimeImmutable $now): void
    {
        if (!$this->awaitsAnswerFrom($receiver)) {
            throw new DomainException('This request is not waiting for your answer.');
        }
        $this->status = $answer;
        $this->lastActivityAt = $now;
    }

    private function isPlayerA(Player $player): bool
    {
        return $player === $this->playerA || ($player->getId() !== null && $player->getId() === $this->playerA->getId());
    }

    private function assertParticipant(Player $player): void
    {
        if (!$this->involves($player)) {
            throw new DomainException('You are not part of this chat.');
        }
    }
}
