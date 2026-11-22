<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Holodisk;
use App\Entity\Larp;
use App\Security\EventVoter;
use App\Service\HolodiskQrCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Printable QR labels for holodisks: one LARP's whole set, or a single disk. */
#[IsGranted('ROLE_ORGANIZER')]
final class HolodiskCodeAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HolodiskQrCode $qrCode,
    ) {
    }

    #[Route('/admin/larp/{id}/holodisk-codes', name: 'admin_larp_holodisk_codes', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function larp(int $id): Response
    {
        $larp = $this->entityManager->find(Larp::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(EventVoter::MANAGE_EVENT, $larp);
        $holodisks = $this->entityManager->getRepository(Holodisk::class)->findBy(['larp' => $larp], ['title' => 'ASC']);

        return $this->sheet($larp, $holodisks);
    }

    #[Route('/admin/holodisk/{id}/code', name: 'admin_holodisk_code', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function holodisk(int $id): Response
    {
        $holodisk = $this->entityManager->find(Holodisk::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(EventVoter::MANAGE_EVENT, $holodisk);

        return $this->sheet($holodisk->getLarp(), [$holodisk]);
    }

    /** @param list<Holodisk> $holodisks */
    private function sheet(?Larp $larp, array $holodisks): Response
    {
        $cards = array_map(fn (Holodisk $holodisk): array => [
            'holodisk' => $holodisk,
            'qr' => $this->qrCode->dataUri($holodisk),
            'code' => HolodiskQrCode::displayCode($holodisk->getCode()),
        ], $holodisks);

        // The codes are secrets that unlock content: keep them out of caches and referrers.
        return $this->render('admin/holodisk_codes.html.twig', ['larp' => $larp, 'cards' => $cards], new Response(headers: [
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]));
    }
}
