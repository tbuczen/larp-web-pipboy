<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\EventInvitation;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EventInvitationTest extends TestCase
{
    public function testInvitationExpiresAtItsDeadline(): void
    {
        $expiry = new DateTimeImmutable('2026-10-18T12:00:00+00:00');
        $invitation = (new EventInvitation())->setExpiresAt($expiry);

        self::assertTrue($invitation->isAvailableAt($expiry->modify('-1 second')));
        self::assertFalse($invitation->isAvailableAt($expiry));
    }

    public function testRevocationIsPermanentAndIdempotent(): void
    {
        $invitation = (new EventInvitation())->setExpiresAt(new DateTimeImmutable('+1 day'));
        $invitation->revoke();
        $revokedAt = $invitation->getRevokedAt();
        $invitation->revoke();

        self::assertFalse($invitation->isAvailableAt(new DateTimeImmutable()));
        self::assertSame($revokedAt, $invitation->getRevokedAt());
    }

    public function testAcceptedInvitationIsUnavailableBeforeItsDeadline(): void
    {
        $time = new DateTimeImmutable('2026-10-18T11:00:00+00:00');
        $invitation = (new EventInvitation())->setExpiresAt($time->modify('+1 day'));
        $invitation->markAcceptedAt($time);

        self::assertFalse($invitation->isAvailableAt($time));
    }
}
