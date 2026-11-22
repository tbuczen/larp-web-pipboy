<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Condition;
use App\Enum\BodyPart;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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
        yield ChoiceField::new('bodyPart', 'Body part')->setRequired(false)
            ->setChoices(array_combine(array_map(static fn (BodyPart $part): string => $part->label(), BodyPart::cases()), BodyPart::cases()))
            ->setHelp('While active, the condition marks this part of the figure on the player\'s STAT screen.');
        yield DateTimeField::new('startsAt')->setFormTypeOption('input', 'datetime_immutable');
        yield DateTimeField::new('endsAt')->setRequired(false)->setFormTypeOption('input', 'datetime_immutable');
    }
}
