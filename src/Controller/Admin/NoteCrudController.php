<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Note;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\{FieldCollection, FilterCollection};
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto, SearchDto};
use EasyCorp\Bundle\EasyAdminBundle\Field\{TextField, TextareaField};

/**
 * Organizer-written notes only. Notes players write for themselves are private
 * and never listed, shown or editable here (EventAccessPolicy refuses them too).
 */
final class NoteCrudController extends ScopedCrudController
{
    public static function getEntityFqcn(): string
    {
        return Note::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $query->andWhere($query->getRootAliases()[0].'.writtenByPlayer = false');
    }

    public function configureFields(string $pageName): iterable
    {
        yield $this->scopedAssociationField('player');
        yield TextField::new('title');
        yield TextareaField::new('content')
            ->setHelp('Shown in the player\'s DATA › Notes, read-only for them. Notes players write themselves are private and not listed here.');
    }
}
