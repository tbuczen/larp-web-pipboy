<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Note;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, TextField, TextareaField};

final class NoteCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Note::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('player');
        yield TextField::new('title');
        yield TextareaField::new('content');
    }
}
