<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EventInvitation;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class GeneratedInvitation
{
    public function __construct(public EventInvitation $invitation, public string $token)
    {
    }
}
