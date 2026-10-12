<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\RegistrationData;
use App\Service\AccountRegistration;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\{EmailType, PasswordType, RepeatedType, TextType};
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        AccountRegistration $registration,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactory $limiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_pipboy');
        }

        $discordProfile = $this->getDiscordProfile($request);
        $data = new RegistrationData();
        $data->email = $discordProfile['email'] ?? '';
        $form = $this->createRegistrationForm($data, $discordProfile !== null)->handleRequest($request);
        $status = Response::HTTP_OK;

        if ($form->isSubmitted() && !$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            $form->addError(new FormError('Too many registration attempts. Please try again later.'));
            $status = Response::HTTP_TOO_MANY_REQUESTS;
        } elseif ($form->isSubmitted() && $form->isValid()) {
            try {
                $registration->register($data, $discordProfile['id'] ?? null);
                $request->getSession()->remove('registration_discord');
                $this->addFlash('success', 'Your account is ready. Sign in to continue.');

                return $this->redirectToRoute('app_login');
            } catch (\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('This account already exists. Sign in to continue.'));
            }
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
            'discord_enabled' => $this->getParameter('app.discord_enabled') && $discordProfile === null,
            'discord_verified' => $discordProfile !== null,
        ], new Response(status: $status));
    }

    private function createRegistrationForm(RegistrationData $data, bool $discordVerified): FormInterface
    {
        $builder = $this->createFormBuilder($data, [
            'csrf_token_id' => 'register_account',
            'validation_groups' => $discordVerified ? ['Default'] : ['Default', 'password_account'],
        ]);
        $builder->add('email', EmailType::class, ['disabled' => $discordVerified])
            ->add('firstName', TextType::class, ['label' => 'First name'])
            ->add('lastName', TextType::class, ['label' => 'Last name']);

        if (!$discordVerified) {
            $builder->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password (at least 12 characters)'],
                'second_options' => ['label' => 'Repeat password'],
                'invalid_message' => 'The passwords must match.',
            ]);
        }

        return $builder->getForm();
    }

    private function getDiscordProfile(Request $request): ?array
    {
        $profile = $request->getSession()->get('registration_discord');
        if (!is_array($profile) || ($profile['expires'] ?? 0) <= time()) {
            $request->getSession()->remove('registration_discord');

            return null;
        }

        return $profile;
    }
}
