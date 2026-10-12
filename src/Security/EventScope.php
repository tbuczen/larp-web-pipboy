<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Condition;
use App\Entity\EventInvitation;
use App\Entity\Holodisk;
use App\Entity\HolodiskAccess;
use App\Entity\Larp;
use App\Entity\Note;
use App\Entity\Player;
use App\Entity\PlayerFeatureAccess;

final class EventScope
{
    public static function larpOf(object $subject): ?Larp
    {
        return match (true) {
            $subject instanceof Larp => $subject,
            $subject instanceof Player, $subject instanceof Holodisk, $subject instanceof EventInvitation => $subject->getLarp(),
            $subject instanceof PlayerFeatureAccess,
            $subject instanceof HolodiskAccess,
            $subject instanceof Note,
            $subject instanceof Condition => $subject->getPlayer()?->getLarp(),
            default => null,
        };
    }

    public static function sameEntity(?object $first, ?object $second): bool
    {
        if ($first === $second) {
            return true;
        }

        return $first !== null && $second !== null
            && $first->getId() !== null && $first->getId() === $second->getId();
    }
}
