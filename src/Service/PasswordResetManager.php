<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Larp;
use App\Entity\PasswordResetToken;
use App\Entity\Player;
use App\Entity\User;
use App\Security\EventAccessPolicy;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Organizer-issued password resets. There is no email: an organizer creates a
 * one-time link (valid 24 hours) and gives it to the player. Issuing a new
 * link cancels earlier unused ones; setting the password uses the link up.
 */
final class PasswordResetManager
{
    public const string LIFETIME = '+24 hours';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventAccessPolicy $policy,
    ) {
    }

    /**
     * Superadministrators may reset any account. Organizers may reset player
     * accounts (no staff role) that play in a LARP they organize.
     */
    public function canIssue(User $issuer, User $account): bool
    {
        if ($this->policy->isSuperAdmin($issuer)) {
            return true;
        }
        if (!in_array('ROLE_ORGANIZER', $issuer->getRoles(), true)
            || array_intersect(['ROLE_ORGANIZER', 'ROLE_SUPER_ADMIN'], $account->getRoles()) !== []) {
            return false;
        }

        foreach ($this->entityManager->getRepository(Player::class)->findBy(['user' => $account]) as $player) {
            if ($player->getLarp() instanceof Larp && $player->getLarp()->isOrganizedBy($issuer)) {
                return true;
            }
        }

        return false;
    }

    /** @return string the raw token for the link; it is not stored and cannot be shown again */
    public function issue(User $issuer, User $account): string
    {
        if (!$this->canIssue($issuer, $account)) {
            throw new DomainException('You cannot reset the password of this account.');
        }

        $this->removeUnused($account);
        $token = bin2hex(random_bytes(32));
        $this->entityManager->persist(new PasswordResetToken($account, hash('sha256', $token), new DateTimeImmutable(self::LIFETIME), $issuer));
        $this->entityManager->flush();

        return $token;
    }

    public function find(string $token): ?PasswordResetToken
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }
        $reset = $this->entityManager->getRepository(PasswordResetToken::class)->findOneBy(['tokenHash' => hash('sha256', $token)]);

        return $reset?->isUsableAt(new DateTimeImmutable()) ? $reset : null;
    }

    /** Changing the password also ends "keep me signed in" and other sessions of the account. */
    public function reset(PasswordResetToken $reset, string $newPassword): void
    {
        if (!$reset->isUsableAt(new DateTimeImmutable())) {
            throw new DomainException('This reset link is no longer valid.');
        }

        $user = $reset->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $reset->markUsed();
        // Flush first: the bulk delete below runs in the database directly.
        $this->entityManager->flush();
        $this->removeUnused($user);
    }

    private function removeUnused(User $account): void
    {
        $this->entityManager->createQueryBuilder()->delete(PasswordResetToken::class, 'reset')
            ->where('reset.user = :user AND reset.usedAt IS NULL')->setParameter('user', $account)
            ->getQuery()->execute();
    }
}
