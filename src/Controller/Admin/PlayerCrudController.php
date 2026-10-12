<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Player;
use App\Enum\RegistrationStatus;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, ChoiceField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};

final class PlayerCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Player::class;
    }

    public function configureFields(string $pageName): iterable
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            yield AssociationField::new('user')->setFormTypeOption('disabled', $pageName === Crud::PAGE_EDIT);
        } else {
            yield TextField::new('user.email', 'Account')->hideOnForm();
        }
        yield $this->scopedAssociationField('larp')->setFormTypeOption('disabled', $pageName === Crud::PAGE_EDIT);
        yield TextField::new('pipboyNumber', 'PipBoy number');
        yield TextField::new('name', 'Character name');
        yield ChoiceField::new('status')->setChoices(RegistrationStatus::cases());
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN');
    }
}
