<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationData
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $lastName = '';

    #[Assert\NotBlank(groups: ['password_account'])]
    #[Assert\Length(min: 12, max: 4096, groups: ['password_account'])]
    public ?string $password = null;
}
