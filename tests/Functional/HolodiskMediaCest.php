<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class HolodiskMediaCest
{
    public function organizersUploadImagesThatOnlyEntitledPlayersCanView(FunctionalTester $I): void
    {
        $this->uploadMedia($I, 'holodisk.png');
        $I->seeElement('.holodisk-media--image img[src="'.$this->mediaUrl().'"]');

        $I->amOnPage($this->mediaUrl());
        $I->seeResponseCodeIs(200);
        $I->seeResponseHeader('Content-Type', 'image/png');
        $I->seeResponseHeader('X-Content-Type-Options', 'nosniff');
        $I->amOnPage('/logout');

        // Approved, holodisk feature, active grant: the player sees the disk and its tinted image.
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage('/pipboy/'.TestFixture::$readerPlayerId.'/data/holodisks');
        $I->click(TestFixture::HOLODISK_TITLE, '.pip-list');
        $I->seeResponseCodeIs(200);
        $I->see('Recovered transmission.');
        $I->seeElement('.pip-detail figure.holodisk-media--image img[src="'.$this->mediaUrl().'"]');
        $I->amOnPage($this->mediaUrl());
        $I->seeResponseCodeIs(200);
    }

    public function playersWithoutAnActiveGrantCannotViewHolodisks(FunctionalTester $I): void
    {
        $this->uploadMedia($I, 'holodisk.png');
        $I->amOnPage('/logout');

        // No holodisk feature and no grant.
        $I->signInAs(TestFixture::PLAYER_EMAIL);
        $I->amOnPage('/holodisk/'.TestFixture::$holodiskId);
        $I->seeResponseCodeIs(403);
        $I->amOnPage($this->mediaUrl());
        $I->seeResponseCodeIs(403);
        $I->amOnPage('/logout');

        // Every feature and a grant, but not yet approved (a player no test approves).
        $I->signInAs(TestFixture::STAYS_PENDING_EMAIL);
        $I->amOnPage($this->mediaUrl());
        $I->seeResponseCodeIs(403);
        $I->amOnPage('/pipboy/'.TestFixture::$staysPendingPlayerId.'/data/holodisks');
        $I->seeResponseCodeIs(404);
    }

    public function anonymousVisitorsCannotFetchMedia(FunctionalTester $I): void
    {
        $I->amOnPage($this->mediaUrl());
        $I->seeCurrentUrlEquals('/login');
    }

    public function onlyImagesAndMp3AreAccepted(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/edit');
        $I->attachFile('Holodisk[mediaFile]', 'not-media.txt');
        $I->click('button.action-saveAndReturn');

        $I->seeResponseCodeIs(422);
        $I->see('Upload a JPEG, PNG, WebP or GIF image, or an MP3 file.');
    }

    public function audioIsPlayableAndMediaCanBeRemoved(FunctionalTester $I): void
    {
        $this->uploadMedia($I, 'holodisk.mp3');
        $I->seeElement('audio.holodisk-media--audio[src="'.$this->mediaUrl().'"]');
        $I->amOnPage($this->mediaUrl());
        $I->seeResponseHeader('Content-Type', 'audio/mpeg');
        $I->seeResponseHeader('Accept-Ranges', 'bytes');

        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/edit');
        $I->checkOption('Holodisk[removeMedia]');
        $I->click('button.action-saveAndReturn');
        $I->amOnPage($this->mediaUrl());
        $I->seeResponseCodeIs(404);
    }

    private function uploadMedia(FunctionalTester $I, string $file): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/edit');
        $I->attachFile('Holodisk[mediaFile]', $file);
        $I->click('button.action-saveAndReturn');
        $I->seeResponseCodeIs(200);
        $I->see(TestFixture::HOLODISK_TITLE, '.datagrid');
    }

    private function mediaUrl(): string
    {
        return '/holodisk/'.TestFixture::$holodiskId.'/media';
    }
}
