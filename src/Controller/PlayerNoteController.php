<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Note;
use App\Entity\Player;
use App\Enum\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Players write, edit and delete their own notes in DATA › Notes. Notes from
 * organizers stay read-only. Every write re-checks ownership, approval and
 * the notes feature, and requires a CSRF token.
 */
#[IsGranted('ROLE_USER')]
final class PlayerNoteController extends AbstractController
{
    /** Keeps one player from filling the database. */
    public const int MAX_NOTES = 200;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/pipboy/{player}/notes', name: 'app_pipboy_note_create', requirements: ['player' => '\d+'], methods: ['POST'])]
    public function create(Player $player, Request $request): Response
    {
        $this->assertNoteTaker($player);
        if (!$this->isCsrfTokenValid('note_new_'.$player->getId(), (string) $request->request->get('_token'))) {
            return $this->expired($player, null, 'new');
        }
        if ($this->entityManager->getRepository(Note::class)->count(['player' => $player, 'writtenByPlayer' => true]) >= self::MAX_NOTES) {
            $this->addFlash('error', 'Memory full: delete a note before adding more.');

            return $this->toNotes($player);
        }

        $note = Note::writtenBy($player);
        if (!$this->fill($note, $request)) {
            return $this->toNotes($player, null, 'new');
        }
        $this->entityManager->persist($note);
        $this->entityManager->flush();
        $this->addFlash('success', 'Note saved.');

        return $this->toNotes($player, $note);
    }

    #[Route('/pipboy/{player}/notes/{note}', name: 'app_pipboy_note_update', requirements: ['player' => '\d+', 'note' => '\d+'], methods: ['POST'])]
    public function update(Player $player, #[MapEntity(id: 'note')] Note $note, Request $request): Response
    {
        $this->assertOwnNote($player, $note);
        if (!$this->isCsrfTokenValid('note_'.$note->getId(), (string) $request->request->get('_token'))) {
            return $this->expired($player, $note, 'edit');
        }

        if ($this->fill($note, $request)) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Note saved.');

            return $this->toNotes($player, $note);
        }

        // Discard the invalid changes; the stored note stays as it was.
        $this->entityManager->refresh($note);

        return $this->toNotes($player, $note, 'edit');
    }

    #[Route('/pipboy/{player}/notes/{note}/delete', name: 'app_pipboy_note_delete', requirements: ['player' => '\d+', 'note' => '\d+'], methods: ['POST'])]
    public function delete(Player $player, #[MapEntity(id: 'note')] Note $note, Request $request): Response
    {
        $this->assertOwnNote($player, $note);
        if (!$this->isCsrfTokenValid('note_delete_'.$note->getId(), (string) $request->request->get('_token'))) {
            return $this->expired($player, $note, null);
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();
        $this->addFlash('success', 'Note deleted.');

        return $this->toNotes($player);
    }

    private function fill(Note $note, Request $request): bool
    {
        $note->setTitle((string) $request->request->get('title'))
            ->setContent(str_replace("\r\n", "\n", (string) $request->request->get('content')));
        $violations = $this->validator->validate($note);
        foreach ($violations as $violation) {
            // Validator messages are already translated; translate the field name too.
            $this->addFlash('error', $this->translator->trans(ucfirst($violation->getPropertyPath())).': '.$violation->getMessage());
        }

        return count($violations) === 0;
    }

    /** Another account's player, or one without approval or the notes feature, is reported as missing. */
    private function assertNoteTaker(Player $player): void
    {
        if ($player->getUser() !== $this->getUser() || !$player->canUse(Feature::NOTES)) {
            throw $this->createNotFoundException();
        }
    }

    private function assertOwnNote(Player $player, Note $note): void
    {
        $this->assertNoteTaker($player);
        if ($note->getPlayer()?->getId() !== $player->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$note->isWrittenByPlayer()) {
            throw $this->createAccessDeniedException('Organizer notes are read-only.');
        }
    }

    private function expired(Player $player, ?Note $note, ?string $mode): Response
    {
        $this->addFlash('error', 'The form expired. Please try again.');

        return $this->toNotes($player, $note, $mode);
    }

    private function toNotes(Player $player, ?Note $note = null, ?string $mode = null): Response
    {
        $parameters = ['player' => $player->getId(), 'tab' => 'data', 'screen' => 'notes', 'item' => $note?->getId()];
        if ($mode !== null) {
            $parameters[$mode] = 1;
        }

        return $this->redirectToRoute('app_pipboy_screen', $parameters);
    }
}
