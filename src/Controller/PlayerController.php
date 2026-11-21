<?php

declare(strict_types=1);
namespace App\Controller;

use App\Entity\Player;
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
        return $this->render('player/index.html.twig', [
            'players' => $entityManager->getRepository(Player::class)->findBy(['user' => $this->getUser()]),
        ]);
    }
}
