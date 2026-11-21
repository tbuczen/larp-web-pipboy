<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class StaffInvitationCest
{
    public function superAdministratorsCanInviteOrganizers(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ADMIN_EMAIL);
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/invite');
        $I->seeElement('select[name="form[role]"]');
        $I->submitForm('form[name="form"]', [
            'form[email]' => TestFixture::ORGANIZER_INVITED_EMAIL,
            'form[days]' => 1,
            'form[role]' => 'organizer',
        ]);
        $I->see('Invitation created');
        $I->see('Organizer');
        $I->rememberCreatedInvitation('organizer-invite');
        $I->click('Sign out');

        // Before accepting, the account has no access to the panel.
        $I->signInAs(TestFixture::ORGANIZER_INVITED_EMAIL);
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(403);

        $I->openInvitation('organizer-invite');
        $I->see('Organizer invitation');
        $I->dontSeeElement('input[name="form[characterName]"]');
        $I->submitForm('form[name="form"]', []);
        $I->see('Invitation accepted');
        $I->see('You are now an organizer of '.TestFixture::LARP_NAME);

        // New roles take effect at the next sign-in.
        $I->signInAs(TestFixture::ORGANIZER_INVITED_EMAIL);
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(200);
        $I->see(TestFixture::LARP_NAME, '.datagrid');
        $I->dontSee(TestFixture::OTHER_LARP_NAME, '.datagrid');

        // The invitation is single-use.
        $I->openInvitation('organizer-invite');
        $I->see('Invitation unavailable');
    }

    public function organizersCanOnlyInvitePlayers(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/invite');
        $I->dontSeeElement('select[name="form[role]"]');

        // A crafted role field is rejected rather than ignored.
        $I->submitForm('form[name="form"]', [
            'form[email]' => TestFixture::ORGANIZER_INVITED_EMAIL,
            'form[days]' => 1,
            'form[role]' => 'super_admin',
        ]);
        $I->dontSee('Invitation created');
    }
}
