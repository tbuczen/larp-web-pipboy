<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class AdminCest
{
    public function anonymousVisitorsMustSignIn(FunctionalTester $I): void
    {
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/login');
        $I->seeElement('input[name="_csrf_token"]');
    }

    public function incorrectPasswordsDoNotAuthenticate(FunctionalTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            '_username' => TestFixture::ADMIN_EMAIL,
            '_password' => 'IncorrectPassword',
        ]);

        $I->seeCurrentUrlEquals('/login');
        $I->see('Invalid credentials.', '[role="alert"]');
        $I->amOnPage('/admin');
        $I->seeCurrentUrlEquals('/login');
    }

    public function invalidLoginCsrfTokensDoNotAuthenticate(FunctionalTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            '_username' => TestFixture::ADMIN_EMAIL,
            '_password' => TestFixture::PASSWORD,
            '_csrf_token' => 'invalid-csrf-token',
        ]);

        $I->seeCurrentUrlEquals('/login');
        $I->see('Invalid CSRF token.', '[role="alert"]');
        $I->amOnPage('/admin');
        $I->seeCurrentUrlEquals('/login');
    }

    public function playersCannotAccessTheAdminPanel(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::PLAYER_EMAIL);

        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/pipboy');
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(403);
    }

    public function administratorsCanSignIn(FunctionalTester $I): void
    {
        $I->signInAs();
        $I->amOnPage('/admin');

        $I->seeResponseCodeIs(200);
        $I->see('PipBoy', '.ea-sidebar');
        $I->see(TestFixture::LARP_NAME, '.datagrid');
    }

    public function administratorsCanCreateAndEditAnEvent(FunctionalTester $I): void
    {
        $I->signInAs();
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(200);
        $I->click('LARPs');
        $I->click('a.action-new');
        $I->fillField('Larp[name]', TestFixture::CREATED_LARP_NAME);
        $I->click('button.action-saveAndReturn');

        $I->seeResponseCodeIs(200);
        $I->see(TestFixture::CREATED_LARP_NAME, '.datagrid');

        $I->click(['xpath' => '//tr[.//td[contains(., "'.TestFixture::CREATED_LARP_NAME.'")]]//a[contains(@class, "action-edit")]']);
        $I->fillField('Larp[name]', TestFixture::EDITED_LARP_NAME);
        $I->click('button.action-saveAndReturn');

        $I->seeResponseCodeIs(200);
        $I->click('LARPs');
        $I->see(TestFixture::EDITED_LARP_NAME, '.datagrid');
    }

    public function invalidAdminCsrfTokensDoNotCreateEvents(FunctionalTester $I): void
    {
        $I->signInAs();
        $I->amOnPage('/admin');
        $I->seeResponseCodeIs(200);
        $I->click('LARPs');
        $I->click('a.action-new');
        $I->submitForm('form[name="Larp"]', [
            'Larp[name]' => TestFixture::CSRF_LARP_NAME,
            'Larp[_token]' => 'invalid-csrf-token',
        ]);

        $I->see('The CSRF token is invalid.');
        $I->click('LARPs');
        $I->dontSee(TestFixture::CSRF_LARP_NAME, '.datagrid');
    }

}
