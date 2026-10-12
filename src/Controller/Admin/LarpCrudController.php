<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Larp;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions};
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, IdField};

final class LarpCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Larp::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name');
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield AssociationField::new('organizers')->setFormTypeOption('by_reference', false)
                ->setHelp('Assigned accounts also need the Organizer role.');
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(\EasyCorp\Bundle\EasyAdminBundle\Config\Crud::PAGE_INDEX, Action::new('invitePlayer', 'Invite player')->linkToRoute('admin_invitation_create', static fn (Larp $larp): array => ['id' => $larp->getId()]))
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN');
    }
}
