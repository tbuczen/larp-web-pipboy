<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\GrantAdminCommand;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GrantAdminCommandTest extends TestCase
{
    public function testExistingAccountBecomesSuperAdministrator(): void
    {
        $user = (new User())->setEmail('player@example.org')->setRoles(['ROLE_USER']);
        $tester = $this->tester($user, expectFlush: true);

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => ' Player@Example.org ']));
        self::assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
    }

    public function testUnknownEmailFailsWithoutWriting(): void
    {
        $tester = $this->tester(null, expectFlush: false);

        self::assertSame(Command::FAILURE, $tester->execute(['email' => 'nobody@example.org']));
        self::assertStringContainsString('No account uses this email', $tester->getDisplay());
    }

    public function testExistingSuperAdministratorIsLeftUnchanged(): void
    {
        $user = (new User())->setEmail('admin@example.org')->setRoles(['ROLE_SUPER_ADMIN']);
        $tester = $this->tester($user, expectFlush: false);

        self::assertSame(Command::SUCCESS, $tester->execute(['email' => 'admin@example.org']));
        self::assertStringContainsString('already a superadministrator', $tester->getDisplay());
    }

    private function tester(?User $user, bool $expectFlush): CommandTester
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?User => $user !== null && $criteria === ['email' => $user->getEmail()] ? $user : null,
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(User::class)->willReturn($repository);
        $entityManager->expects($expectFlush ? self::once() : self::never())->method('flush');

        return new CommandTester(new GrantAdminCommand($entityManager));
    }
}
