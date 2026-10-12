<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EventInvitation;
use App\Entity\Larp;
use App\Entity\Player;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use InvalidArgumentException;

final class EventInvitationManager
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function createInvite(Larp $larp, string $email, DateTimeImmutable $expiresAt): GeneratedInvitation
    {
        $email = mb_strtolower(trim($email));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 180) {
            throw new InvalidArgumentException('An invitation requires a valid email address.');
        }

        if ($expiresAt <= new DateTimeImmutable()) {
            throw new InvalidArgumentException('An invitation must expire in the future.');
        }

        $token = bin2hex(random_bytes(32));
        $invitation = (new EventInvitation())->setLarp($larp)->setEmail($email)
            ->setTokenHash(hash('sha256', $token))->setExpiresAt($expiresAt);
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        return new GeneratedInvitation($invitation, $token);
    }

    public function findByToken(string $token): ?EventInvitation
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }

        return $this->entityManager->getRepository(EventInvitation::class)->findOneBy(['tokenHash' => hash('sha256', $token)]);
    }

    public function accept(string $token, User $user, string $characterName): Player
    {
        $characterName = trim($characterName);

        if ($characterName === '' || mb_strlen($characterName) > 180) {
            throw new DomainException('Enter a character name between 1 and 180 characters.');
        }

        $invitation = $this->findByToken($token);
        $now = new DateTimeImmutable();

        if ($invitation === null || !$invitation->isAvailableAt($now)) {
            throw new DomainException('This invitation is unavailable or has expired.');
        }

        if ($user->getId() === null || $invitation->getEmail() !== $user->getEmail()) {
            throw new DomainException('Sign in with the account this invitation was issued to.');
        }

        $player = $this->entityManager->wrapInTransaction(function () use ($invitation, $user, $characterName): Player {
            // The conditional update locks the row and makes simultaneous acceptance single-use.
            $claimed = $this->entityManager->getConnection()->executeStatement(
                'UPDATE event_invitation SET accepted_at = clock_timestamp() '
                .'WHERE id = :id AND token_hash = :token_hash AND email = :email AND larp_id = :larp_id '
                .'AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > clock_timestamp()',
                [
                    'id' => $invitation->getId(),
                    'token_hash' => $invitation->getTokenHash(),
                    'email' => $user->getEmail(),
                    'larp_id' => $invitation->getLarp()->getId(),
                ],
            );

            if ($claimed !== 1) {
                throw new DomainException('This invitation is unavailable or has expired.');
            }

            if ($this->entityManager->getRepository(Player::class)->findOneBy(['user' => $user, 'larp' => $invitation->getLarp()])) {
                throw new DomainException('You already have a player in this LARP.');
            }

            $player = (new Player())->setUser($user)->setLarp($invitation->getLarp())
                ->setName($characterName)->setPipboyNumber((string) random_int(10000000, 99999999));
            $this->entityManager->persist($player);

            return $player;
        });
        $this->entityManager->refresh($invitation);

        return $player;
    }
}
