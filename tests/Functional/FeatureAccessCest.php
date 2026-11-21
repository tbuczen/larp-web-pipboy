<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\FunctionalTester;
use App\Tests\Support\TestFixture;

final class FeatureAccessCest
{
    private const PLAYER_SELECT = 'select[name="PlayerFeatureAccess[player]"]';
    private const FEATURE_SELECT = 'select[name="PlayerFeatureAccess[feature]"]';

    public function newGrantsOfferOnlyMissingFeatures(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/player-feature-access/new?player='.TestFixture::$playerId);

        $I->seeResponseCodeIs(200);
        // The fixture player already has NOTES, so only the other features are offered.
        $I->dontSeeElement(self::FEATURE_SELECT.' option[value="notes"]');
        foreach (['chat', 'map', 'holodisks', 'conditions'] as $feature) {
            $I->seeElement(self::FEATURE_SELECT.' option[value="'.$feature.'"]');
        }
        $I->seeElement(self::FEATURE_SELECT.' option[value="chat"][selected]');
        $I->see('Test player', self::PLAYER_SELECT.' option[selected]');
    }

    public function playersWithEveryFeatureAreNotOffered(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/player-feature-access/new');

        $I->seeResponseCodeIs(200);
        $I->see('Test player', self::PLAYER_SELECT);
        $I->dontSee(TestFixture::PENDING_CHARACTER_NAME, self::PLAYER_SELECT);
    }

    public function playersListLinksToGrantingAFeature(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/player');

        $I->seeElement('a[href*="player-feature-access/new"][href*="player='.TestFixture::$playerId.'"]');
    }

    public function duplicateGrantsAreStillRejected(FunctionalTester $I): void
    {
        $I->signInAs(TestFixture::ORGANIZER_EMAIL);
        $I->amOnPage('/admin/player-feature-access/new');
        $I->submitForm('form[name="PlayerFeatureAccess"]', [
            'PlayerFeatureAccess[player]' => (string) TestFixture::$playerId,
            'PlayerFeatureAccess[feature]' => 'notes',
        ]);

        $I->seeResponseCodeIs(422);
        $I->seeCurrentUrlMatches('~/admin/player-feature-access/new~');
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
}
