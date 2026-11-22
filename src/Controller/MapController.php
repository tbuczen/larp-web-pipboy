<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LarpMap;
use App\Security\LarpMapVoter;
use App\Service\MapImageStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MapController extends AbstractController
{
    /** The query string carries the update time only to bust browser caches after a new upload. */
    #[Route('/larp-map/{id}/image', name: 'app_larp_map_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(LarpMapVoter::VIEW, subject: 'map')]
    public function image(LarpMap $map, MapImageStorage $storage): Response
    {
        $path = $storage->pathFor($map) ?? throw $this->createNotFoundException();

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => $map->getImageMimeType()]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'map-'.$map->getId().'.'.pathinfo($path, PATHINFO_EXTENSION));
        // Private caching keeps the map usable on weak field signal without exposing it to shared caches.
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
