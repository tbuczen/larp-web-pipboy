<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\HolodiskAccess;
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, DateTimeField};

final class HolodiskAccessCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return HolodiskAccess::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('player');
        yield $this->scopedAssociationField('holodisk');
        yield DateTimeField::new('grantedAt')->hideOnForm();
        yield BooleanField::new('revoked')->renderAsSwitch(false);
    }
}
