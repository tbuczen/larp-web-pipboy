<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Codeception\Actor;

final class FunctionalTester extends Actor
{
    use _generated\FunctionalTesterActions;

    public function signInAs(string $email = TestFixture::ADMIN_EMAIL): void
    {
        $this->amOnPage('/login');
        $this->submitForm('form', [
            '_username' => $email,
            '_password' => TestFixture::PASSWORD,
        ]);
    }
}
