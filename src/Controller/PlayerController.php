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
        if ($this->isGranted('ROLE_ORGANIZER')) {
            return $this->redirectToRoute('admin');
        }

        return $this->render('player/index.html.twig', [
            'players' => $entityManager->getRepository(Player::class)->findBy(['user' => $this->getUser()]),
        ]);
    }
}
