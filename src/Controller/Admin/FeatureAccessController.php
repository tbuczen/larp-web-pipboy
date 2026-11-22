<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\LarpContext;
use App\Entity\Player;
use App\Enum\Feature;
use App\Security\EventVoter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Feature access for every player of the current LARP on one page: a row per
 * player, a checkbox per feature, saved together.
 */
#[IsGranted('ROLE_ORGANIZER')]
final class FeatureAccessController extends AbstractController
{
    public function __construct(
        private readonly LarpContext $larpContext,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[AdminRoute('/feature-access', name: 'feature_access', options: ['methods' => ['GET', 'POST']])]
    public function index(Request $request): Response
    {
        $larp = $this->larpContext->current() ?? throw $this->createNotFoundException('No LARP to manage.');
        $this->denyAccessUnlessGranted(EventVoter::MANAGE_EVENT, $larp);
        $players = $this->entityManager->getRepository(Player::class)->findBy(['larp' => $larp], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('feature_access_'.$larp->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'The form expired. Please try again.');
            } else {
                $this->save($players, $request->request->all('access'));
                $this->addFlash('success', 'Feature access saved.');
            }

            return $this->redirectToRoute('admin_feature_access');
        }

        return $this->render('admin/feature_access.html.twig', [
            'larp' => $larp,
            'players' => $players,
            'features' => Feature::cases(),
        ], new Response(headers: ['Cache-Control' => 'no-store, private']));
    }

    /**
     * Applies the submitted checkboxes. Only players of the current LARP are
     * changed; unknown players or features in the request are ignored.
     *
     * @param list<Player> $players
     * @param array<mixed> $submitted player id => list of feature values
     */
    private function save(array $players, array $submitted): void
    {
        foreach ($players as $player) {
            $values = $submitted[(string) $player->getId()] ?? [];
            $player->setFeatures(array_filter(array_map(
                static fn (mixed $value): ?Feature => is_string($value) ? Feature::tryFrom($value) : null,
                is_array($values) ? $values : [],
            )));
        }

        $this->entityManager->flush();
    }
}
