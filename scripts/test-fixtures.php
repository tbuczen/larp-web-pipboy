<?php

declare(strict_types=1);

use App\Entity\Larp;
use App\Entity\Player;
use App\Entity\User;
use App\Entity\EventInvitation;
use App\Enum\RegistrationStatus;
use App\Kernel;
use App\Service\EventInvitationManager;
use App\Tests\Support\TestFixture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require_once dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) !== 'test') {
    throw new RuntimeException('Run fixtures with APP_ENV=test. Development data must remain untouched.');
}

$kernel = new Kernel('test', true);
$kernel->boot();

try {
    $container = $kernel->getContainer()->get('test.service_container');
    $entityManager = $container->get(EntityManagerInterface::class);
    $databaseName = $entityManager->getConnection()->getDatabase();

    if (!is_string($databaseName) || !str_ends_with($databaseName, '_test')) {
        throw new RuntimeException('Test fixtures require a dedicated database ending in _test.');
    }

    $larpRepository = $entityManager->getRepository(Larp::class);
    foreach ($larpRepository->findBy(['name' => [
        TestFixture::CREATED_LARP_NAME,
        TestFixture::EDITED_LARP_NAME,
        TestFixture::CSRF_LARP_NAME,
    ]]) as $previousTestLarp) {
        $entityManager->remove($previousTestLarp);
    }

    $passwordHasher = $container->get(UserPasswordHasherInterface::class);
    $userRepository = $entityManager->getRepository(User::class);

    foreach ([TestFixture::REGISTERED_EMAIL, TestFixture::INVALID_PASSWORD_EMAIL, TestFixture::INVALID_CSRF_EMAIL] as $email) {
        $previousUser = $userRepository->findOneBy(['email' => $email]);
        if ($previousUser === null) {
            continue;
        }
        foreach ($entityManager->getRepository(Player::class)->findBy(['user' => $previousUser]) as $previousPlayer) {
            $entityManager->remove($previousPlayer);
        }
        $entityManager->flush();
        $entityManager->remove($previousUser);
    }

    $users = [];
    foreach ([
        TestFixture::ADMIN_EMAIL => ['ROLE_SUPER_ADMIN'],
        TestFixture::ORGANIZER_EMAIL => ['ROLE_ORGANIZER'],
        TestFixture::PLAYER_EMAIL => [],
        TestFixture::PENDING_PLAYER_EMAIL => [],
        TestFixture::INVITED_EMAIL => [],
        TestFixture::GET_ONLY_EMAIL => [],
        TestFixture::ADMIN_INVITED_EMAIL => [],
    ] as $email => $roles) {
        $user = $userRepository->findOneBy(['email' => $email]) ?? new User();
        $user->setEmail($email)->setRoles($roles)->setFirstName('Test')->setLastName('Account');
        $user->setPassword($passwordHasher->hashPassword($user, TestFixture::PASSWORD));
        $entityManager->persist($user);
        $users[$email] = $user;
    }

    $larp = $larpRepository->findOneBy(['name' => TestFixture::LARP_NAME]) ?? new Larp();
    $larp->setName(TestFixture::LARP_NAME);
    $larp->addOrganizer($users[TestFixture::ORGANIZER_EMAIL]);
    $entityManager->persist($larp);
    $otherLarp = $larpRepository->findOneBy(['name' => TestFixture::OTHER_LARP_NAME]) ?? new Larp();
    $otherLarp->setName(TestFixture::OTHER_LARP_NAME);
    $entityManager->persist($otherLarp);
    $entityManager->flush();

    TestFixture::$larpId = $larp->getId();
    TestFixture::$otherLarpId = $otherLarp->getId();
    $playerUser = $users[TestFixture::PLAYER_EMAIL];
    $player = $entityManager->getRepository(Player::class)->findOneBy(['user' => $playerUser, 'larp' => $larp]) ?? new Player();
    $player->setUser($playerUser)->setLarp($larp)->setPipboyNumber('TEST-0001')->setName('Test player')->approve();
    $entityManager->persist($player);
    $pendingUser = $users[TestFixture::PENDING_PLAYER_EMAIL];
    $pendingPlayer = $entityManager->getRepository(Player::class)->findOneBy(['user' => $pendingUser, 'larp' => $larp]) ?? new Player();
    $pendingPlayer->setUser($pendingUser)->setLarp($larp)->setPipboyNumber('TEST-PENDING')
        ->setName(TestFixture::PENDING_CHARACTER_NAME)->setStatus(RegistrationStatus::PENDING);
    $entityManager->persist($pendingPlayer);
    $entityManager->flush();

    foreach ($entityManager->getRepository(EventInvitation::class)->findBy(['larp' => [$larp, $otherLarp]]) as $previousInvitation) {
        $entityManager->remove($previousInvitation);
    }
    foreach ([TestFixture::INVITED_EMAIL, TestFixture::GET_ONLY_EMAIL, TestFixture::ADMIN_INVITED_EMAIL] as $email) {
        foreach ($entityManager->getRepository(Player::class)->findBy(['user' => $users[$email]]) as $previousPlayer) {
            $entityManager->remove($previousPlayer);
        }
    }
    $entityManager->flush();

    $invitationManager = $container->get(EventInvitationManager::class);
    $expiresAt = new DateTimeImmutable('+1 day');
    foreach ([
        'valid' => TestFixture::INVITED_EMAIL,
        'get-only' => TestFixture::GET_ONLY_EMAIL,
        'wrong-email' => TestFixture::INVITED_EMAIL,
        'expired' => TestFixture::INVITED_EMAIL,
        'revoked' => TestFixture::INVITED_EMAIL,
        'used' => TestFixture::INVITED_EMAIL,
    ] as $key => $email) {
        $generated = $invitationManager->createInvite($larp, $email, $expiresAt);
        TestFixture::$invitationTokens[$key] = $generated->token;
        match ($key) {
            'expired' => $generated->invitation->setExpiresAt(new DateTimeImmutable('-1 day')),
            'revoked' => $generated->invitation->revoke(),
            'used' => $generated->invitation->markAcceptedAt(new DateTimeImmutable()),
            default => null,
        };
    }
    $invitationManager->createInvite($otherLarp, TestFixture::UNRELATED_INVITATION_EMAIL, $expiresAt);
    $entityManager->flush();
    $container->get('cache.rate_limiter')->clear();

    echo "Test fixtures ready in the isolated database.\n";
} finally {
    $kernel->shutdown();
}
