<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

final class EventAccessPolicy
{
    public function canManage(User $user, object $subject): bool
    {
        $larp = EventScope::larpOf($subject);

        if ($larp === null) {
            return false;
        }

        return $this->isSuperAdmin($user)
            || (in_array('ROLE_ORGANIZER', $user->getRoles(), true) && $larp->isOrganizedBy($user));
    }

    public function isSuperAdmin(User $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }
}
