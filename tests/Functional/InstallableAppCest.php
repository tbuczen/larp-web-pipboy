<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class InstallableAppCest
{
    public function theManifestDescribesAnInstallableApp(FunctionalTester $I): void
    {
        $I->amOnPage('/login');
        $I->seeElement('link[rel="manifest"][href="/manifest.webmanifest"]');
        $I->seeElement('meta[name="apple-mobile-web-app-capable"][content="yes"]');

        $I->amOnPage('/manifest.webmanifest');
        $I->seeResponseCodeIs(200);
        $manifest = json_decode($I->grabPageSource(), true, flags: JSON_THROW_ON_ERROR);
        $I->assertSame('standalone', $manifest['display']);
        $I->assertSame('/pipboy?source=homescreen', $manifest['start_url']);
        $I->assertContains('512x512', array_column($manifest['icons'], 'sizes'));
        $I->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            $I->amOnPage($icon['src']);
            $I->seeResponseCodeIs(200);
        }
    }

    public function theServiceWorkerAndOfflineScreenAreServed(FunctionalTester $I): void
    {
        $I->amOnPage('/sw.js');
        $I->seeResponseCodeIs(200);
        $I->seeInSource('pipboy-static-');
        $I->amOnPage('/offline.html');
        $I->seeResponseCodeIs(200);
        $I->see('No signal');
    }

    public function keepMeSignedInSurvivesTheEndOfTheBrowserSession(FunctionalTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            '_username' => TestFixture::HOLODISK_READER_EMAIL,
            '_password' => TestFixture::PASSWORD,
            '_remember_me' => 'on',
        ]);
        $I->seeCookie('REMEMBERME');

        // A closed browser loses the session cookie but keeps the remember-me cookie.
        $I->resetCookie('PHPSESSID');
        $I->amOnPage('/pipboy');
        $I->seeResponseCodeIs(200);
        $I->see('Your LARPs');
        $I->see('Install PipBoy');

        $I->amOnPage('/logout');
        $I->dontSeeCookie('REMEMBERME');
        $I->amOnPage('/pipboy');
        $I->seeCurrentUrlEquals('/login');
    }

    public function withoutKeepMeSignedInTheSessionEndsWithTheBrowser(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->dontSeeCookie('REMEMBERME');
        $I->resetCookie('PHPSESSID');
        $I->amOnPage('/pipboy');
        $I->seeCurrentUrlEquals('/login');
    }
}
