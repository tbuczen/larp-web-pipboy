<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Exception\OAuth2ClientException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class DiscordController extends AbstractController
{
    #[Route('/connect/discord', name: 'connect_discord', methods: ['GET'])]
    public function connect(Request $request, ClientRegistry $clients): Response
    {
        if (!$this->getParameter('app.discord_enabled')) {
            throw $this->createNotFoundException();
        }

        $request->getSession()->remove('registration_discord');

        return $clients->getClient('discord')->redirect(['identify', 'email']);
    }

    #[Route('/connect/discord/check', name: 'connect_discord_check', methods: ['GET'])]
    public function check(Request $request, ClientRegistry $clients, EntityManagerInterface $entityManager, Security $security): Response
    {
        if (!$this->getParameter('app.discord_enabled')) {
            throw $this->createNotFoundException();
        }

        try {
            $profile = $clients->getClient('discord')->fetchUser()->toArray();
        } catch (OAuth2ClientException|IdentityProviderException) {
            $this->addFlash('error', 'Discord sign-in could not be verified. Please try again.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->hasVerifiedIdentity($profile)) {
            $this->addFlash('error', 'Discord must provide a verified email address.');

            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['discordId' => $profile['id']]);
        if ($user !== null) {
            $targetPath = $request->getSession()->remove('_security.main.target_path');
            $security->login($user, 'form_login', 'main');
            if (is_string($targetPath) && preg_match('#^/invite/[a-f0-9]{64}$#D', $targetPath)) {
                return $this->redirect($targetPath);
            }

            return $this->redirectToRoute('app_pipboy');
        }

        $request->getSession()->set('registration_discord', [
            'id' => $profile['id'],
            'email' => $profile['email'],
            'expires' => time() + 600,
        ]);

        return $this->redirectToRoute('app_register');
    }

    private function hasVerifiedIdentity(array $profile): bool
    {
        return is_string($profile['id'] ?? null)
            && preg_match('/^\d{1,30}$/D', $profile['id']) === 1
            && is_string($profile['email'] ?? null)
            && filter_var($profile['email'], FILTER_VALIDATE_EMAIL) !== false
            && ($profile['verified'] ?? false) === true;
    }
}
