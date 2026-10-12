<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class InvitationCest
{
    public function openingAnInvitationDoesNotConsumeIt(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::GET_ONLY_EMAIL);
        $I->openInvitation('get-only');
        $I->seeResponseCodeIs(200);
        $I->seeElement('input[name="form[characterName]"]');
        $I->amOnPage('/pipboy');
        $I->see('You have not joined a LARP.');

        $I->openInvitation('get-only');
        $I->seeResponseCodeIs(200);
        $I->seeElement('input[name="form[characterName]"]');
    }

    public function matchingAccountsCanJoinOnceAndWaitForApproval(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::INVITED_EMAIL);
        $I->openInvitation('valid');
        $I->submitForm('form[name="form"]', ['form[characterName]' => 'Invited test character']);

        $I->seeResponseCodeIs(200);
        $I->see('Invitation accepted');
        $I->see('waiting for organizer approval');
        $I->amOnPage('/pipboy');
        $I->see('Invited test character');
        $I->see('Waiting for organizer approval.');

        $this->assertUnavailable($I, 'valid');
    }

    public function invitationsCannotBeAcceptedByUnrelatedAccounts(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::PLAYER_EMAIL);
        $I->openInvitation('wrong-email');

        $I->seeResponseCodeIs(403);
        $I->see('Sign in with the account this invitation was issued to.');
        $I->dontSeeElement('input[name="form[characterName]"]');
        $I->dontSee(TestFixture::LARP_NAME);
    }

    public function expiredInvitationsAreUnavailable(FunctionalTester $I): void
    {
        $this->assertUnavailable($I, 'expired');
    }

    public function revokedInvitationsAreUnavailable(FunctionalTester $I): void
    {
        $this->assertUnavailable($I, 'revoked');
    }

    public function previouslyAcceptedInvitationsAreUnavailable(FunctionalTester $I): void
    {
        $this->assertUnavailable($I, 'used');
    }

    public function invalidCsrfTokensCannotConsumeInvitations(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::GET_ONLY_EMAIL);
        $I->openInvitation('get-only');
        $I->submitForm('form[name="form"]', [
            'form[characterName]' => 'CSRF rejected character',
            'form[_token]' => 'invalid-csrf-token',
        ]);

        $I->see('The CSRF token is invalid.');
        $I->amOnPage('/pipboy');
        $I->see('You have not joined a LARP.');
        $I->openInvitation('get-only');
        $I->seeResponseCodeIs(200);
        $I->seeElement('input[name="form[characterName]"]');
    }

    private function assertUnavailable(FunctionalTester $I, string $fixtureName): void
    {
        $I->openInvitation($fixtureName);
        $I->seeResponseCodeIs(410);
        $I->see('Invitation unavailable');
        $I->dontSeeElement('input[name="form[characterName]"]');
    }
}
