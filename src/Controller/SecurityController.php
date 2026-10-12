<?php

declare(strict_types=1);
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('app_login');
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->isGranted('ROLE_ORGANIZER') ? 'admin' : 'app_pipboy');
        }
        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'discord_enabled' => $this->getParameter('app.discord_enabled'),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('The security firewall handles logout.');
    }
}
