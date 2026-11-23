<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Entity\Conversation;
use App\Entity\Player;
use App\PipBoy\Navigation;
use App\Service\ChatService;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Chat actions behind COMMS › Messages. Screens are rendered by
 * PipBoyController; these endpoints change state (POST, CSRF-protected,
 * rate-limited) or return new messages for polling. Every action requires
 * the account's own approved player with the chat feature, and a
 * conversation that player may see; anything else is a 404.
 */
#[IsGranted('ROLE_USER')]
#[Route('/pipboy/{player}/comms/messages', requirements: ['player' => '\d+'])]
final class ChatController extends AbstractController
{
    public function __construct(
        private readonly ChatService $chat,
        private readonly Navigation $navigation,
        #[Autowire(service: 'limiter.chat_request')] private readonly RateLimiterFactory $requestLimiter,
        #[Autowire(service: 'limiter.chat_send')] private readonly RateLimiterFactory $sendLimiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/request', name: 'app_chat_request', methods: ['POST'])]
    public function requestChat(Player $player, Request $request): Response
    {
        $this->assertChatPlayer($player, $request, 'chat_request_'.$player->getId());
        if (!$this->requestLimiter->create('player-'.$player->getId())->consume()->isAccepted()) {
            return $this->back($player, null, 'Too many requests. Try again later.');
        }

        try {
            $conversation = $this->chat->requestChat($player, (string) $request->request->get('pipboy'), (string) $request->request->get('message'));
        } catch (DomainException $exception) {
            return $this->back($player, null, $exception->getMessage());
        }

        return $this->back($player, $conversation);
    }

    #[Route('/{conversation}/send', name: 'app_chat_send', requirements: ['conversation' => '\d+'], methods: ['POST'])]
    public function send(Player $player, Conversation $conversation, Request $request): Response
    {
        $this->assertParticipant($player, $conversation, $request);
        $wantsJson = $request->getPreferredFormat() === 'json';

        if (!$this->sendLimiter->create('player-'.$player->getId())->consume()->isAccepted()) {
            return $wantsJson ? new JsonResponse(['error' => $this->translator->trans('Too many messages. Wait a moment.')], Response::HTTP_TOO_MANY_REQUESTS)
                : $this->back($player, $conversation, 'Too many messages. Wait a moment.');
        }

        try {
            $message = $this->chat->send($player, $conversation, (string) $request->request->get('message'));
        } catch (DomainException $exception) {
            return $wantsJson ? new JsonResponse(['error' => $this->translator->trans($exception->getMessage())], Response::HTTP_UNPROCESSABLE_ENTITY)
                : $this->back($player, $conversation, $exception->getMessage());
        }

        return $wantsJson ? new JsonResponse(['messages' => [$this->serialize($message, $player)]]) : $this->back($player, $conversation);
    }

    #[Route('/{conversation}/{answer}', name: 'app_chat_answer', requirements: ['conversation' => '\d+', 'answer' => 'accept|decline|close'], methods: ['POST'])]
    public function answer(Player $player, Conversation $conversation, string $answer, Request $request): Response
    {
        $this->assertParticipant($player, $conversation, $request);

        try {
            match ($answer) {
                'accept' => $this->chat->accept($player, $conversation),
                'decline' => $this->chat->decline($player, $conversation),
                'close' => $this->chat->close($player, $conversation),
            };
        } catch (DomainException $exception) {
            return $this->back($player, $conversation, $exception->getMessage());
        }

        // A declined request disappears for the receiver.
        return $this->back($player, $answer === 'decline' ? null : $conversation);
    }

    #[Route('/{conversation}/reveal', name: 'app_chat_reveal', requirements: ['conversation' => '\d+'], methods: ['POST'])]
    public function reveal(Player $player, Conversation $conversation, Request $request): Response
    {
        $this->assertParticipant($player, $conversation, $request);

        try {
            $this->chat->setRevealsName($player, $conversation, $request->request->getBoolean('reveal'));
        } catch (DomainException $exception) {
            return $this->back($player, $conversation, $exception->getMessage());
        }

        return $this->back($player, $conversation);
    }

    /** New messages after ?after=<id>, for polling. Marks them read. */
    #[Route('/{conversation}/poll', name: 'app_chat_poll', requirements: ['conversation' => '\d+'], methods: ['GET'])]
    public function poll(Player $player, Conversation $conversation, Request $request): JsonResponse
    {
        $this->assertParticipant($player, $conversation);
        $messages = $this->chat->messages($conversation, max(0, $request->query->getInt('after')));
        $this->chat->markRead($player, $conversation, $messages);

        $response = new JsonResponse([
            'status' => $conversation->statusFor($player)->value,
            'canWrite' => $conversation->canWrite($player),
            'label' => $conversation->labelFor($player),
            'messages' => array_map(fn (ChatMessage $message): array => $this->serialize($message, $player), $messages),
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /** @return array{id: int, mine: bool, body: string, sentAt: string, time: string} */
    private function serialize(ChatMessage $message, Player $viewer): array
    {
        return [
            'id' => (int) $message->getId(),
            'mine' => $message->isFrom($viewer),
            'body' => $message->getBody(),
            'sentAt' => $message->getSentAt()->format(DATE_ATOM),
            'time' => $message->getSentAt()->format('H:i'),
        ];
    }

    private function assertChatPlayer(Player $player, ?Request $request = null, ?string $csrfId = null): void
    {
        $tab = $player->getUser() === $this->getUser() ? $this->navigation->tab($player, 'comms') : null;
        if ($tab?->screen('messages') === null) {
            throw $this->createNotFoundException();
        }
        if ($request !== null && $csrfId !== null && !$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function assertParticipant(Player $player, Conversation $conversation, ?Request $request = null): void
    {
        $this->assertChatPlayer($player);
        if (!$conversation->isVisibleTo($player)) {
            throw $this->createNotFoundException();
        }
        if ($request !== null && !$this->isCsrfTokenValid('chat_'.$conversation->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function back(Player $player, ?Conversation $conversation, ?string $error = null): RedirectResponse
    {
        if ($error !== null) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('app_pipboy_screen', array_filter([
            'player' => $player->getId(),
            'tab' => 'comms',
            'screen' => 'messages',
            'item' => $conversation?->getId(),
        ]));
    }
}
