<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Condition;
use App\Entity\EventInvitation;
use App\Entity\Holodisk;
use App\Entity\HolodiskAccess;
use App\Entity\Larp;
use App\Entity\Note;
use App\Entity\Player;
use App\Entity\PlayerFeatureAccess;
use App\Entity\User;
use App\Security\EventAccessPolicy;
use App\Security\EventMutationPolicy;
use App\Security\EventVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EventSecurityTest extends TestCase
{
    /** @param class-string $class */
    #[DataProvider('scopedEntityClasses')]
    public function testOrganizerCanOnlyManageAssignedEventData(string $class): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $ownEvent = (new Larp())->addOrganizer($organizer);
        $otherEvent = new Larp();
        $policy = new EventAccessPolicy();

        self::assertTrue($policy->canManage($organizer, $this->subject($class, $ownEvent)));
        self::assertFalse($policy->canManage($organizer, $this->subject($class, $otherEvent)));
    }

    /** @return iterable<string, array{class-string}> */
    public static function scopedEntityClasses(): iterable
    {
        foreach ([Larp::class, Player::class, Holodisk::class, EventInvitation::class, PlayerFeatureAccess::class, HolodiskAccess::class, Note::class, Condition::class] as $class) {
            yield $class => [$class];
        }
    }

    public function testAssignmentAloneDoesNotGrantOrganizerPrivileges(): void
    {
        $player = new User();
        $event = (new Larp())->addOrganizer($player);

        self::assertFalse((new EventAccessPolicy())->canManage($player, $event));
    }

    public function testSuperadministratorCanManageAnyEventWithoutAssignment(): void
    {
        $superAdmin = (new User())->setRoles(['ROLE_SUPER_ADMIN']);

        self::assertTrue((new EventAccessPolicy())->canManage($superAdmin, new Larp()));
        self::assertFalse((new EventAccessPolicy())->canManage($superAdmin, new User()));
    }

    public function testVoterDeniesUnassignedEventEvenForAuthenticatedOrganizer(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $token = new UsernamePasswordToken($organizer, 'main', $organizer->getRoles());
        $voter = new EventVoter(new EventAccessPolicy());

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, new Larp(), [EventVoter::MANAGE_EVENT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, (new Larp())->addOrganizer($organizer), [EventVoter::MANAGE_EVENT]));
    }

    public function testOrganizerCanOpenScopedListWithoutAnEntity(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $token = new UsernamePasswordToken($organizer, 'main', $organizer->getRoles());

        self::assertSame(VoterInterface::ACCESS_GRANTED, (new EventVoter(new EventAccessPolicy()))->vote($token, null, [EventVoter::MANAGE_EVENT]));
    }

    public function testMovingPlayerBetweenTwoAssignedEventsIsDenied(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $previousEvent = (new Larp())->addOrganizer($organizer);
        $newEvent = (new Larp())->addOrganizer($organizer);
        $player = (new Player())->setLarp($newEvent);

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($organizer, $player, ['larp' => $previousEvent]);
    }

    public function testSuperadministratorCannotMoveExistingHolodiskToAnotherEvent(): void
    {
        $admin = (new User())->setRoles(['ROLE_SUPER_ADMIN']);
        $holodisk = (new Holodisk())->setLarp(new Larp());

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($admin, $holodisk, ['larp' => new Larp()]);
    }

    public function testChangingExistingPlayerUserIsDenied(): void
    {
        $admin = (new User())->setRoles(['ROLE_SUPER_ADMIN']);
        $event = new Larp();
        $player = (new Player())->setLarp($event)->setUser(new User());

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($admin, $player, ['larp' => $event, 'user' => new User()]);
    }

    public function testExistingInvitationCannotBeRetargetedToAnotherEvent(): void
    {
        $admin = (new User())->setRoles(['ROLE_SUPER_ADMIN']);
        $invitation = (new EventInvitation())->setLarp(new Larp());

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($admin, $invitation, ['larp' => new Larp()]);
    }

    public function testRetargetingAnotherEventsNoteToOwnEventIsDenied(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $ownPlayer = (new Player())->setLarp((new Larp())->addOrganizer($organizer));
        $otherPlayer = (new Player())->setLarp(new Larp());
        $note = (new Note())->setPlayer($ownPlayer);

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($organizer, $note, ['player' => $otherPlayer]);
    }

    public function testFormCreatedHolodiskGrantCannotCrossEvents(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $access = (new HolodiskAccess())
            ->setPlayer((new Player())->setLarp((new Larp())->addOrganizer($organizer)))
            ->setHolodisk((new Holodisk())->setLarp(new Larp()));

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($organizer, $access, creating: true);
    }

    public function testOrganizerCannotChangeEventAssignments(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $event = (new Larp())->addOrganizer($organizer)->addOrganizer(new User());

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($organizer, $event, ['organizers' => [$organizer]]);
    }

    public function testOrganizerCannotCreateEventByAssigningThemself(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $event = (new Larp())->addOrganizer($organizer);

        $this->expectException(AccessDeniedException::class);

        $this->mutations()->assertCanWrite($organizer, $event, creating: true);
    }

    public function testOrganizerCanUpdateDataWithinAssignedEvent(): void
    {
        $organizer = (new User())->setRoles(['ROLE_ORGANIZER']);
        $event = (new Larp())->addOrganizer($organizer);
        $playerUser = new User();
        $player = (new Player())->setUser($playerUser)->setLarp($event);

        $this->mutations()->assertCanWrite($organizer, $player, ['larp' => $event, 'user' => $playerUser]);
        $this->mutations()->assertCanWrite($organizer, $event, ['organizers' => [$organizer]]);

        self::assertSame($event, $player->getLarp());
    }

    /** @param class-string $class */
    private function subject(string $class, Larp $event): object
    {
        if ($class === Larp::class) {
            return $event;
        }

        if ($class === Player::class || $class === Holodisk::class || $class === EventInvitation::class) {
            return (new $class())->setLarp($event);
        }

        return (new $class())->setPlayer((new Player())->setLarp($event));
    }

    private function mutations(): EventMutationPolicy
    {
        return new EventMutationPolicy(new EventAccessPolicy());
    }
}
