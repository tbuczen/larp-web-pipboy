<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Player;
use App\Enum\Feature;
use App\Enum\RegistrationStatus;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testFeatureGrantIsIdempotentAndRequiresApproval(): void
    {
        $player = new Player();

        $player->grantFeature(Feature::MAP)->grantFeature(Feature::MAP);

        self::assertCount(1, $player->getFeatureAccesses());
        self::assertFalse($player->canUse(Feature::MAP));
        self::assertSame(RegistrationStatus::PENDING, $player->getStatus());

        $player->approve();

        self::assertTrue($player->canUse(Feature::MAP));
        self::assertFalse($player->canUse(Feature::CHAT));
    }

    public function testRejectingPlayerDisablesTheirGrantedFeatures(): void
    {
        $player = (new Player())->grantFeature(Feature::CHAT);
        $player->approve();
        $player->reject();

        self::assertFalse($player->canUse(Feature::CHAT));
        self::assertSame(RegistrationStatus::REJECTED, $player->getStatus());
        self::assertCount(1, $player->getFeatureAccesses());
    }

    public function testFeatureRevocationDoesNotRemoveOtherPermissions(): void
    {
        $player = (new Player())->grantFeature(Feature::MAP)->grantFeature(Feature::NOTES);
        $player->approve();
        $player->revokeFeature(Feature::MAP);

        self::assertFalse($player->canUse(Feature::MAP));
        self::assertTrue($player->canUse(Feature::NOTES));
    }
}
