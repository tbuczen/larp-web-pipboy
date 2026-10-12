<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\EventInvitationManager;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class InvitationController extends AbstractController
{
    #[Route('/invite/{token}', name: 'app_invitation', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function accept(
        string $token,
        Request $request,
        EventInvitationManager $invitations,
        #[Autowire(service: 'limiter.invitation_accept')] RateLimiterFactory $limiter,
    ): Response {
        $invitation = $invitations->findByToken($token);

        if ($invitation === null || !$invitation->isAvailableAt(new DateTimeImmutable())) {
            return $this->privateResponse($this->render('invitation/unavailable.html.twig', [], new Response(status: Response::HTTP_GONE)));
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            $request->getSession()->set('_security.main.target_path', $this->generateUrl('app_invitation', ['token' => $token]));

            return $this->privateResponse($this->redirectToRoute('app_login'));
        }

        if ($invitation->getEmail() !== $user->getEmail()) {
            return $this->privateResponse($this->render('invitation/wrong_account.html.twig', [], new Response(status: Response::HTTP_FORBIDDEN)));
        }

        $eventName = $invitation->getLarp()->getName();
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'invitation_'.$invitation->getId()])
            ->add('characterName', TextType::class, ['label' => 'Character name', 'constraints' => [new NotBlank(), new Length(max: 180)]])
            ->getForm()->handleRequest($request);

        if ($form->isSubmitted() && !$limiter->create((string) $user->getId())->consume()->isAccepted()) {
            return $this->privateResponse(new Response('Too many attempts. Please try again later.', Response::HTTP_TOO_MANY_REQUESTS));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $invitations->accept($token, $user, $form->getData()['characterName']);

                return $this->privateResponse($this->render('invitation/success.html.twig', ['event_name' => $eventName]));
            } catch (DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('The invitation could not be accepted. You may already have a player in this LARP.'));
            }
        }

        return $this->privateResponse($this->render('invitation/accept.html.twig', ['form' => $form, 'event_name' => $eventName]));
    }

    private function privateResponse(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
