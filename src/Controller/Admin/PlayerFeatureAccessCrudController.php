<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\PlayerFeatureAccess;
use App\Enum\Feature;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, ChoiceField};

final class PlayerFeatureAccessCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return PlayerFeatureAccess::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('player');
        yield ChoiceField::new('feature')->setChoices(Feature::cases());
    }
}
