<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\EventInvitation;
use App\Entity\Larp;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\InvitationRole;
use App\Service\EventInvitationManager;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EventInvitationManagerTest extends TestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testIssuanceStoresOnlyTokenHashAndNormalizesRecipient(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $generated = (new EventInvitationManager($entityManager))->createInvite(new Larp(), ' Player@Example.COM ', new DateTimeImmutable('+1 day'));

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $generated->token);
        self::assertSame(hash('sha256', $generated->token), $generated->invitation->getTokenHash());
        self::assertSame('player@example.com', $generated->invitation->getEmail());
        self::assertStringNotContainsString($generated->token, serialize($generated->invitation));
    }

    public function testIssuanceRejectsExpiredInvitationBeforeWriting(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $this->expectException(InvalidArgumentException::class);

        (new EventInvitationManager($entityManager))->createInvite(new Larp(), 'player@example.com', new DateTimeImmutable('-1 day'));
    }

    public function testMalformedTokensDoNotQueryDatabase(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');

        self::assertNull((new EventInvitationManager($entityManager))->findByToken('invalid'));
    }

    public function testLookupUsesTokenHashRatherThanBearerToken(): void
    {
        $invitation = new EventInvitation();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())->method('findOneBy')
            ->with(['tokenHash' => hash('sha256', self::TOKEN)])->willReturn($invitation);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        self::assertSame($invitation, (new EventInvitationManager($entityManager))->findByToken(self::TOKEN));
    }

    public function testWrongAccountCannotClaimInvitation(): void
    {
        $invitation = (new EventInvitation())->setEmail('player@example.com');
        $entityManager = $this->invitationEntityManager($invitation);
        $entityManager->expects(self::never())->method('wrapInTransaction');

        $this->expectException(DomainException::class);

        (new EventInvitationManager($entityManager))->accept(self::TOKEN, $this->user('other@example.com'), 'Character');
    }

    public function testExpiredInvitationCannotBeginAcceptanceTransaction(): void
    {
        $invitation = (new EventInvitation())->setEmail('player@example.com')->setExpiresAt(new DateTimeImmutable('-1 second'));
        $entityManager = $this->invitationEntityManager($invitation);
        $entityManager->expects(self::never())->method('wrapInTransaction');

        $this->expectException(DomainException::class);

        (new EventInvitationManager($entityManager))->accept(self::TOKEN, $this->user(), 'Character');
    }

    public function testDatabaseClaimFailureCannotCreatePlayerEvenWhenLoadedInvitationAppearsAvailable(): void
    {
        $invitation = (new EventInvitation())->setEmail('player@example.com')->setLarp(new Larp());
        $entityManager = $this->invitationEntityManager($invitation);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn (callable $transaction): mixed => $transaction());
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeStatement')->willReturn(0);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::never())->method('persist');

        $this->expectException(DomainException::class);

        (new EventInvitationManager($entityManager))->accept(self::TOKEN, $this->user(), 'Character');
    }

    public function testDuplicateMembershipRaisesInsideTheClaimTransaction(): void
    {
        $invitation = (new EventInvitation())->setEmail('player@example.com')->setLarp(new Larp());
        $invitations = $this->createStub(EntityRepository::class);
        $invitations->method('findOneBy')->willReturn($invitation);
        $players = $this->createStub(EntityRepository::class);
        $players->method('findOneBy')->willReturn(new Player());
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([[EventInvitation::class, $invitations], [Player::class, $players]]);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $transaction): mixed => $transaction());
        $entityManager->expects(self::never())->method('persist');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('You already have a player');

        (new EventInvitationManager($entityManager))->accept(self::TOKEN, $this->user(), 'Character');
    }

    public function testCharacterNameIsRequiredBeforeReadingInvitation(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');

        $this->expectException(DomainException::class);

        (new EventInvitationManager($entityManager))->accept(self::TOKEN, $this->user(), '   ');
    }

    public function testOrganizersCannotIssueStaffInvitations(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $organizer = $this->user('organizer@example.com')->setRoles(['ROLE_ORGANIZER']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only superadministrators');

        (new EventInvitationManager($entityManager))
            ->createInvite(new Larp(), 'new-organizer@example.com', new DateTimeImmutable('+1 day'), InvitationRole::ORGANIZER, $organizer);
    }

    public function testSuperAdministratorsCanIssueOrganizerInvitations(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $admin = $this->user('admin@example.com')->setRoles(['ROLE_SUPER_ADMIN']);

        $generated = (new EventInvitationManager($entityManager))
            ->createInvite(new Larp(), 'new-organizer@example.com', new DateTimeImmutable('+1 day'), InvitationRole::ORGANIZER, $admin);

        self::assertSame(InvitationRole::ORGANIZER, $generated->invitation->getRole());
    }

    public function testOrganizerInvitationGrantsRoleAndAssignsTheLarp(): void
    {
        $larp = new Larp();
        $invitation = (new EventInvitation())->setEmail('player@example.com')->setLarp($larp)->setRole(InvitationRole::ORGANIZER);
        $entityManager = $this->claimingEntityManager($invitation);
        $user = $this->user();

        $role = (new EventInvitationManager($entityManager))->acceptStaffRole(self::TOKEN, $user);

        self::assertSame(InvitationRole::ORGANIZER, $role);
        self::assertContains('ROLE_ORGANIZER', $user->getRoles());
        self::assertTrue($larp->getOrganizers()->contains($user));
    }

    public function testSuperAdministratorInvitationGrantsTheGlobalRole(): void
    {
        $larp = new Larp();
        $invitation = (new EventInvitation())->setEmail('player@example.com')->setLarp($larp)->setRole(InvitationRole::SUPER_ADMIN);
        $user = $this->user();

        (new EventInvitationManager($this->claimingEntityManager($invitation)))->acceptStaffRole(self::TOKEN, $user);

        self::assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
        self::assertFalse($larp->getOrganizers()->contains($user));
    }

    public function testPlayerAndStaffInvitationsCannotBeAcceptedThroughTheOtherFlow(): void
    {
        $staff = (new EventInvitation())->setEmail('player@example.com')->setLarp(new Larp())->setRole(InvitationRole::ORGANIZER);
        $player = (new EventInvitation())->setEmail('player@example.com')->setLarp(new Larp());

        $staffEntityManager = $this->invitationEntityManager($staff);
        $staffEntityManager->expects(self::never())->method('wrapInTransaction');
        $playerEntityManager = $this->invitationEntityManager($player);
        $playerEntityManager->expects(self::never())->method('wrapInTransaction');

        try {
            (new EventInvitationManager($staffEntityManager))->accept(self::TOKEN, $this->user(), 'Character');
            self::fail('A staff invitation must not create a player.');
        } catch (DomainException) {
        }

        $this->expectException(DomainException::class);
        (new EventInvitationManager($playerEntityManager))->acceptStaffRole(self::TOKEN, $this->user());
    }

    private function claimingEntityManager(EventInvitation $invitation): EntityManagerInterface&MockObject
    {
        $entityManager = $this->invitationEntityManager($invitation);
        $entityManager->expects(self::once())->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $transaction): mixed => $transaction());
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturn(1);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    private function invitationEntityManager(EventInvitation $invitation): EntityManagerInterface&MockObject
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($invitation);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return $entityManager;
    }

    private function user(string $email = 'player@example.com'): User
    {
        $user = (new User())->setEmail($email);
        (new ReflectionProperty(User::class, 'id'))->setValue($user, 1);

        return $user;
    }
}
