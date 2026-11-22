<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class PasswordResetCest
{
    private const string NEW_PASSWORD = 'Brand-new-passphrase-42';

    public function organizersIssueOneTimeLinksThatSetANewPassword(FunctionalTester $I): void
    {
        $link = $this->issueLink($I);

        // Opening the link changes nothing; mismatched or short passwords are refused.
        $I->amOnPage($link);
        $I->see('Choose a new password');
        $I->see(TestFixture::RESET_EMAIL);
        $I->submitForm('form', ['form[password][first]' => 'short', 'form[password][second]' => 'short']);
        $I->seeResponseCodeIs(422);
        $I->submitForm('form', ['form[password][first]' => self::NEW_PASSWORD, 'form[password][second]' => self::NEW_PASSWORD.'x']);
        $I->see('The passwords do not match.');

        $I->submitForm('form', ['form[password][first]' => self::NEW_PASSWORD, 'form[password][second]' => self::NEW_PASSWORD]);
        $I->seeCurrentUrlEquals('/login');
        $I->see('Password changed. Sign in with your new password.');

        // The old password no longer works; the new one does.
        $I->submitForm('form', ['_username' => TestFixture::RESET_EMAIL, '_password' => TestFixture::PASSWORD]);
        $I->seeCurrentUrlEquals('/login');
        $I->submitForm('form', ['_username' => TestFixture::RESET_EMAIL, '_password' => self::NEW_PASSWORD]);
        $I->seeCurrentUrlEquals('/pipboy');
        $I->amOnPage('/logout');

        // A used link cannot be used again.
        $I->amOnPage($link);
        $I->seeResponseCodeIs(404);
        $I->see('Reset link not valid');
    }

    public function aNewLinkCancelsTheOlderOne(FunctionalTester $I): void
    {
        $first = $this->issueLink($I);
        $second = $this->issueLink($I);
        $I->amOnPage($first);
        $I->seeResponseCodeIs(404);
        $I->amOnPage($second);
        $I->seeResponseCodeIs(200);
        $I->amOnPage('/reset-password/'.str_repeat('a', 64));
        $I->seeResponseCodeIs(404);
    }

    public function organizersCannotResetStaffOrOtherLarpsAccounts(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        // Dave plays only in a LARP this organizer does not run.
        $I->amOnPage('/admin/player/'.TestFixture::$chatPlayerIds['dave'].'/password-reset');
        $I->seeResponseCodeIs(403);
        // Superadministrator-only account screen.
        $I->amOnPage('/admin/account/1/password-reset');
        $I->seeResponseCodeIs(403);
        $I->amOnPage('/logout');

        // Players cannot issue links at all.
        $I->signInAs(TestFixture::PLAYER_EMAIL);
        $I->amOnPage('/admin/player/'.TestFixture::$resetPlayerId.'/password-reset');
        $I->seeResponseCodeIs(403);
    }

    public function theSignInPageExplainsHowToGetALink(FunctionalTester $I): void
    {
        $I->amOnPage('/login');
        $I->see('Forgot your password? Ask an organizer of your LARP for a reset link.');
    }

    private function issueLink(FunctionalTester $I): string
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/player/'.TestFixture::$resetPlayerId.'/password-reset');
        $I->seeResponseCodeIs(200);
        $I->click('Create reset link');
        $I->see('The link is shown only now.');
        $link = (string) parse_url($I->grabValueFrom('#reset-link'), PHP_URL_PATH);
        $I->amOnPage('/logout');

        return $link;
    }
}
