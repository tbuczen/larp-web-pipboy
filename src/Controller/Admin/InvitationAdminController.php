<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\{EventInvitation, Larp};
use App\Security\EventVoter;
use App\Service\EventInvitationManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\{EmailType, IntegerType};
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\{Email, Length, NotBlank, Range};

#[IsGranted('ROLE_ORGANIZER')]
final class InvitationAdminController extends AbstractController
{
    #[Route('/admin/larp/{id}/invite', name: 'admin_invitation_create', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function create(int $id, Request $request, EntityManagerInterface $entityManager, EventInvitationManager $manager): Response
    {
        $larp = $entityManager->find(Larp::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(EventVoter::MANAGE_EVENT, $larp);
        $form = $this->createFormBuilder(['days' => 7], ['csrf_token_id' => 'invite_'.$id])
            ->add('email', EmailType::class, ['label' => 'Player account email', 'constraints' => [new NotBlank(), new Email(), new Length(max: 180)]])
            ->add('days', IntegerType::class, ['label' => 'Expires in days', 'constraints' => [new NotBlank(), new Range(min: 1, max: 30)]])
            ->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $details = $form->getData();
            $generated = $manager->createInvite($larp, $details['email'], new DateTimeImmutable('+'.$details['days'].' days'));
            $link = $this->generateUrl('app_invitation', ['token' => $generated->token], UrlGeneratorInterface::ABSOLUTE_URL);
            return $this->privateResponse('invitation/created.html.twig', ['link' => $link, 'invitation' => $generated->invitation]);
        }
        return $this->privateResponse('invitation/create.html.twig', ['form' => $form, 'larp' => $larp]);
    }

    #[Route('/admin/invitation/{id}/revoke', name: 'admin_invitation_revoke', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function revoke(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $invitation = $entityManager->find(EventInvitation::class, $id) ?? throw $this->createNotFoundException();
        $this->denyAccessUnlessGranted(EventVoter::MANAGE_EVENT, $invitation);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'revoke_invitation_'.$id])->getForm()->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $invitation->revoke();
            $entityManager->flush();
            return $this->redirectToRoute('admin');
        }
        return $this->privateResponse('invitation/revoke.html.twig', ['form' => $form, 'invitation' => $invitation]);
    }

    private function privateResponse(string $template, array $parameters): Response
    {
        return $this->render($template, $parameters, new Response(headers: [
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]));
    }
}
