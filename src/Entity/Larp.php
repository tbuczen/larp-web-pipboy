<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Larp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'larp_organizer')]
    private Collection $organizers;

    public function __construct()
    {
        $this->organizers = new ArrayCollection();
    }

    /** @return Collection<int, User> */
    public function getOrganizers(): Collection
    {
        return $this->organizers;
    }

    public function addOrganizer(User $user): self
    {
        if (!$this->isOrganizedBy($user)) {
            $this->organizers->add($user);
        }

        return $this;
    }

    public function removeOrganizer(User $user): self
    {
        foreach ($this->organizers as $organizer) {
            if ($organizer === $user || ($user->getId() !== null && $user->getId() === $organizer->getId())) {
                $this->organizers->removeElement($organizer);
            }
        }

        return $this;
    }

    public function isOrganizedBy(User $user): bool
    {
        return $this->organizers->exists(
            static fn (int $key, User $organizer): bool => $organizer === $user
                || ($user->getId() !== null && $user->getId() === $organizer->getId()),
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

}
