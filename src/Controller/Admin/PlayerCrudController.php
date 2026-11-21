<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Player;
use App\Enum\RegistrationStatus;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, ChoiceField, TextField};
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

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
        $grantFeature = Action::new('grantFeature', 'Grant feature')
            ->linkToUrl(fn (Player $player): string => $this->container->get(AdminUrlGenerator::class)
                ->unsetAll()
                ->setController(PlayerFeatureAccessCrudController::class)
                ->setAction(Action::NEW)
                ->set(PlayerFeatureAccessCrudController::PLAYER_PARAMETER, $player->getId())
                ->generateUrl())
            ->displayIf(static fn (Player $player): bool => $player->missingFeatures() !== []);

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $grantFeature)
            ->setPermission(Action::NEW, 'ROLE_SUPER_ADMIN');
    }
}
