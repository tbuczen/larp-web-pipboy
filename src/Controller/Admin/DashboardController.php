<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Admin\LarpContext;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Assets, Dashboard, MenuItem};
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ORGANIZER')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly LarpContext $larpContext,
        private readonly AdminContextProvider $adminContext,
    ) {
    }

    public function index(): Response
    {
        return $this->redirect($this->container->get(AdminUrlGenerator::class)
            ->setController(LarpCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('PipBoy · Organizer')
            ->setFaviconPath('favicon.svg')
            ->setDefaultColorScheme(ColorScheme::DARK);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('css/admin-pipboy.css');
    }

    /**
     * Event data is managed for one LARP at a time: the menu names the current
     * LARP, offers the others to switch to, and lists that LARP's sections.
     */
    public function configureMenuItems(): iterable
    {
        $larp = $this->larpContext->current();
        $available = $this->larpContext->available();

        if ($larp !== null) {
            yield MenuItem::section('LARP: '.$larp->getName());
            if (count($available) > 1) {
                $list = LarpContextController::listName($this->adminContext->getContext()?->getCrud()?->getControllerFqcn());
                yield MenuItem::subMenu('Switch LARP', 'fa fa-shuffle')->setSubItems(array_map(
                    fn ($other) => MenuItem::linkToUrl($other->getName(), $other === $larp ? 'fa fa-check' : 'fa fa-circle', $this->generateUrl('admin_larp_select', ['id' => $other->getId(), 'list' => $list])),
                    $available,
                ));
            }
            yield MenuItem::linkTo(PlayerCrudController::class, 'Players', 'fa fa-users');
            yield MenuItem::linkToRoute('Feature access', 'fa fa-key', 'admin_feature_access');
            yield MenuItem::linkTo(EventInvitationCrudController::class, 'Invitations', 'fa fa-envelope');
            yield MenuItem::linkTo(HolodiskCrudController::class, 'Holodisks', 'fa fa-compact-disc');
            yield MenuItem::linkTo(HolodiskAccessCrudController::class, 'Holodisk access', 'fa fa-unlock');
            yield MenuItem::linkTo(NoteCrudController::class, 'Notes', 'fa fa-note-sticky');
            yield MenuItem::linkTo(ConditionCrudController::class, 'Conditions', 'fa fa-heart');
            yield MenuItem::linkToUrl('Map', 'fa fa-map', $this->generateUrl('admin_larp_map', ['id' => $larp->getId()]));
            yield MenuItem::linkToUrl('Holodisk codes', 'fa fa-qrcode', $this->generateUrl('admin_larp_holodisk_codes', ['id' => $larp->getId()]));
            yield MenuItem::linkToUrl('Invite player', 'fa fa-user-plus', $this->generateUrl('admin_invitation_create', ['id' => $larp->getId()]));
        }

        yield MenuItem::section('All LARPs');
        yield MenuItem::linkTo(LarpCrudController::class, 'LARPs', 'fa fa-calendar');
        yield MenuItem::linkTo(UserCrudController::class, 'Accounts', 'fa fa-user')->setPermission('ROLE_SUPER_ADMIN');
        // A plain link: EasyAdmin's route items wrap the target in the dashboard context.
        yield MenuItem::linkToUrl('Player view', 'fa fa-mobile-screen', $this->generateUrl('app_pipboy'));
        yield MenuItem::linkToLogout('Sign out', 'fa fa-sign-out');
    }
}
