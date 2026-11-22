<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\HolodiskScanner;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Printed QR codes link to /scan/{code}: opening the link only shows a
 * confirmation, and the unlock itself is a CSRF-protected POST. The PipBoy
 * scanner and manual code entry post to the same endpoint.
 */
#[IsGranted('ROLE_USER')]
final class HolodiskScanController extends AbstractController
{
    #[Route('/scan/{code}', name: 'app_holodisk_scan_link', requirements: ['code' => '[A-Za-z0-9-]{1,100}'], methods: ['GET'])]
    public function link(string $code): Response
    {
        $response = $this->render('holodisk/scan.html.twig', ['code' => $code]);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    #[Route('/scan', name: 'app_holodisk_scan', methods: ['POST'])]
    public function scan(
        Request $request,
        HolodiskScanner $scanner,
        #[Autowire(service: 'limiter.holodisk_scan')] RateLimiterFactory $scanLimiter,
    ): Response {
        $user = $this->getUser();
        $code = (string) $request->request->get('code');
        $back = $this->backUrl($request, $code);

        if (!$user instanceof User || !$this->isCsrfTokenValid('holodisk_scan', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The scan expired. Please try again.');

            return $this->redirect($back);
        }
        if (!$scanLimiter->create('user-'.$user->getId())->consume()->isAccepted()) {
            $this->addFlash('error', 'Scanner overheated. Wait a few minutes before scanning again.');

            return $this->redirect($back);
        }

        try {
            $scan = $scanner->unlock($user, $code);
        } catch (DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirect($back);
        }

        $this->addFlash('success', $scan->newlyUnlocked ? 'Holodisk downloaded.' : 'This holodisk is already in your data.');

        return $this->redirectToRoute('app_pipboy_screen', [
            'player' => $scan->player->getId(),
            'tab' => 'data',
            'screen' => 'holodisks',
            'item' => $scan->holodisk->getId(),
        ]);
    }

    /** Failed scans return to the PipBoy scanner they came from, or to the scan link page. */
    private function backUrl(Request $request, string $code): string
    {
        $player = $request->request->get('player');
        if (is_numeric($player)) {
            return $this->generateUrl('app_pipboy_screen', ['player' => (int) $player, 'tab' => 'data', 'screen' => 'scan']);
        }

        $code = HolodiskScanner::codeFrom($code);

        return preg_match('/^[A-Za-z0-9-]{1,100}$/D', $code) === 1
            ? $this->generateUrl('app_holodisk_scan_link', ['code' => $code])
            : $this->generateUrl('app_pipboy');
    }
}
