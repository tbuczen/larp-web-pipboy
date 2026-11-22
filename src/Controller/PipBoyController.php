<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Condition;
use App\Entity\Conversation;
use App\Entity\Holodisk;
use App\Entity\LarpMap;
use App\Entity\HolodiskAccess;
use App\Entity\Note;
use App\Entity\Player;
use App\Enum\BodyPart;
use App\Enum\Feature;
use App\Enum\RegistrationStatus;
use App\PipBoy\Navigation;
use App\Service\ChatService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The player's PipBoy for one LARP: main tabs, screens and an optional selected
 * entry. Only the account that owns the player may open it, and every screen
 * is resolved through Navigation, which enforces approval and feature grants.
 */
#[IsGranted('ROLE_USER')]
final class PipBoyController extends AbstractController
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatService $chat,
    ) {
    }

    #[Route(
        '/pipboy/{player}/{tab}/{screen}/{item}',
        name: 'app_pipboy_screen',
        requirements: ['player' => '\d+', 'tab' => '[a-z]+', 'screen' => '[a-z]+', 'item' => '\d+'],
        defaults: ['tab' => 'stat', 'screen' => null, 'item' => null],
        methods: ['GET'],
    )]
    public function screen(Player $player, string $tab, ?string $screen, ?int $item, Request $request): Response
    {
        // Another account's player is reported as missing rather than forbidden.
        if ($player->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        $currentTab = $this->navigation->tab($player, $tab) ?? throw $this->createNotFoundException();
        $currentScreen = $currentTab->screen($screen) ?? throw $this->createNotFoundException();
        $entries = $this->entries($player, $currentScreen->key);
        $selected = $item === null ? null : ($entries[$item] ?? throw $this->createNotFoundException());
        $chat = $this->chatContext($player, $currentScreen->key === 'messages' ? $entries : [], $selected);

        $now = new DateTimeImmutable();
        $response = $this->render('pipboy/screen.html.twig', [
            'noteMode' => $currentScreen->key === 'notes' ? $this->noteMode($request, $selected) : null,
            'figure' => $currentScreen->key === 'status' ? $this->figure($player, $now) : null,
            'chat' => $chat,
            'map' => $currentScreen->key === 'local' ? $this->alignedMap($player) : null,
            'player' => $player,
            'tabs' => $this->navigation->tabsFor($player),
            'tab' => $currentTab,
            'screen' => $currentScreen,
            'entries' => $entries,
            'selected' => $selected,
            'now' => $now,
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * The player's own records listed on a screen, keyed by id.
     *
     * @return array<int, Condition|Conversation|Holodisk|Note>
     */
    private function entries(Player $player, string $screen): array
    {
        $records = match ($screen) {
            'conditions' => $this->entityManager->getRepository(Condition::class)->findBy(['player' => $player], ['startsAt' => 'DESC']),
            'notes' => $this->entityManager->getRepository(Note::class)->findBy(['player' => $player], ['title' => 'ASC']),
            'holodisks' => $this->unlockedHolodisks($player),
            'messages' => $this->chat->conversationsFor($player),
            default => [],
        };

        $entries = [];
        foreach ($records as $record) {
            $entries[$record->getId()] = $record;
        }

        return $entries;
    }

    /**
     * Unread counts for the COMMS badge and the conversation list, and the open
     * thread's messages (marked read once shown).
     *
     * @param array<int, object> $conversations conversations listed on this screen, if any
     * @return array{unread: array<int, int>, unreadTotal: int, messages: list<\App\Entity\ChatMessage>}
     */
    private function chatContext(Player $player, array $conversations, ?object $selected): array
    {
        if (!$player->canUse(Feature::CHAT)) {
            return ['unread' => [], 'unreadTotal' => 0, 'messages' => []];
        }

        $unread = $this->chat->unreadCounts($player, $conversations !== [] ? array_values($conversations) : $this->chat->conversationsFor($player));
        $messages = [];
        if ($selected instanceof Conversation) {
            $messages = $this->chat->messages($selected);
            $this->chat->markRead($player, $selected, $messages);
            $unread[(int) $selected->getId()] = 0;
        }

        return ['unread' => $unread, 'unreadTotal' => array_sum($unread), 'messages' => $messages];
    }

    /** 'new' shows the new-note form; 'edit' the edit form of the player's own selected note. */
    private function noteMode(Request $request, ?object $selected): ?string
    {
        return match (true) {
            $request->query->has('new') => 'new',
            $request->query->has('edit') && $selected instanceof Note && $selected->isWrittenByPlayer() => 'edit',
            default => null,
        };
    }

    /**
     * Body parts marked on the STAT figure by currently active conditions.
     * Only players who may see their conditions get them; others see a plain figure.
     *
     * @return array{marks: array<string, list<Condition>>, conditionsVisible: bool}|null
     */
    private function figure(Player $player, DateTimeImmutable $now): ?array
    {
        if ($player->getStatus() !== RegistrationStatus::APPROVED) {
            return null;
        }

        $marks = array_fill_keys(array_map(static fn (BodyPart $part): string => $part->value, BodyPart::cases()), []);
        if (!$player->canUse(Feature::CONDITIONS)) {
            return ['marks' => $marks, 'conditionsVisible' => false];
        }

        foreach ($this->entityManager->getRepository(Condition::class)->findBy(['player' => $player]) as $condition) {
            if ($condition->getBodyPart() !== null && $condition->isActiveAt($now)) {
                $marks[$condition->getBodyPart()->value][] = $condition;
            }
        }

        return ['marks' => $marks, 'conditionsVisible' => true];
    }

    /** The LARP's map once organizers have aligned it; until then the screen reports it offline. */
    private function alignedMap(Player $player): ?LarpMap
    {
        $map = $this->entityManager->getRepository(LarpMap::class)->findOneBy(['larp' => $player->getLarp()]);

        return $map?->isAligned() ? $map : null;
    }

    /** @return list<Holodisk> */
    private function unlockedHolodisks(Player $player): array
    {
        $accesses = $this->entityManager->getRepository(HolodiskAccess::class)->findBy(['player' => $player, 'revokedAt' => null]);
        $holodisks = array_map(
            static fn (HolodiskAccess $access): Holodisk => $access->getHolodisk(),
            array_filter($accesses, static fn (HolodiskAccess $access): bool => $access->isActive()),
        );
        usort($holodisks, static fn (Holodisk $a, Holodisk $b): int => strcasecmp($a->getTitle(), $b->getTitle()));

        return $holodisks;
    }
}
