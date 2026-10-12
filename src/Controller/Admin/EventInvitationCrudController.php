<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EventInvitation;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Field\{DateTimeField, EmailField};

final class EventInvitationCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return EventInvitation::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('larp');
        yield EmailField::new('email', 'Invited account');
        yield DateTimeField::new('expiresAt');
        yield DateTimeField::new('acceptedAt');
        yield DateTimeField::new('revokedAt');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::new('revokeInvitation', 'Revoke')
                ->linkToRoute('admin_invitation_revoke', static fn (EventInvitation $invitation): array => ['id' => $invitation->getId()])
                ->displayIf(static fn (EventInvitation $invitation): bool => $invitation->getRevokedAt() === null && $invitation->getAcceptedAt() === null));
    }
}
