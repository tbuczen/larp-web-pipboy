<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Player;
use App\Enum\Feature;
use App\PipBoy\Navigation;
use App\PipBoy\Tab;
use PHPUnit\Framework\TestCase;

final class PipBoyNavigationTest extends TestCase
{
    public function testPendingPlayersOnlySeeTheirStatusEvenWithFeatures(): void
    {
        $player = (new Player())->grantFeature(Feature::HOLODISKS)->grantFeature(Feature::MAP);

        self::assertSame(['stat' => ['status']], $this->layout((new Navigation())->tabsFor($player)));
    }

    public function testApprovedPlayersSeeOnlyGrantedFeatures(): void
    {
        $player = (new Player())->grantFeature(Feature::HOLODISKS);
        $player->approve();

        self::assertSame(['stat' => ['status'], 'data' => ['holodisks']], $this->layout((new Navigation())->tabsFor($player)));
    }

    public function testEveryFeatureAddsItsScreens(): void
    {
        $player = new Player();
        foreach (Feature::cases() as $feature) {
            $player->grantFeature($feature);
        }
        $player->approve();

        self::assertSame([
            'stat' => ['status', 'conditions'],
            'data' => ['holodisks', 'notes'],
            'map' => ['local'],
            'comms' => ['messages'],
        ], $this->layout((new Navigation())->tabsFor($player)));
    }

    public function testRejectedPlayersLoseGameplayScreens(): void
    {
        $player = (new Player())->grantFeature(Feature::NOTES);
        $player->approve();
        $player->reject();

        self::assertNull((new Navigation())->tab($player, 'data'));
        self::assertSame('status', (new Navigation())->tab($player, 'stat')?->screen(null)?->key);
    }

    public function testUnknownScreensAreNotResolved(): void
    {
        $player = (new Player())->grantFeature(Feature::NOTES);
        $player->approve();
        $data = (new Navigation())->tab($player, 'data');

        self::assertSame('notes', $data?->screen('notes')?->key);
        self::assertNull($data?->screen('holodisks'));
    }

    /**
     * @param list<Tab> $tabs
     * @return array<string, list<string>>
     */
    private function layout(array $tabs): array
    {
        $layout = [];
        foreach ($tabs as $tab) {
            $layout[$tab->key] = array_map(static fn ($screen): string => $screen->key, $tab->screens);
        }

        return $layout;
    }
}
