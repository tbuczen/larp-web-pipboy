<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Holodisk;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField, TextField, TextareaField};
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

final class HolodiskCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Holodisk::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('larp')->setFormTypeOption('disabled', $pageName === Crud::PAGE_EDIT);
        yield TextField::new('title');
        yield TextareaField::new('content');
    }
}
