<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Note
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
    private string $title = '';

    #[ORM\Column(type: 'text')]
    #[Assert\Length(max: 10000)]
    private string $content = '';

    /**
     * Notes players write for themselves are private: organizers never see
     * them in admin. Notes written by organizers are read-only for players.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $writtenByPlayer = false;

    public static function writtenBy(Player $player): self
    {
        $note = (new self())->setPlayer($player);
        $note->writtenByPlayer = true;

        return $note;
    }

    public function isWrittenByPlayer(): bool
    {
        return $this->writtenByPlayer;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
