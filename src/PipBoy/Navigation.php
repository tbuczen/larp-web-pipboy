<?php

declare(strict_types=1);

namespace App\PipBoy;

use App\Entity\Player;
use App\Enum\Feature;
use App\Enum\RegistrationStatus;

/**
 * Decides which PipBoy tabs and screens a player may open. Gameplay screens
 * require an approved player and the matching feature grant; until approval
 * only the status screen exists.
 */
final class Navigation
{
    /** @return non-empty-list<Tab> */
    public function tabsFor(Player $player): array
    {
        $status = new Screen('status', 'Status');

        if ($player->getStatus() !== RegistrationStatus::APPROVED) {
            return [new Tab('stat', 'Stat', [$status])];
        }

        $tabs = [new Tab('stat', 'Stat', $this->screens([
            $status,
            $player->canUse(Feature::CONDITIONS) ? new Screen('conditions', 'Conditions') : null,
        ]))];

        $data = $this->screens([
            $player->canUse(Feature::HOLODISKS) ? new Screen('holodisks', 'Holodisks') : null,
            $player->canUse(Feature::NOTES) ? new Screen('notes', 'Notes') : null,
        ]);
        if ($data !== []) {
            $tabs[] = new Tab('data', 'Data', $data);
        }

        if ($player->canUse(Feature::MAP)) {
            $tabs[] = new Tab('map', 'Map', [new Screen('local', 'Local map')]);
        }

        if ($player->canUse(Feature::CHAT)) {
            $tabs[] = new Tab('comms', 'Comms', [new Screen('messages', 'Messages')]);
        }

        return $tabs;
    }

    public function tab(Player $player, string $key): ?Tab
    {
        foreach ($this->tabsFor($player) as $tab) {
            if ($tab->key === $key) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * @param list<Screen|null> $screens
     * @return list<Screen>
     */
    private function screens(array $screens): array
    {
        return array_values(array_filter($screens));
    }
}
