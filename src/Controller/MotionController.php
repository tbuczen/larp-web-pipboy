<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Player;
use App\Enum\Feature;
use App\Service\MotionDetector;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * JSON endpoints of the motion detector screen. Every call re-checks that the
 * player belongs to the signed-in account, is approved and has the motion
 * feature; writes need the CSRF token from the page (X-CSRF-Token header).
 */
#[IsGranted('ROLE_USER')]
#[Route('/pipboy/{player}/motion', requirements: ['player' => '\d+'])]
final class MotionController extends AbstractController
{
    public function __construct(
        private readonly MotionDetector $detector,
        #[Autowire(service: 'limiter.motion')] private readonly RateLimiterFactory $limiter,
    ) {
    }

    #[Route('/position', name: 'app_motion_position', methods: ['POST'])]
    public function position(Player $player, Request $request): JsonResponse
    {
        if (($denied = $this->guardWrite($player, $request)) !== null) {
            return $denied;
        }
        // JSON from the PipBoy screen; form fields are accepted too.
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : $request->request->all();
        try {
            $this->detector->share($player, $data['latitude'] ?? null, $data['longitude'] ?? null, $data['accuracy'] ?? null);
        } catch (DomainException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        return $this->json(['sharing' => true]);
    }

    #[Route('/stop', name: 'app_motion_stop', methods: ['POST'])]
    public function stop(Player $player, Request $request): JsonResponse
    {
        if (($denied = $this->guardWrite($player, $request)) !== null) {
            return $denied;
        }
        $this->detector->stop($player);

        return $this->json(['sharing' => false]);
    }

    #[Route('/blips', name: 'app_motion_blips', methods: ['GET'])]
    public function blips(Player $player, Request $request): JsonResponse
    {
        $this->assertOwnMotion($player);
        if (!$this->limiter->create('blips-'.$player->getId())->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests.'], 429);
        }
        $response = $this->json([
            'sharing' => $this->detector->isSharing($player),
            'blips' => $this->detector->blips($player, $request->query->getInt('range', 100)),
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function guardWrite(Player $player, Request $request): ?JsonResponse
    {
        $this->assertOwnMotion($player);
        if (!$this->isCsrfTokenValid('motion_'.$player->getId(), (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'The page expired. Reload it.'], 403);
        }
        if (!$this->limiter->create('write-'.$player->getId())->consume()->isAccepted()) {
            return $this->json(['error' => 'Too many requests.'], 429);
        }

        return null;
    }

    /** Another account's player, or one without approval or the feature, is reported as missing. */
    private function assertOwnMotion(Player $player): void
    {
        if ($player->getUser() !== $this->getUser() || !$player->canUse(Feature::MOTION)) {
            throw $this->createNotFoundException();
        }
    }
}
