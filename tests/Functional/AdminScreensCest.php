<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;

final class AdminScreensCest
{
    public function everyManagementScreenRendersForSuperadmins(FunctionalTester $I): void
    {
        $I->signInAs();
        $I->amOnPage('/admin');
        foreach (['LARPs', 'Accounts', 'Players', 'Invitations', 'Feature access', 'Holodisks', 'Holodisk access', 'Notes', 'Conditions'] as $screen) {
            $I->click($screen);
            $I->seeResponseCodeIs(200);
            $I->seeElement('.datagrid');
        }
    }
}
