<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Dashboard, MenuItem};
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ORGANIZER')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->redirect($this->container->get(AdminUrlGenerator::class)
            ->setController(LarpCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('PipBoy · Organizer');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkTo(LarpCrudController::class, 'LARPs', 'fa fa-calendar');
        yield MenuItem::linkTo(UserCrudController::class, 'Accounts', 'fa fa-user')->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkTo(PlayerCrudController::class, 'Players', 'fa fa-users');
        yield MenuItem::linkTo(EventInvitationCrudController::class, 'Invitations', 'fa fa-envelope');
        yield MenuItem::linkTo(PlayerFeatureAccessCrudController::class, 'Feature access', 'fa fa-key');
        yield MenuItem::linkTo(HolodiskCrudController::class, 'Holodisks', 'fa fa-disc');
        yield MenuItem::linkTo(HolodiskAccessCrudController::class, 'Holodisk access', 'fa fa-unlock');
        yield MenuItem::linkTo(NoteCrudController::class, 'Notes', 'fa fa-note-sticky');
        yield MenuItem::linkTo(ConditionCrudController::class, 'Conditions', 'fa fa-heart');
        yield MenuItem::linkToLogout('Sign out', 'fa fa-sign-out');
    }
}
