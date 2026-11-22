<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class FeatureAccessCest
{
    public function organizersTickFeaturesForEveryPlayerOnOnePage(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/manage');
        $I->amOnPage('/admin/feature-access');
        $I->seeResponseCodeIs(200);
        $I->see('Feature access · '.TestFixture::LARP_NAME);
        // The fixture player has Notes only; the other LARP's players are not listed.
        $I->seeCheckboxIsChecked($this->box(TestFixture::$playerId, 'notes'));
        $I->dontSeeCheckboxIsChecked($this->box(TestFixture::$playerId, 'map'));
        $I->dontSee(TestFixture::CHAT_PLAYERS['dave']['name']);

        $form = $I->grabMultiple('input[type="checkbox"][checked]', 'name');
        $fields = ['_token' => $I->grabValueFrom('.feature-access-form input[name="_token"]')];
        // Keep everyone's current grants, then give the fixture player Map and remove Notes.
        foreach ($I->grabMultiple('input[type="checkbox"][checked]', 'value') as $index => $value) {
            $fields[$form[$index]][] = $value;
        }
        $fields['access['.TestFixture::$playerId.'][]'] = ['map'];
        $I->submitForm('.feature-access-form', $this->nested($fields));
        $I->see('Feature access saved.');
        $I->seeCheckboxIsChecked($this->box(TestFixture::$playerId, 'map'));
        $I->dontSeeCheckboxIsChecked($this->box(TestFixture::$playerId, 'notes'));

        // Restore the fixture player's original grant through the player form checkboxes.
        $I->amOnPage('/admin/player/'.TestFixture::$playerId.'/edit');
        $I->seeCheckboxIsChecked('#Player_features_2');
        $I->checkOption('#Player_features_0');
        $I->uncheckOption('#Player_features_2');
        $I->click('button.action-saveAndReturn');
        $I->amOnPage('/admin/feature-access');
        $I->seeCheckboxIsChecked($this->box(TestFixture::$playerId, 'notes'));
        $I->dontSeeCheckboxIsChecked($this->box(TestFixture::$playerId, 'map'));
    }

    public function organizersCannotManageAnotherLarp(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/larp/'.TestFixture::$otherLarpId.'/manage');
        $I->seeResponseCodeIs(403);
    }

    public function listsShowOnlyTheChosenLarp(FunctionalTester $I): void
    {
        // A superadministrator sees every LARP but manages one at a time.
        $I->signInAs(TestFixture::ADMIN_EMAIL);
        $I->amOnPage('/admin/larp/'.TestFixture::$otherLarpId.'/manage');
        $I->see('LARP: '.TestFixture::OTHER_LARP_NAME);
        $I->see(TestFixture::CHAT_PLAYERS['dave']['name'], '.datagrid');
        $I->dontSee(TestFixture::CHAT_PLAYERS['alice']['name'], '.datagrid');

        $I->amOnPage('/admin/larp/'.TestFixture::$larpId.'/manage?list=holodisk');
        $I->see('LARP: '.TestFixture::LARP_NAME);
        $I->see(TestFixture::HOLODISK_TITLE, '.datagrid');
        $I->amOnPage('/admin/larp/'.TestFixture::$otherLarpId.'/manage?list=holodisk');
        $I->dontSee(TestFixture::HOLODISK_TITLE, '.datagrid');

        // New holodisks are created in the chosen LARP only.
        $I->amOnPage('/admin/holodisk/new');
        $I->seeOptionIsSelected('Holodisk[larp]', TestFixture::OTHER_LARP_NAME);
        $I->dontSeeElement('select[name="Holodisk[larp]"] option[value="'.TestFixture::$larpId.'"]');
    }

    public function oneAccountPlaysSeveralLarpsWithSeparateCharacters(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ADMIN_EMAIL);
        $I->amOnPage('/admin/user');
        $I->see(TestFixture::LARP_NAME.' (CHAT-ALICE), '.TestFixture::OTHER_LARP_NAME.' ('.TestFixture::ALICE_ELSEWHERE_NUMBER.')');
        $I->amOnPage('/logout');

        // Alice sees both memberships; each opens its own PipBoy with its own features.
        $I->signInAs(TestFixture::CHAT_PLAYERS['alice']['email']);
        $I->amOnPage('/pipboy');
        $I->see(TestFixture::LARP_NAME);
        $I->see(TestFixture::OTHER_LARP_NAME);
        $I->see(TestFixture::ALICE_ELSEWHERE_NAME);
        $I->amOnPage('/pipboy/'.TestFixture::$chatPlayerIds['alice'].'/map');
        $I->seeResponseCodeIs(200);
        $I->amOnPage('/pipboy/'.TestFixture::$aliceElsewherePlayerId.'/map');
        $I->seeResponseCodeIs(404);
    }

    public function organizersCanOpenThePlayerView(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/pipboy');

        $I->seeResponseCodeIs(200);
        $I->seeCurrentUrlEquals('/pipboy');
        $I->seeLink('Admin', '/admin');
        $I->amOnPage('/admin');
        $I->seeLink('Player view', '/pipboy');
    }

    private function box(int $playerId, string $feature): string
    {
        return 'input[name="access['.$playerId.'][]"][value="'.$feature.'"]';
    }

    /**
     * @param array<string, mixed> $fields flat "access[id][]" names
     * @return array<string, mixed>
     */
    private function nested(array $fields): array
    {
        $result = ['_token' => $fields['_token'], 'access' => []];
        foreach ($fields as $name => $values) {
            if (preg_match('/^access\[(\d+)\]\[\]$/', (string) $name, $match) === 1) {
                $result['access'][$match[1]] = array_values((array) $values);
            }
        }

        return $result;
    }
}
