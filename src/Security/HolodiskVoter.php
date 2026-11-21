<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Holodisk;
use App\Entity\HolodiskAccess;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A holodisk can be viewed by organizers who manage its LARP, or by an approved
 * player of that LARP who has the holodisk feature and an active grant for it.
 *
 * @extends Voter<string, Holodisk>
 */
final class HolodiskVoter extends Voter
{
    public const string VIEW = 'VIEW_HOLODISK';

    public function __construct(
        private readonly EventAccessPolicy $policy,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Holodisk;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->policy->canManage($user, $subject)) {
            return true;
        }

        $player = $this->entityManager->getRepository(Player::class)->findOneBy(['user' => $user, 'larp' => $subject->getLarp()]);

        if (!$player instanceof Player || !$player->canUse(Feature::HOLODISKS)) {
            return false;
        }

        $access = $this->entityManager->getRepository(HolodiskAccess::class)->findOneBy(['player' => $player, 'holodisk' => $subject]);

        return $access instanceof HolodiskAccess && $access->isActive();
    }
}
