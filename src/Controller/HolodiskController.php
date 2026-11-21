<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Holodisk;
use App\Security\HolodiskVoter;
use App\Service\HolodiskMediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HolodiskController extends AbstractController
{
    #[Route('/holodisk/{id}', name: 'app_holodisk', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HolodiskVoter::VIEW, subject: 'holodisk')]
    public function show(Holodisk $holodisk): Response
    {
        $response = $this->render('holodisk/show.html.twig', ['holodisk' => $holodisk]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/holodisk/{id}/media', name: 'app_holodisk_media', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(HolodiskVoter::VIEW, subject: 'holodisk')]
    public function media(Holodisk $holodisk, HolodiskMediaStorage $storage): Response
    {
        $path = $storage->pathFor($holodisk) ?? throw $this->createNotFoundException();

        // BinaryFileResponse answers Range requests, so audio can be seeked.
        $response = new BinaryFileResponse($path, headers: ['Content-Type' => $holodisk->getMediaMimeType()]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'holodisk-'.$holodisk->getId().'.'.pathinfo($path, PATHINFO_EXTENSION));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
