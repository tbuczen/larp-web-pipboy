<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'player_condition')]
class Condition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Player $player = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'datetimetz_immutable')]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?DateTimeImmutable $endsAt = null;

    public function __construct()
    {
        $this->startsAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(Player $player): self
    {
        $this->player = $player;

        return $this;
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function setInterval(DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        if ($endsAt !== null && $endsAt < $startsAt) {
            throw new InvalidArgumentException('A condition cannot end before it starts.');
        }

        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isActiveAt(DateTimeImmutable $time): bool
    {
        return $time >= $this->startsAt && ($this->endsAt === null || $time < $this->endsAt);
    }

    #[Assert\Callback]
    public function validateInterval(ExecutionContextInterface $context): void
    {
        if ($this->endsAt !== null && $this->endsAt < $this->startsAt) {
            $context->buildViolation('A condition cannot end before it starts.')
                ->atPath('endsAt')
                ->addViolation();
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
