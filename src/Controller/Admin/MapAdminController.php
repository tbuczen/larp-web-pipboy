<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Larp;
use App\Entity\LarpMap;
use App\Security\EventVoter;
use App\Service\MapImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Organizers upload the LARP's map image and align it over OpenStreetMap by
 * dragging three of its corners. Players see only the uploaded image.
 */
#[IsGranted('ROLE_ORGANIZER')]
final class MapAdminController extends AbstractController
{
    private const array CORNERS = ['topLeft', 'topRight', 'bottomLeft'];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[AdminRoute('/larp/{id}/map', name: 'larp_map', options: ['requirements' => ['id' => '\d+'], 'methods' => ['GET', 'POST']])]
    public function edit(int $id, Request $request, MapImageStorage $storage): Response
    {
        $larp = $this->managedLarp($id);
        $map = $this->mapOf($larp);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'map_upload_'.$id])
            ->add('image', FileType::class, [
                'label' => $map === null ? 'Map image' : 'Replace map image',
                'help' => 'JPEG, PNG or WebP, up to 15 MB. A replacement keeps the current alignment.',
                'constraints' => [new NotNull(message: 'Choose an image to upload.'), new Image(
                    maxSize: '15M',
                    mimeTypes: array_keys(MapImageStorage::ACCEPTED_MIME_TYPES),
                    mimeTypesMessage: 'Upload a JPEG, PNG or WebP image.',
                    maxWidth: 12000,
                    maxHeight: 12000,
                )],
            ])
            ->getForm()->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $map = $storage->store($larp, $map, $form->get('image')->getData());
            $this->entityManager->persist($map);
            $this->entityManager->flush();
            $this->addFlash('success', $map->isAligned() ? 'Map image replaced.' : 'Map uploaded. Drag its corners into place and save the alignment.');

            return $this->redirectToRoute('admin_larp_map', ['id' => $id]);
        }

        return $this->privateResponse('admin/map.html.twig', ['larp' => $larp, 'map' => $map, 'form' => $form], $form->isSubmitted() ? 422 : 200);
    }

    #[AdminRoute('/larp/{id}/map/align', name: 'larp_map_align', options: ['requirements' => ['id' => '\d+'], 'methods' => ['POST']])]
    public function align(int $id, Request $request): Response
    {
        $larp = $this->managedLarp($id);
        $map = $this->mapOf($larp) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('map_align_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'The form expired. Please try again.');

            return $this->redirectToRoute('admin_larp_map', ['id' => $id]);
        }

        $corners = [];
        foreach (self::CORNERS as $corner) {
            $corners[] = [$request->request->get($corner.'Lat'), $request->request->get($corner.'Lng')];
        }

        try {
            $map->align(...$corners);
            $map->setInvertColors($request->request->getBoolean('invertColors'));
            $this->entityManager->flush();
            $this->addFlash('success', 'Map alignment saved. Players with the map feature can use it now.');
        } catch (DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_larp_map', ['id' => $id]);
    }

    private function managedLarp(int $id): Larp
    {
        $larp = $this->entityManager->find(Larp::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(EventVoter::MANAGE_EVENT, $larp);

        return $larp;
    }

    private function mapOf(Larp $larp): ?LarpMap
    {
        return $this->entityManager->getRepository(LarpMap::class)->findOneBy(['larp' => $larp]);
    }

    /** @param array<string, mixed> $parameters */
    private function privateResponse(string $template, array $parameters, int $status): Response
    {
        return $this->render($template, $parameters, new Response(status: $status, headers: ['Cache-Control' => 'no-store, private']));
    }
}
