<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Larp;
use App\Entity\User;
use App\Security\EventAccessPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The LARP the admin panel is currently working on. Event data (players,
 * feature access, invitations, holodisks, notes, conditions) is listed and
 * created for this LARP only. Superadministrators can choose any LARP,
 * organizers only the LARPs assigned to them. The choice lives in the session.
 * Not final so tests can provide a fixed context.
 */
class LarpContext
{
    private const string SESSION_KEY = 'admin_larp_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly EventAccessPolicy $policy,
    ) {
    }

    /** @return list<Larp> LARPs the current account may manage, by name */
    public function available(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $query = $this->entityManager->createQueryBuilder()->select('larp')->from(Larp::class, 'larp')->orderBy('larp.name', 'ASC');
        if (!$this->policy->isSuperAdmin($user)) {
            if (!in_array('ROLE_ORGANIZER', $user->getRoles(), true)) {
                return [];
            }
            $query->innerJoin('larp.organizers', 'organizer')->andWhere('organizer = :user')->setParameter('user', $user);
        }

        return $query->getQuery()->getResult();
    }

    /** The chosen LARP, or the first available one when none was chosen (or the choice is no longer allowed). */
    public function current(): ?Larp
    {
        $available = $this->available();
        $chosen = $this->session()?->get(self::SESSION_KEY);
        foreach ($available as $larp) {
            if ($larp->getId() === $chosen) {
                return $larp;
            }
        }

        $first = $available[0] ?? null;
        if ($first !== null) {
            $this->session()?->set(self::SESSION_KEY, $first->getId());
        }

        return $first;
    }

    public function select(Larp $larp): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->policy->canManage($user, $larp)) {
            throw new AccessDeniedException('This LARP is not assigned to you.');
        }

        $this->session()?->set(self::SESSION_KEY, $larp->getId());
    }

    private function session(): ?\Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }
}
