<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class LanguageAndSettingsCest
{
    public function thePhoneLanguageIsUsedUntilAPlayerChooses(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Accept-Language', 'pl-PL,pl;q=0.9,en;q=0.5');
        $I->amOnPage('/login');
        $I->see('Zaloguj się, aby otworzyć swojego PipBoya.');
        $I->seeElement('html[lang="pl"]');
        $I->deleteHeader('Accept-Language');

        $I->amOnPage('/login');
        $I->see('Sign in to your account to open your PipBoy.');
    }

    public function signedInPlayersChooseALanguageThatFollowsTheirAccount(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage('/pipboy/'.TestFixture::$readerPlayerId.'/data/notes');
        $I->see('New note');
        $I->submitForm('.pip-menu .language-switch', ['locale' => 'pl']);
        $I->seeCurrentUrlEquals('/pipboy/'.TestFixture::$readerPlayerId.'/data/notes');
        $I->see('Nowa notatka');
        $I->see('Notatki', '.pip-subtabs');
        $I->seeElement('.pip-menu a[href="/logout"]');

        // The choice is saved on the account: a fresh sign-in (another device) keeps Polish.
        $I->amOnPage('/logout');
        $I->resetCookie('PHPSESSID');
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage('/pipboy');
        $I->see('Twoje LARP-y');

        // Organizer pages stay English; switching back restores English.
        $I->submitForm('.device-settings .language-switch', ['locale' => 'en']);
        $I->see('Your LARPs');
    }

    public function theAdminPanelStaysEnglish(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/pipboy');
        $I->submitForm('.device-settings .language-switch', ['locale' => 'pl']);
        $I->see('Twoje LARP-y');
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/manage');
        $I->see('Players');
        $I->see('Feature access');
        $I->amOnPage('/pipboy');
        $I->submitForm('.device-settings .language-switch', ['locale' => 'en']);
    }

    public function languageSwitchRejectsForeignRedirectsAndBadTokens(FunctionalTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('.language-switch', ['locale' => 'pl', 'back' => '//evil.example/']);
        $I->seeCurrentUrlEquals('/login');
        $I->see('Zaloguj');
        $I->submitForm('.language-switch', ['locale' => 'en', '_token' => 'invalid']);
        $I->see('Zaloguj');
        $I->submitForm('.language-switch', ['locale' => 'en']);
        $I->see('Sign in to your account');
    }

    public function thePipBoyMenuOffersRotationAndSignOut(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage('/pipboy/'.TestFixture::$readerPlayerId);
        $I->seeElement('.pip-menu button[data-rotate-toggle]');
        $I->seeElement('.pip-menu a[href="/pipboy"]');
        $I->click('Sign out', '.pip-menu');
        $I->amOnPage('/pipboy');
        $I->seeCurrentUrlEquals('/login');
    }
}
