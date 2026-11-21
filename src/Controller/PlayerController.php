<?php

declare(strict_types=1);
namespace App\Controller;

use App\Entity\HolodiskAccess;
use App\Entity\Player;
use App\Enum\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlayerController extends AbstractController
{
    #[Route('/pipboy', name: 'app_pipboy', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Organizers can also play: they reach the panel through the Admin tab.
        $players = $entityManager->getRepository(Player::class)->findBy(['user' => $this->getUser()]);

        return $this->render('player/index.html.twig', [
            'players' => $players,
            'holodisks' => $this->unlockedHolodisks($entityManager, $players),
        ]);
    }

    /**
     * Holodisks each player may open, keyed by player id: only for approved
     * players with the holodisk feature, and only active same-event grants.
     *
     * @param list<Player> $players
     * @return array<int, list<\App\Entity\Holodisk>>
     */
    private function unlockedHolodisks(EntityManagerInterface $entityManager, array $players): array
    {
        $eligible = array_values(array_filter($players, static fn (Player $player): bool => $player->canUse(Feature::HOLODISKS)));
        if ($eligible === []) {
            return [];
        }

        $unlocked = [];
        foreach ($entityManager->getRepository(HolodiskAccess::class)->findBy(['player' => $eligible, 'revokedAt' => null]) as $access) {
            if ($access->isActive()) {
                $unlocked[$access->getPlayer()->getId()][] = $access->getHolodisk();
            }
        }

        return $unlocked;
    }
}
