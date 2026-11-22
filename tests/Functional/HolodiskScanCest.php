<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class HolodiskScanCest
{
    public function playersUnlockAHolodiskOnceFromThePipBoyScanner(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->scanScreen());
        $I->seeResponseCodeIs(200);
        $I->seeElement('[data-scan-form]');
        $I->dontSee(TestFixture::SCAN_HOLODISK_TITLE);

        // Typed codes ignore case, spaces and hyphens.
        $I->submitForm('[data-scan-form]', ['code' => 'scan-test disk1']);
        $I->seeResponseCodeIs(200);
        $I->see('Holodisk downloaded.');
        $I->seeCurrentUrlEquals('/pipboy/'.TestFixture::$readerPlayerId.'/data/holodisks/'.TestFixture::$scanHolodiskId);
        $I->see('Coordinates of the vault door.');

        // A scanned QR holds the link; scanning again changes nothing.
        $I->amOnPage($this->scanScreen());
        $I->submitForm('[data-scan-form]', ['code' => 'https://pipboy.example/scan/'.TestFixture::SCAN_HOLODISK_CODE]);
        $I->see('This holodisk is already in your data.');
    }

    public function phoneCameraLinksConfirmBeforeUnlocking(FunctionalTester $I): void
    {
        // Opening the link first requires signing in, then only shows a confirmation.
        $I->amOnPage('/scan/'.TestFixture::SCAN_HOLODISK_CODE);
        $I->seeCurrentUrlEquals('/login');

        $I->signInAs(TestFixture::CHAT_PLAYERS['carol']['email']);
        $I->amOnPage('/scan/'.TestFixture::SCAN_HOLODISK_CODE);
        $I->see('Holodisk signal detected');
        $I->dontSee(TestFixture::SCAN_HOLODISK_TITLE);
        $I->seeElement('form[action="/scan"] input[name="code"][value="'.TestFixture::SCAN_HOLODISK_CODE.'"]');
    }

    public function ineligibleScansGetTheSameAnswer(FunctionalTester $I): void
    {
        // Bob lacks the holodisk feature, Dave plays another LARP, the pending player is not approved.
        foreach ([TestFixture::CHAT_PLAYERS['bob']['email'], TestFixture::CHAT_PLAYERS['dave']['email'], TestFixture::STAYS_PENDING_EMAIL] as $email) {
            $I->signInAs($email);
            $I->amOnPage('/scan/'.TestFixture::SCAN_HOLODISK_CODE);
            $I->click('Download holodisk');
            $I->see('No readable holodisk data in that code.');
            $I->amOnPage('/logout');
        }

        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->scanScreen());
        $I->submitForm('[data-scan-form]', ['code' => 'NOSUCHCODE99']);
        $I->see('No readable holodisk data in that code.');
        $I->seeCurrentUrlEquals($this->scanScreen());
    }

    public function revokedGrantsStayRevokedWhenScannedAgain(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->scanScreen());
        $I->submitForm('[data-scan-form]', ['code' => TestFixture::SCAN_HOLODISK_CODE]);
        $I->amOnPage('/logout');

        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/holodisk-access');
        $I->click('Edit', '//tr[contains(., "'.TestFixture::SCAN_HOLODISK_TITLE.'")]');
        $I->checkOption('HolodiskAccess[revoked]');
        $I->click('button.action-saveAndReturn');
        $I->amOnPage('/logout');

        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->scanScreen());
        $I->submitForm('[data-scan-form]', ['code' => TestFixture::SCAN_HOLODISK_CODE]);
        $I->see('This holodisk can no longer be read on your PipBoy.');
    }

    public function scansRequireAValidCsrfToken(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::HOLODISK_READER_EMAIL);
        $I->amOnPage($this->scanScreen());
        $I->submitForm('[data-scan-form]', ['code' => TestFixture::SCAN_HOLODISK_CODE, '_token' => 'invalid']);
        $I->see('The scan expired. Please try again.');
    }

    public function organizersPrintAndRegenerateCodes(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/holodisk-codes');
        $I->seeResponseCodeIs(200);
        $I->see('SCAN-TEST-DISK-1');
        $I->seeElement('.qr-card img[src^="data:image/svg+xml;base64,"]');

        $I->amOnPage('/admin/larp/'.TestFixture::$otherLarpId.'/holodisk-codes');
        $I->seeResponseCodeIs(403);

        // Organizers can enter the text of an existing printed code.
        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/edit');
        $I->fillField('Holodisk[code]', 'old-label 0042');
        $I->click('button.action-saveAndReturn');
        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/code');
        $I->see('OLDL-ABEL-0042');

        // Another holodisk's code is refused.
        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/edit');
        $I->fillField('Holodisk[code]', TestFixture::SCAN_HOLODISK_CODE);
        $I->click('button.action-saveAndReturn');
        $I->see('This code is already used by another holodisk.');

        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/edit');
        $I->checkOption('Holodisk[regenerateCode]');
        $I->click('button.action-saveAndReturn');
        $I->amOnPage('/admin/holodisk/'.TestFixture::$holodiskId.'/code');
        $I->dontSee('OLDL-ABEL-0042');
        $I->amOnPage('/logout');

        $I->signInAs(TestFixture::CHAT_PLAYERS['alice']['email']);
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/holodisk-codes');
        $I->seeResponseCodeIs(403);
    }

    private function scanScreen(): string
    {
        return '/pipboy/'.TestFixture::$readerPlayerId.'/data/scan';
    }
}
