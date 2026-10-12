<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Condition;
use App\Entity\EventInvitation;
use App\Entity\Holodisk;
use App\Entity\HolodiskAccess;
use App\Entity\Larp;
use App\Entity\Note;
use App\Entity\Player;
use App\Entity\PlayerFeatureAccess;
use App\Entity\User;
use App\Security\EventMutationPolicy;
use App\Security\EventVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Service\Attribute\Required;

abstract class ScopedCrudController extends AbstractCrudController
{
    private EventMutationPolicy $mutationPolicy;

    #[Required]
    public function setEventMutationPolicy(EventMutationPolicy $mutationPolicy): void
    {
        $this->mutationPolicy = $mutationPolicy;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setEntityPermission(EventVoter::MANAGE_EVENT);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return $this->restrictToAssignedEvents(
            parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters),
            static::getEntityFqcn(),
        );
    }

    protected function scopedAssociationField(string $property): AssociationField
    {
        $class = match ($property) {
            'larp' => Larp::class,
            'player' => Player::class,
            'holodisk' => Holodisk::class,
            default => throw new \InvalidArgumentException('Unsupported event association: '.$property),
        };

        return AssociationField::new($property)->setQueryBuilder(
            fn (QueryBuilder $query): QueryBuilder => $this->restrictToAssignedEvents($query, $class),
        );
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->mutationPolicy->assertCanWrite($this->currentUser(), $entityInstance, creating: true);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->mutationPolicy->assertCanWrite(
            $this->currentUser(),
            $entityInstance,
            $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance),
        );
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->mutationPolicy->assertCanWrite($this->currentUser(), $entityInstance);
        parent::deleteEntity($entityManager, $entityInstance);
    }

    /** @param class-string $class */
    private function restrictToAssignedEvents(QueryBuilder $query, string $class): QueryBuilder
    {
        $user = $this->currentUser();

        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return $query;
        }

        if (!in_array('ROLE_ORGANIZER', $user->getRoles(), true)) {
            return $query->andWhere('1 = 0');
        }

        // Autocomplete applies the list scope before the association field scope.
        if (in_array('scope_organizer', $query->getAllAliases(), true)) {
            return $query;
        }

        $root = $query->getRootAliases()[0];
        $larpAlias = match ($class) {
            Larp::class => $root,
            Player::class, Holodisk::class, EventInvitation::class => 'scope_larp',
            PlayerFeatureAccess::class, HolodiskAccess::class, Note::class, Condition::class => 'scope_larp',
            default => throw new \LogicException('The entity has no supported event scope.'),
        };

        if ($class === Player::class || $class === Holodisk::class || $class === EventInvitation::class) {
            $query->innerJoin($root.'.larp', $larpAlias);
        } elseif ($class !== Larp::class) {
            $query->innerJoin($root.'.player', 'scope_player')->innerJoin('scope_player.larp', $larpAlias);
        }

        return $query->innerJoin($larpAlias.'.organizers', 'scope_organizer')
            ->andWhere('scope_organizer = :scope_current_user')
            ->setParameter('scope_current_user', $user);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('An authenticated account is required.');
        }

        return $user;
    }
}
