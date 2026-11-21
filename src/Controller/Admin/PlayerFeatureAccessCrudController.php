<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\Player;
use App\Entity\PlayerFeatureAccess;
use App\Enum\Feature;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Assets, Crud};
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

final class PlayerFeatureAccessCrudController extends ScopedCrudController
{
    /** Query parameter that preselects a player on the "new" page. */
    public const PLAYER_PARAMETER = 'player';

    private ?Player $requestedPlayer = null;
    /** @var list<Feature> captured before the new grant joins the player's collection */
    private array $requestedPlayerMissingFeatures = [];
    private bool $requestedPlayerResolved = false;

    public static function getEntityFqcn(): string
    {
        return PlayerFeatureAccess::class;
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName !== Crud::PAGE_NEW) {
            yield $this->scopedAssociationField('player');
            yield ChoiceField::new('feature')->setChoices(Feature::cases());

            return;
        }

        // Offer only grants that do not exist yet: players still missing a
        // feature, and for a chosen player only the features they lack.
        yield $this->scopedAssociationField('player', $this->excludePlayersWithEveryFeature(...))
            // Without a placeholder the first player looks selected while the
            // feature list is not yet filtered for anyone.
            ->setFormTypeOption('placeholder', 'Choose a player')
            ->setHelp('Players who already have every feature are not listed.');
        $this->requestedPlayer();
        yield ChoiceField::new('feature')->setChoices($this->requestedPlayerMissingFeatures ?: Feature::cases());
    }

    public function createEntity(string $entityFqcn): PlayerFeatureAccess
    {
        $access = new PlayerFeatureAccess();
        $player = $this->requestedPlayer();

        // setPlayer() adds the unsaved grant to the player's features, so use
        // the list captured when the player was resolved.
        if ($player !== null && $this->requestedPlayerMissingFeatures !== []) {
            $access->setPlayer($player)->setFeature($this->requestedPlayerMissingFeatures[0]);
        }

        return $access;
    }

    public function configureAssets(Assets $assets): Assets
    {
        // Reloads the "new" form for the chosen player so the feature list matches.
        return parent::configureAssets($assets)->addJsFile('js/admin-feature-access.js');
    }

    private function excludePlayersWithEveryFeature(QueryBuilder $query): QueryBuilder
    {
        $player = $query->getRootAliases()[0];

        return $query
            ->andWhere(sprintf('(SELECT COUNT(granted.id) FROM %s granted WHERE granted.player = %s) < :feature_count', PlayerFeatureAccess::class, $player))
            ->setParameter('feature_count', count(Feature::cases()));
    }

    /** The player named in the query string, if it belongs to an event the current user manages. */
    private function requestedPlayer(): ?Player
    {
        if (!$this->requestedPlayerResolved) {
            $this->requestedPlayerResolved = true;
            $id = $this->getContext()?->getRequest()->query->getInt(self::PLAYER_PARAMETER) ?? 0;

            if ($id > 0) {
                $query = $this->container->get('doctrine')->getManager()->createQueryBuilder()
                    ->select('player')->from(Player::class, 'player')
                    ->andWhere('player.id = :requested_player')->setParameter('requested_player', $id);
                $this->requestedPlayer = $this->restrictToAssignedEvents($query, Player::class)->getQuery()->getOneOrNullResult();
                $this->requestedPlayerMissingFeatures = $this->requestedPlayer?->missingFeatures() ?? [];
            }
        }

        return $this->requestedPlayer;
    }
}
