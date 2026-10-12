<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Holodisk;
use App\Entity\HolodiskAccess;
use App\Entity\Larp;
use App\Entity\Player;
use DomainException;
use PHPUnit\Framework\TestCase;

final class HolodiskAccessTest extends TestCase
{
    public function testPlayerCannotUnlockHolodiskFromAnotherLarp(): void
    {
        $player = (new Player())->setLarp((new Larp())->setName('First event'));
        $holodisk = (new Holodisk())->setLarp((new Larp())->setName('Second event'));

        $this->expectException(DomainException::class);

        HolodiskAccess::grant($player, $holodisk);
    }

    public function testGrantCannotUseMissingEventAsMatchingEvent(): void
    {
        $this->expectException(DomainException::class);

        HolodiskAccess::grant(new Player(), new Holodisk());
    }

    public function testGrantCanBeRevokedAndRestoredWithoutLosingGrantDate(): void
    {
        $larp = new Larp();
        $access = HolodiskAccess::grant(
            (new Player())->setLarp($larp),
            (new Holodisk())->setLarp($larp),
        );
        $grantedAt = $access->getGrantedAt();

        self::assertTrue($access->isActive());

        $access->revoke();
        $revokedAt = $access->getRevokedAt();
        $access->revoke();

        self::assertFalse($access->isActive());
        self::assertNotNull($revokedAt);
        self::assertSame($revokedAt, $access->getRevokedAt());

        $access->restore();

        self::assertTrue($access->isActive());
        self::assertSame($grantedAt, $access->getGrantedAt());
        self::assertNull($access->getRevokedAt());
    }

    public function testChangedEventCannotKeepGrantActive(): void
    {
        $larp = new Larp();
        $player = (new Player())->setLarp($larp);
        $access = HolodiskAccess::grant($player, (new Holodisk())->setLarp($larp));

        $player->setLarp(new Larp());

        self::assertFalse($access->isActive());
    }
}
