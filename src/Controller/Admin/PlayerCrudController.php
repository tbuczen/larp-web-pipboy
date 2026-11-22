<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Player;
use App\Enum\Feature;
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
        yield ChoiceField::new('features', 'Features')
            ->setChoices(array_combine(array_map(static fn (Feature $feature): string => $feature->label(), Feature::cases()), Feature::cases()))
            ->allowMultipleChoices()->renderExpanded()
            ->setFormTypeOption('by_reference', false)
            ->setHelp('PipBoy modules this player can use once approved. For many players at once use Feature access.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, Action::new('passwordReset', 'Password reset link', 'fa fa-key')
                ->linkToRoute('admin_player_password_reset', static fn (Player $player): array => ['id' => $player->getId()]))
            ->add(Crud::PAGE_INDEX, Action::new('featureAccess', 'Feature access', 'fa fa-key')->linkToRoute('admin_feature_access')->createAsGlobalAction())
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN');
    }
}
