<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Player;
use App\Entity\User;
use App\Service\PasswordResetManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Organizers create a one-time password reset link for a player and send it
 * themselves (the app sends no email). Superadministrators can do the same
 * for any account. The link is shown once.
 */
#[IsGranted('ROLE_ORGANIZER')]
final class PasswordResetAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordResetManager $resets,
    ) {
    }

    #[AdminRoute('/player/{id}/password-reset', name: 'player_password_reset', options: ['requirements' => ['id' => '\d+'], 'methods' => ['GET', 'POST']])]
    public function forPlayer(int $id, Request $request): Response
    {
        $player = $this->entityManager->find(Player::class, $id) ?? throw $this->createNotFoundException();
        $account = $player->getUser() ?? throw $this->createNotFoundException();

        return $this->handle($request, $account, $player->getName().' ('.$player->getPipboyNumber().')', 'player_reset_'.$id);
    }

    #[AdminRoute('/account/{id}/password-reset', name: 'account_password_reset', options: ['requirements' => ['id' => '\d+'], 'methods' => ['GET', 'POST']])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function forAccount(int $id, Request $request): Response
    {
        $account = $this->entityManager->find(User::class, $id) ?? throw $this->createNotFoundException();

        return $this->handle($request, $account, $account->getEmail(), 'account_reset_'.$id);
    }

    private function handle(Request $request, User $account, string $label, string $csrfId): Response
    {
        $issuer = $this->getUser();
        if (!$issuer instanceof User || !$this->resets->canIssue($issuer, $account)) {
            throw $this->createAccessDeniedException('You cannot reset the password of this account.');
        }

        $link = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'The form expired. Please try again.');
            } else {
                $link = $this->generateUrl('app_password_reset', ['token' => $this->resets->issue($issuer, $account)], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        // The link is a credential: keep it out of caches and referrers.
        return $this->render('admin/password_reset.html.twig', [
            'label' => $label,
            'email' => $account->getEmail(),
            'csrf_id' => $csrfId,
            'link' => $link,
        ], new Response(headers: ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']));
    }
}
