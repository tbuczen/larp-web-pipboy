<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class PipBoyCest
{
    public function playersOpenTheirPipBoyFromTheLarpList(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage('/pipboy');
        $I->click('Open PipBoy');

        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals($this->url());
        $I->see('Holodisk reader', 'h1');
        $I->see('TEST-READER', '.pip-status');
        $I->see('Stat', '.pip-tab.is-active');
    }

    public function onlyGrantedModulesAppear(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->url());

        // Holodisks, notes and conditions are granted; map and chat are not.
        $I->seeLink('Data', $this->url('data'));
        $I->seeLink('Conditions', $this->url('stat', 'conditions'));
        $I->dontSeeElement('.pip-tab[href="'.$this->url('map').'"]');
        $I->dontSeeElement('.pip-tab[href="'.$this->url('comms').'"]');
        $I->amOnPage($this->url('map'));
        $I->seeResponseCodeIs(404);
    }

    public function dataScreensListAndOpenEntries(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->url('data', 'holodisks'));
        $I->see('Holodisks', '.pip-subtab.is-active');
        $I->click(TestFixture::HOLODISK_TITLE, '.pip-list');
        $I->seeCurrentUrlEquals($this->url('data', 'holodisks').'/'.TestFixture::$holodiskId);
        $I->see(TestFixture::HOLODISK_TITLE, '.pip-list__item.is-selected');
        $I->see('Recovered transmission.', '.pip-detail');

        $I->click('Notes', '.pip-subtabs');
        $I->click(TestFixture::NOTE_TITLE, '.pip-list');
        $I->see('Meet the courier at dawn.', '.pip-detail');

        $I->amOnPage($this->url('stat', 'conditions'));
        $I->see(TestFixture::CONDITION_NAME, '.pip-list');
        $I->see('Active', '.pip-list');
    }

    public function entriesFromOtherScreensOrPlayersAreNotFound(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        // An entry that is not on this screen.
        $I->amOnPage($this->url('data', 'notes').'/999999');
        $I->seeResponseCodeIs(404);
        // Another player's PipBoy does not exist for this account.
        $I->amOnPage('/pipboy/'.TestFixture::$pendingPlayerId);
        $I->seeResponseCodeIs(404);
    }

    public function organizersCannotOpenAPlayersPipBoy(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage($this->url());
        $I->seeResponseCodeIs(404);
    }

    public function pendingPlayersOnlySeeTheirStatus(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::STAYS_PENDING_EMAIL);
        $I->amOnPage('/pipboy/'.TestFixture::$staysPendingPlayerId);

        $I->seeResponseCodeIs(200);
        $I->see('Waiting for organizer approval.');
        $I->seeNumberOfElements('.pip-tab', 1);
        // Every feature is granted, but nothing works before approval.
        $I->amOnPage('/pipboy/'.TestFixture::$staysPendingPlayerId.'/data/holodisks');
        $I->seeResponseCodeIs(404);
    }

    private function url(?string $tab = null, ?string $screen = null): string
    {
        return '/pipboy/'.TestFixture::$readerPlayerId.($tab ? '/'.$tab : '').($screen ? '/'.$screen : '');
    }
}
