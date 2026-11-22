<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class MotionDetectorCest
{
    // Bob stands about 22 m north and 14 m east of Alice.
    private const array ALICE = ['latitude' => '52.2297000', 'longitude' => '21.0122000', 'accuracy' => '8'];
    private const array BOB = ['latitude' => '52.2299000', 'longitude' => '21.0124000', 'accuracy' => '8'];

    public function sharingPlayersSeeEachOtherAsAnonymousBlips(FunctionalTester $I): void
    {
        $this->share($I, 'bob', self::BOB);
        $I->amOnPage('/logout');

        // Alice sees nothing until she shares herself.
        $token = $this->openDetector($I, 'alice');
        $I->assertSame(['sharing' => false, 'blips' => []], $this->blips($I, 'alice'));

        $this->post($I, 'alice', 'position', $token, self::ALICE);
        $I->assertSame(['sharing' => true, 'blips' => [['x' => 15, 'y' => 20]]], $this->blips($I, 'alice', 50));
        // Only rounded offsets: no names, numbers or coordinates.
        $I->dontSeeInSource('Bob');
        $I->dontSeeInSource(TestFixture::CHAT_PLAYERS['bob']['number']);
        $I->dontSeeInSource('52.2299');

        // Turning sharing off removes the position at once.
        $this->post($I, 'alice', 'stop', $token);
        $I->assertSame(['sharing' => false, 'blips' => []], $this->blips($I, 'alice'));
        $I->amOnPage('/logout');

        $bob = $this->openDetector($I, 'bob');
        $this->post($I, 'bob', 'stop', $bob);
    }

    public function otherLarpsAndPlayersWithoutTheFeatureAreInvisible(FunctionalTester $I): void
    {
        // Dave shares from the same spot as Bob, but plays another LARP.
        $this->share($I, 'dave', self::BOB);
        $I->amOnPage('/logout');
        $token = $this->openDetector($I, 'alice');
        $this->post($I, 'alice', 'position', $token, self::ALICE);
        $I->assertSame([], $this->blips($I, 'alice', 250)['blips']);
        $this->post($I, 'alice', 'stop', $token);
        $I->amOnPage('/logout');

        // Carol has no motion detector: no screen and no endpoints.
        $I->signInAs(TestFixture::CHAT_PLAYERS['carol']['email']);
        $I->amOnPage('/pipboy/'.TestFixture::$chatPlayerIds['carol'].'/map/motion');
        $I->seeResponseCodeIs(404);
        $I->amOnPage($this->url('carol', 'blips'));
        $I->seeResponseCodeIs(404);

        // Nor can anyone use another account's player.
        $I->amOnPage($this->url('alice', 'blips'));
        $I->seeResponseCodeIs(404);
    }

    public function positionUpdatesNeedTheTokenAndValidCoordinates(FunctionalTester $I): void
    {
        $token = $this->openDetector($I, 'bob');
        $this->post($I, 'bob', 'position', 'invalid', self::BOB);
        $I->seeResponseCodeIs(403);
        $this->post($I, 'bob', 'position', $token, ['latitude' => '123', 'longitude' => '21']);
        $I->seeResponseCodeIs(422);
        $this->post($I, 'bob', 'stop', $token);
        $I->seeResponseCodeIs(200);
    }

    public function theScreenExplainsSharingPlainly(FunctionalTester $I): void
    {
        $this->openDetector($I, 'alice');
        $I->see('Share my location: OFF');
        $I->see('anonymous blip');
        $I->see('You see others only while you share yourself.');
    }

    /** @param array<string, string> $position */
    private function share(FunctionalTester $I, string $player, array $position): void
    {
        $token = $this->openDetector($I, $player);
        $this->post($I, $player, 'position', $token, $position);
        $I->seeResponseCodeIs(200);
    }

    private function openDetector(FunctionalTester $I, string $player): string
    {
        $I->signInAs(TestFixture::CHAT_PLAYERS[$player]['email']);
        $I->amOnPage('/pipboy/'.TestFixture::$chatPlayerIds[$player].'/map/motion');
        $I->seeResponseCodeIs(200);

        return $I->grabAttributeFrom('[data-motion]', 'data-token');
    }

    /** @param array<string, string> $fields */
    private function post(FunctionalTester $I, string $player, string $action, string $token, array $fields = []): void
    {
        $I->haveHttpHeader('X-CSRF-Token', $token);
        $I->sendAjaxPostRequest($this->url($player, $action), $fields);
        $I->deleteHeader('X-CSRF-Token');
    }

    /** @return array<string, mixed> */
    private function blips(FunctionalTester $I, string $player, int $range = 100): array
    {
        $I->amOnPage($this->url($player, 'blips').'?range='.$range);
        $I->seeResponseCodeIs(200);

        return json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function url(string $player, string $action): string
    {
        return '/pipboy/'.TestFixture::$chatPlayerIds[$player].'/motion/'.$action;
    }
}
