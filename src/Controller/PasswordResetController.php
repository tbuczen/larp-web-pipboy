<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordResetManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Choosing a new password from an organizer-issued link. Opening the link changes nothing. */
final class PasswordResetController extends AbstractController
{
    #[Route('/reset-password/{token}', name: 'app_password_reset', requirements: ['token' => '[a-zA-Z0-9]{1,128}'], methods: ['GET', 'POST'])]
    public function reset(string $token, Request $request, PasswordResetManager $resets): Response
    {
        $reset = $resets->find($token);
        if ($reset === null) {
            return $this->privateResponse('security/reset_unavailable.html.twig', [], 404);
        }

        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'password_reset'])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'New password', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Repeat the new password', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'The passwords do not match.',
                'constraints' => [new NotBlank(), new Length(min: 12, max: 4096)],
            ])
            ->getForm()->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $resets->reset($reset, $form->get('password')->getData());
            $this->addFlash('success', 'Password changed. Sign in with your new password.');

            return $this->redirectToRoute('app_login');
        }

        return $this->privateResponse('security/reset_password.html.twig', ['form' => $form, 'email' => $reset->getUser()->getEmail()], $form->isSubmitted() ? 422 : 200);
    }

    /** @param array<string, mixed> $parameters */
    private function privateResponse(string $template, array $parameters, int $status): Response
    {
        return $this->render($template, $parameters, new Response(status: $status, headers: [
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]));
    }
}
