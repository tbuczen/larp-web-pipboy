<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class MapCest
{
    private const array ALIGNMENT = [
        'topLeftLat' => '52.2300000', 'topLeftLng' => '21.0100000',
        'topRightLat' => '52.2300000', 'topRightLng' => '21.0200000',
        'bottomLeftLat' => '52.2250000', 'bottomLeftLng' => '21.0100000',
    ];

    public function anUploadedMapStaysOfflineUntilItIsAligned(FunctionalTester $I): void
    {
        $this->upload($I, 'map.png');
        $I->see('Map uploaded. Drag its corners into place and save the alignment.');
        $I->see('not aligned yet');
        $I->amOnPage('/logout');

        $I->signInAs(TestFixture::CHAT_PLAYERS['alice']['email']);
        $I->amOnPage($this->playerMapUrl('alice'));
        $I->seeResponseCodeIs(200);
        $I->see('Module offline');
        $I->dontSeeElement('#pip-map');
    }

    public function organizersAlignTheMapForPlayersWithTheMapFeature(FunctionalTester $I): void
    {
        $this->upload($I, 'map.png');
        $I->submitForm('[data-map-align-form]', self::ALIGNMENT + ['invertColors' => '1']);
        $I->seeResponseCodeIs(200);
        $I->see('Map alignment saved.');
        $imageUrl = $I->grabAttributeFrom('#map-calibrate', 'data-image-url');
        $I->amOnPage($imageUrl);
        $I->seeResponseCodeIs(200);
        $I->amOnPage('/logout');

        $I->signInAs(TestFixture::CHAT_PLAYERS['alice']['email']);
        $I->amOnPage($this->playerMapUrl('alice'));
        $I->seeResponseCodeIs(200);
        $I->seeElement('#pip-map[data-image-url="'.$imageUrl.'"]');
        $I->seeElement('#pip-map[data-corners*="52.23"]');
        $I->seeElement('filter#pip-map-tint');
        $I->see('never sent to the organizers');
        $I->amOnPage($imageUrl);
        $I->seeResponseCodeIs(200);
        $I->seeResponseHeader('Content-Type', 'image/png');
        $I->seeResponseHeader('X-Content-Type-Options', 'nosniff');
        $I->amOnPage('/logout');

        // Bob is an approved player of the same LARP without the map feature.
        $I->signInAs(TestFixture::CHAT_PLAYERS['bob']['email']);
        $I->amOnPage($this->playerMapUrl('bob'));
        $I->seeResponseCodeIs(404);
        $I->amOnPage($imageUrl);
        $I->seeResponseCodeIs(403);
        $I->amOnPage('/logout');

        // Dave plays another LARP; the pending player has every feature but no approval.
        foreach ([TestFixture::CHAT_PLAYERS['dave']['email'], TestFixture::STAYS_PENDING_EMAIL] as $email) {
            $I->signInAs($email);
            $I->amOnPage($imageUrl);
            $I->seeResponseCodeIs(403);
            $I->amOnPage('/logout');
        }

        $I->amOnPage($imageUrl);
        $I->seeCurrentUrlEquals('/login');
    }

    public function invalidAlignmentsAreRejected(FunctionalTester $I): void
    {
        $this->upload($I, 'map.png');
        $I->submitForm('[data-map-align-form]', [
            'topRightLat' => '52.2300000', 'topRightLng' => '21.0100000',
            'bottomLeftLat' => '52.2300000', 'bottomLeftLng' => '21.0100000',
        ] + self::ALIGNMENT);
        $I->see('The map corners must not lie on one line.');

        $I->submitForm('[data-map-align-form]', ['topLeftLat' => '95'] + self::ALIGNMENT);
        $I->see('Map corners need a latitude between -90 and 90');
    }

    public function onlyImagesAreAccepted(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage($this->adminMapUrl(TestFixture::$larpId));
        $I->attachFile('form[image]', 'not-media.txt');
        $I->click('form[name="form"] button[type="submit"]');
        $I->seeResponseCodeIs(422);
        $I->see('Upload a JPEG, PNG or WebP image.');
    }

    public function onlyOrganizersOfTheLarpManageItsMap(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage($this->adminMapUrl(TestFixture::$otherLarpId));
        $I->seeResponseCodeIs(403);
        $I->amOnPage('/logout');

        $I->signInAs(TestFixture::CHAT_PLAYERS['alice']['email']);
        $I->amOnPage($this->adminMapUrl(TestFixture::$larpId));
        $I->seeResponseCodeIs(403);
        $I->amOnPage('/logout');

        $I->amOnPage($this->adminMapUrl(TestFixture::$larpId));
        $I->seeCurrentUrlEquals('/login');
    }

    private function upload(FunctionalTester $I, string $file): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage($this->adminMapUrl(TestFixture::$larpId));
        $I->attachFile('form[image]', $file);
        $I->click('form[name="form"] button[type="submit"]');
        $I->seeResponseCodeIs(200);
    }

    private function adminMapUrl(int $larpId): string
    {
        return '/admin/larp/'.$larpId.'/map';
    }

    private function playerMapUrl(string $player): string
    {
        return '/pipboy/'.TestFixture::$chatPlayerIds[$player].'/map/local';
    }
}
