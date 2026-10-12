<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class RegistrationCest
{
    public function accountsCanRegisterBeforeJoiningAnEvent(FunctionalTester $I): void
    {
        $I->amOnPage('/register');
        $I->submitForm('form[name="form"]', $this->registrationFields(TestFixture::REGISTERED_EMAIL));

        $I->seeResponseCodeIs(200);
        $I->dontSeeElement('input[name="form[eventSecret]"]');

        $I->signInAs(TestFixture::REGISTERED_EMAIL);
        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/pipboy');
        $I->see('You have not joined a LARP.');
        $I->dontSee(TestFixture::LARP_NAME);
    }

    public function existingEmailAddressesCannotRegisterAgain(FunctionalTester $I): void
    {
        $I->amOnPage('/register');
        $I->submitForm('form[name="form"]', $this->registrationFields(TestFixture::PLAYER_EMAIL));

        $I->seeCurrentUrlEquals('/register');
        $I->see('already');
        $I->signInAs(TestFixture::PLAYER_EMAIL);
        $I->seeResponseCodeIs(200);
    }

    public function insufficientPasswordsCannotCreateAccounts(FunctionalTester $I): void
    {
        $I->amOnPage('/register');
        $fields = $this->registrationFields(TestFixture::INVALID_PASSWORD_EMAIL);
        $fields['form[password][first]'] = $fields['form[password][second]'] = 'short';
        $I->submitForm('form[name="form"]', $fields);

        $I->seeCurrentUrlEquals('/register');
        $I->see('12 characters');
        $I->signInAs(TestFixture::INVALID_PASSWORD_EMAIL);
        $I->seeCurrentUrlEquals('/login');
    }

    public function invalidCsrfTokensCannotCreateAccounts(FunctionalTester $I): void
    {
        $I->amOnPage('/register');
        $fields = $this->registrationFields(TestFixture::INVALID_CSRF_EMAIL);
        $fields['form[_token]'] = 'invalid-csrf-token';
        $I->submitForm('form[name="form"]', $fields);

        $I->see('The CSRF token is invalid.');
        $I->signInAs(TestFixture::INVALID_CSRF_EMAIL);
        $I->seeCurrentUrlEquals('/login');
    }

    private function registrationFields(string $email): array
    {
        return [
            'form[email]' => $email,
            'form[firstName]' => 'Test',
            'form[lastName]' => 'Registrant',
            'form[password][first]' => TestFixture::PASSWORD,
            'form[password][second]' => TestFixture::PASSWORD,
        ];
    }
}
