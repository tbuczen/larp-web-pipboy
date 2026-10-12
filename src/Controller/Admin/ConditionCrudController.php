<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Condition;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, DateTimeField, TextField, TextareaField};

final class ConditionCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Condition::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('player');
        yield TextField::new('name');
        yield TextareaField::new('description');
        yield DateTimeField::new('startsAt')->setFormTypeOption('input', 'datetime_immutable');
        yield DateTimeField::new('endsAt')->setRequired(false)->setFormTypeOption('input', 'datetime_immutable');
    }
}
