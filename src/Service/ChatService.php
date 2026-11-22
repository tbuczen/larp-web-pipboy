<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ChatMessage;
use App\Entity\Conversation;
use App\Entity\Player;
use App\Enum\ChatStatus;
use App\Enum\Feature;
use DomainException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Direct chat between players of one LARP. A chat starts as a request to a
 * PipBoy number with an opening message; the receiver accepts or declines.
 * Lookups never reveal whether a number exists, belongs to another event or
 * lacks chat access: all of those answer the same way.
 */
final class ChatService
{
    public const string NO_ANSWER = 'No PipBoy with that number answers.';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function requestChat(Player $sender, string $pipboyNumber, string $openingMessage): Conversation
    {
        $this->assertCanChat($sender);
        $recipient = $this->recipient($sender, $pipboyNumber);
        $existing = $this->conversationBetween($sender, $recipient);

        if ($existing !== null) {
            return $this->continueExisting($existing, $sender, $openingMessage);
        }

        $conversation = Conversation::request($sender, $recipient);
        $message = new ChatMessage($conversation, $sender, $openingMessage);
        $this->entityManager->persist($conversation);
        $this->entityManager->persist($message);
        $this->entityManager->flush();
        $conversation->markReadUpTo($sender, (int) $message->getId());
        $this->entityManager->flush();

        return $conversation;
    }

    public function send(Player $sender, Conversation $conversation, string $body): ChatMessage
    {
        $this->assertCanChat($sender);
        if (!$conversation->canWrite($sender)) {
            throw new DomainException('This chat is not open for messages.');
        }
        if (!$conversation->otherParticipant($sender)->canUse(Feature::CHAT)) {
            throw new DomainException('This PipBoy no longer receives messages.');
        }

        $message = new ChatMessage($conversation, $sender, $body);
        $this->entityManager->persist($message);
        $this->entityManager->flush();
        $conversation->markReadUpTo($sender, (int) $message->getId());
        $this->entityManager->flush();

        return $message;
    }

    public function accept(Player $receiver, Conversation $conversation): void
    {
        $conversation->accept($receiver);
        $this->entityManager->flush();
    }

    public function decline(Player $receiver, Conversation $conversation): void
    {
        $conversation->decline($receiver);
        $this->entityManager->flush();
    }

    public function close(Player $participant, Conversation $conversation): void
    {
        $conversation->close($participant);
        $this->entityManager->flush();
    }

    public function setRevealsName(Player $participant, Conversation $conversation, bool $reveals): void
    {
        $conversation->setRevealsName($participant, $reveals);
        $this->entityManager->flush();
    }

    /** @return list<Conversation> conversations the player may see, most recent first */
    public function conversationsFor(Player $player): array
    {
        $conversations = $this->entityManager->createQueryBuilder()
            ->select('conversation')->from(Conversation::class, 'conversation')
            ->andWhere('conversation.playerA = :player OR conversation.playerB = :player')
            ->setParameter('player', $player)
            ->orderBy('conversation.lastActivityAt', 'DESC')
            ->getQuery()->getResult();

        return array_values(array_filter($conversations, static fn (Conversation $conversation): bool => $conversation->isVisibleTo($player)));
    }

    /**
     * Messages after a known id, oldest first. Without an id, the latest page.
     *
     * @return list<ChatMessage>
     */
    public function messages(Conversation $conversation, int $afterId = 0, int $limit = 100): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('message')->from(ChatMessage::class, 'message')
            ->andWhere('message.conversation = :conversation')->setParameter('conversation', $conversation)
            ->setMaxResults($limit);

        if ($afterId > 0) {
            return $query->andWhere('message.id > :after')->setParameter('after', $afterId)
                ->orderBy('message.id', 'ASC')->getQuery()->getResult();
        }

        return array_reverse($query->orderBy('message.id', 'DESC')->getQuery()->getResult());
    }

    /** @param list<ChatMessage> $messages */
    public function markRead(Player $reader, Conversation $conversation, array $messages): void
    {
        $last = end($messages);
        if ($last instanceof ChatMessage && $last->getId() > $conversation->lastReadId($reader)) {
            $conversation->markReadUpTo($reader, (int) $last->getId());
            $this->entityManager->flush();
        }
    }

    /**
     * Unread messages from the other participant, per conversation id.
     *
     * @param list<Conversation> $conversations
     * @return array<int, int>
     */
    public function unreadCounts(Player $reader, array $conversations): array
    {
        $counts = [];
        foreach ($conversations as $conversation) {
            $counts[(int) $conversation->getId()] = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(message.id)')->from(ChatMessage::class, 'message')
                ->andWhere('message.conversation = :conversation AND message.id > :lastRead AND message.sender != :reader')
                ->setParameter('conversation', $conversation)
                ->setParameter('lastRead', $conversation->lastReadId($reader))
                ->setParameter('reader', $reader)
                ->getQuery()->getSingleScalarResult();
        }

        return $counts;
    }

    private function continueExisting(Conversation $existing, Player $sender, string $message): Conversation
    {
        return match (true) {
            // Already chatting: the "request" is simply the next message.
            $existing->getStatus() === ChatStatus::ACTIVE => $this->send($sender, $existing, $message)->getConversation(),
            // Declined requests look pending to the requester, and cannot be repeated.
            $existing->isRequestedBy($sender) && in_array($existing->getStatus(), [ChatStatus::PENDING, ChatStatus::DECLINED], true)
                => throw new DomainException('Your request to this PipBoy is waiting for an answer.'),
            $existing->awaitsAnswerFrom($sender) => throw new DomainException('This PipBoy has already sent you a request. Answer it in your messages.'),
            $existing->getStatus() === ChatStatus::CLOSED => throw new DomainException('This chat has been closed.'),
            default => throw new DomainException(self::NO_ANSWER),
        };
    }

    private function recipient(Player $sender, string $pipboyNumber): Player
    {
        $recipient = $this->entityManager->getRepository(Player::class)->findOneBy([
            'larp' => $sender->getLarp(),
            'pipboyNumber' => trim($pipboyNumber),
        ]);

        if (!$recipient instanceof Player || $recipient->getId() === $sender->getId() || !$recipient->canUse(Feature::CHAT)) {
            throw new DomainException(self::NO_ANSWER);
        }

        return $recipient;
    }

    private function conversationBetween(Player $one, Player $two): ?Conversation
    {
        [$a, $b] = $one->getId() < $two->getId() ? [$one, $two] : [$two, $one];

        return $this->entityManager->getRepository(Conversation::class)->findOneBy(['playerA' => $a, 'playerB' => $b]);
    }

    private function assertCanChat(Player $player): void
    {
        if (!$player->canUse(Feature::CHAT)) {
            throw new DomainException('Messages are not enabled for your PipBoy.');
        }
    }
}
