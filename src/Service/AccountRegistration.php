<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Form\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountRegistration
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function register(RegistrationData $details, ?string $discordId = null): User
    {
        $account = (new User())->setEmail($details->email)
            ->setFirstName($details->firstName)
            ->setLastName($details->lastName)
            ->setDiscordId($discordId);

        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $account->getEmail()])) {
            throw new \DomainException('This email address already has an account. Sign in to continue.');
        }

        $password = $discordId !== null ? bin2hex(random_bytes(32)) : $details->password;
        if ($password === null) {
            throw new \DomainException('Enter a password with at least 12 characters.');
        }
        $account->setPassword($this->passwordHasher->hashPassword($account, $password));

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }
}
