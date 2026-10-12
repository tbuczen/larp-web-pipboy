<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class OrganizerScopeCest
{
    public function organizersOnlySeeAssignedEvents(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin');

        $I->seeResponseCodeIs(200);
        $I->see(TestFixture::LARP_NAME, '.datagrid');
        $I->dontSee(TestFixture::OTHER_LARP_NAME, '.datagrid');
        $I->dontSee('Accounts', '.ea-sidebar');
    }

    public function organizersCannotEditUnassignedEvents(FunctionalTester $I): void
    {
        $I->signInAs();
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(200);
        $editUrl = $I->grabAttributeFrom([
            'xpath' => '//tr[.//td[contains(., "'.TestFixture::OTHER_LARP_NAME.'")]]//a[contains(@class, "action-edit")]',
        ], 'href');
        $I->click('Sign out');
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnUrl($editUrl);

        $I->seeResponseCodeIsBetween(403, 404);
        $I->dontSeeElement('input[name="Larp[name]"]');
    }

    public function organizersCannotAccessGlobalAccountManagement(FunctionalTester $I): void
    {
        $I->signInAs();
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(200);
        $accountUrl = $I->grabAttributeFrom([
            'xpath' => '//a[.//span[normalize-space(.)="Accounts"]]',
        ], 'href');
        $I->click('Sign out');
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnUrl($accountUrl);

        $I->seeResponseCodeIsBetween(403, 404);
        $I->dontSeeElement('a.action-new');
    }

    public function assignedOrganizersCanApprovePlayers(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(200);
        $I->click('Players');
        $I->click([
            'xpath' => '//tr[.//td[contains(., "'.TestFixture::PENDING_CHARACTER_NAME.'")]]//a[contains(@class, "action-edit")]',
        ]);
        $I->selectOption('Player[status]', 'approved');
        $I->click('button.action-saveAndReturn');

        $I->seeResponseCodeIs(200);
        $I->click('Sign out');
        $I->signInAs(TestFixture::PENDING_PLAYER_EMAIL);
        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/pipboy');
        $I->see('approved');
        $I->dontSee('Waiting for organizer approval.');
    }

    public function organizersOnlySeeInvitationsForAssignedEvents(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin');
        $I->click('Invitations');

        $I->seeResponseCodeIs(200);
        $I->see(TestFixture::GET_ONLY_EMAIL, '.datagrid');
        $I->dontSee(TestFixture::UNRELATED_INVITATION_EMAIL, '.datagrid');
    }

    public function organizersCannotRevokeOtherEventsInvitations(FunctionalTester $I): void
    {
        $I->signInAs();
        $I->amOnPage('/admin');
        $I->click('Invitations');
        $I->seeResponseCodeIs(200);
        $revokeUrl = $I->grabAttributeFrom([
            'xpath' => '//tr[.//td[contains(., "'.TestFixture::UNRELATED_INVITATION_EMAIL.'")]]//a[contains(@class, "action-revokeInvitation")]',
        ], 'href');
        $I->click('Sign out');
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnUrl($revokeUrl);

        $I->seeResponseCodeIsBetween(403, 404);
        $I->dontSeeElement('form');
    }

    public function organizersCanIssueWorkingPersonalInvitations(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/invite');
        $I->submitForm('form[name="form"]', [
            'form[email]' => TestFixture::ADMIN_INVITED_EMAIL,
            'form[days]' => 1,
        ]);

        $I->seeResponseCodeIs(200);
        $I->see('Invitation created');
        $I->seeElement('#invitation-link[readonly]');
        $I->rememberCreatedInvitation('admin-created');
        $I->click('Back to admin');
        $I->click('Invitations');
        $I->see(TestFixture::ADMIN_INVITED_EMAIL, '.datagrid');
        $I->click('Sign out');

        $I->signInAs(TestFixture::ADMIN_INVITED_EMAIL);
        $I->openInvitation('admin-created');
        $I->submitForm('form[name="form"]', ['form[characterName]' => 'Admin invited character']);
        $I->see('Invitation accepted');
        $I->see('waiting for organizer approval');
    }
}
