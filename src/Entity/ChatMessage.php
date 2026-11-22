<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity]
#[ORM\Table(name: 'chat_message')]
#[ORM\Index(name: 'chat_message_conversation_id', columns: ['conversation_id', 'id'])]
class ChatMessage
{
    public const int MAX_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Player $sender;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $sentAt;

    public function __construct(Conversation $conversation, Player $sender, string $body, DateTimeImmutable $sentAt = new DateTimeImmutable())
    {
        if (!$conversation->involves($sender)) {
            throw new DomainException('You are not part of this chat.');
        }
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > self::MAX_LENGTH) {
            throw new DomainException(sprintf('Write a message of 1 to %d characters.', self::MAX_LENGTH));
        }

        $this->conversation = $conversation;
        $this->sender = $sender;
        $this->body = $body;
        $this->sentAt = $sentAt;
        $conversation->recordMessage($sentAt);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getSender(): Player
    {
        return $this->sender;
    }

    public function isFrom(Player $player): bool
    {
        return $this->sender === $player || ($player->getId() !== null && $this->sender->getId() === $player->getId());
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getSentAt(): DateTimeImmutable
    {
        return $this->sentAt;
    }
}
