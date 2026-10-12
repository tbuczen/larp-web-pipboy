<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class UserTest extends TestCase
{
    public function testSessionTokenNeverSerializesTransientPlaintextPassword(): void
    {
        $user = (new User())->setEmail('player@example.com')->setRoles(['ROLE_ORGANIZER'])
            ->setPlainPassword('temporary-password-that-must-not-enter-session');
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $serialized = serialize($token);

        self::assertStringNotContainsString('temporary-password-that-must-not-enter-session', $serialized);
        $restored = unserialize($serialized)->getUser();
        self::assertSame('player@example.com', $restored->getEmail());
        self::assertContains('ROLE_ORGANIZER', $restored->getRoles());
        self::assertNull($restored->getPlainPassword());
    }

    public function testSessionSerializationUsesPasswordDigestWithoutChangingLiveHash(): void
    {
        $passwordHash = password_hash('A sufficiently long test password', PASSWORD_BCRYPT);
        $user = (new User())->setEmail('player@example.com')->setPassword($passwordHash);

        $serialized = serialize($user);
        $restored = unserialize($serialized);

        self::assertStringNotContainsString($passwordHash, $serialized);
        self::assertSame(hash('crc32c', $passwordHash), $restored->getPassword());
        self::assertSame($passwordHash, $user->getPassword());
    }

    public function testClearingTransientPasswordPreservesStoredPasswordHash(): void
    {
        $user = (new User())->setPassword('stored-hash')->setPlainPassword('temporary-password');

        $user->eraseCredentials();

        self::assertNull($user->getPlainPassword());
        self::assertSame('stored-hash', $user->getPassword());
    }

    public function testLoginIdentifierUsesNormalizedEmail(): void
    {
        $user = (new User())->setEmail(' Player@Example.COM ');

        self::assertSame('player@example.com', $user->getUserIdentifier());
    }
}
