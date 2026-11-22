<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\LarpContext;
use App\Entity\Larp;
use App\Security\EventVoter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Switches the LARP the admin panel works on, then opens the same list for it. */
#[IsGranted('ROLE_ORGANIZER')]
final class LarpContextController extends AbstractController
{
    /** Lists a switch may return to, by short name. */
    private const array LISTS = [
        'player' => PlayerCrudController::class,
        'invitation' => EventInvitationCrudController::class,
        'holodisk' => HolodiskCrudController::class,
        'holodisk-access' => HolodiskAccessCrudController::class,
        'note' => NoteCrudController::class,
        'condition' => ConditionCrudController::class,
    ];

    #[Route('/admin/larp/{id}/manage', name: 'admin_larp_select', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function select(int $id, Request $request, EntityManagerInterface $entityManager, LarpContext $context, AdminUrlGenerator $urls): Response
    {
        $larp = $entityManager->find(Larp::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(EventVoter::MANAGE_EVENT, $larp);
        $context->select($larp);

        $list = (string) $request->query->get('list', 'player');
        if ($list === 'feature-access') {
            return $this->redirectToRoute('admin_feature_access');
        }

        return $this->redirect($urls->unsetAll()->setController(self::LISTS[$list] ?? PlayerCrudController::class)->generateUrl());
    }

    /** Short name of a CRUD list for switch links, so switching keeps the organizer on the same kind of list. */
    public static function listName(?string $crudController): string
    {
        return array_search($crudController, self::LISTS, true) ?: 'player';
    }
}
