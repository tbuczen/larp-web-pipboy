<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\Admin\ScopedCrudController;
use App\Entity\Player;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ScopedCrudControllerTest extends TestCase
{
    public function testAutocompleteCanApplyAssociationScopeToAnAlreadyScopedQuery(): void
    {
        $user = (new User())->setRoles(['ROLE_ORGANIZER']);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = new Container();
        $container->set('security.token_storage', $tokens);
        $controller = new class extends ScopedCrudController {
            public static function getEntityFqcn(): string
            {
                return Player::class;
            }

            public function playerField(): AssociationField
            {
                return $this->scopedAssociationField('player');
            }
        };
        $controller->setContainer($container);
        $scopeQuery = $controller->playerField()->getAsDto()->getCustomOption(AssociationField::OPTION_QUERY_BUILDER_CALLABLE);
        $query = (new QueryBuilder($this->createStub(EntityManagerInterface::class)))
            ->select('entity')->from(Player::class, 'entity');

        $scopeQuery($query);
        $scopedDql = $query->getDQL();
        $scopeQuery($query);

        self::assertSame($scopedDql, $query->getDQL());
        self::assertStringContainsString('scope_organizer = :scope_current_user', $query->getDQL());
        self::assertSame($user, $query->getParameter('scope_current_user')->getValue());
    }
}
