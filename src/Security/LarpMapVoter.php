<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\LarpMap;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A LARP map can be viewed by organizers who manage the LARP, or by an approved
 * player of that LARP who has the map feature.
 *
 * @extends Voter<string, LarpMap>
 */
final class LarpMapVoter extends Voter
{
    public const string VIEW = 'VIEW_LARP_MAP';

    public function __construct(
        private readonly EventAccessPolicy $policy,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof LarpMap;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->policy->canManage($user, $subject->getLarp())) {
            return true;
        }

        $player = $this->entityManager->getRepository(Player::class)->findOneBy(['user' => $user, 'larp' => $subject->getLarp()]);

        return $player instanceof Player && $player->canUse(Feature::MAP);
    }
}
