<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class AdminScreensCest
{
    public function everyManagementScreenRendersForSuperadmins(FunctionalTester $I): void
    {
        $I->signInAs();
        // Event screens list the chosen LARP; the fixture LARP has data on every screen.
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/manage');
        foreach (['LARPs', 'Accounts', 'Players', 'Invitations', 'Feature access', 'Holodisks', 'Holodisk access', 'Notes', 'Conditions'] as $screen) {
            $I->click($screen);
            $I->seeResponseCodeIs(200);
            $I->seeElement('.datagrid');
        }
    }
}
