<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class EventVoter extends Voter
{
    public const string MANAGE_EVENT = 'MANAGE_EVENT';

    public function __construct(private readonly EventAccessPolicy $policy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE_EVENT;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // EasyAdmin checks the list and creation screens before an entity exists.
        if ($subject === null) {
            return $this->policy->isSuperAdmin($user) || in_array('ROLE_ORGANIZER', $user->getRoles(), true);
        }

        return is_object($subject) && $this->policy->canManage($user, $subject);
    }
}
