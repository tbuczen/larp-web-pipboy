<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Holodisk;
use App\Entity\EventInvitation;
use App\Entity\HolodiskAccess;
use App\Entity\Larp;
use App\Entity\Player;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class EventMutationPolicy
{
    public function __construct(private readonly EventAccessPolicy $access)
    {
    }

    /** @param array<string, mixed> $original */
    public function assertCanWrite(User $user, object $subject, array $original = [], bool $creating = false): void
    {
        if (!$this->access->canManage($user, $subject)) {
            throw new AccessDeniedException('You can only manage your assigned events.');
        }

        if ($subject instanceof Larp && $creating && !$this->access->isSuperAdmin($user)) {
            throw new AccessDeniedException('Only superadministrators can create events.');
        }

        $this->protectOriginalEvent($user, $subject, $original);

        if ($subject instanceof Player && isset($original['user']) && !EventScope::sameEntity($original['user'], $subject->getUser())) {
            throw new AccessDeniedException('An existing player cannot be moved to another user.');
        }

        if ($subject instanceof HolodiskAccess && !EventScope::sameEntity($subject->getPlayer()?->getLarp(), $subject->getHolodisk()?->getLarp())) {
            throw new AccessDeniedException('A holodisk grant cannot cross event boundaries.');
        }

        if ($subject instanceof Larp && !$this->access->isSuperAdmin($user) && isset($original['organizers'])) {
            $organizers = $original['organizers'];
            $previous = $organizers instanceof PersistentCollection ? $organizers->getSnapshot()
                : ($organizers instanceof Collection ? $organizers->toArray() : $organizers);

            if ($this->organizerKeys($previous) !== $this->organizerKeys($subject->getOrganizers()->toArray())) {
                throw new AccessDeniedException('Only superadministrators can assign event organizers.');
            }
        }
    }

    /** @param array<string, mixed> $original */
    private function protectOriginalEvent(User $user, object $subject, array $original): void
    {
        $previousLarp = $original['larp'] ?? ($original['player'] ?? null)?->getLarp();

        if (!$previousLarp instanceof Larp) {
            return;
        }

        if (!$this->access->canManage($user, $previousLarp)) {
            throw new AccessDeniedException('You cannot change data belonging to another event.');
        }

        if (($subject instanceof Player || $subject instanceof Holodisk || $subject instanceof EventInvitation)
            && !EventScope::sameEntity($previousLarp, EventScope::larpOf($subject))) {
            throw new AccessDeniedException('Existing players, holodisks and invitations cannot be moved between events.');
        }
    }

    /** @param array<User> $organizers
     *  @return list<string>
     */
    private function organizerKeys(array $organizers): array
    {
        $keys = array_map(
            static fn (User $user): string => $user->getId() === null ? 'new:'.spl_object_id($user) : 'id:'.$user->getId(),
            $organizers,
        );
        sort($keys);

        return array_values($keys);
    }
}
