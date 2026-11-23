<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\EventSubscriber\LocaleSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Interface language switch. Signed-in accounts keep the choice on every device. */
final class SettingsController extends AbstractController
{
    /** @param list<string> $locales */
    public function __construct(#[Autowire('%kernel.enabled_locales%')] private readonly array $locales)
    {
    }

    #[Route('/settings/language', name: 'app_settings_language', methods: ['POST'])]
    public function language(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        $locale = (string) $request->request->get('locale');
        if (in_array($locale, $this->locales, true) && $this->isCsrfTokenValid('language', (string) $request->request->get('_token'))) {
            $request->getSession()->set(LocaleSubscriber::SESSION_KEY, $locale);
            $user = $this->getUser();
            if ($user instanceof User) {
                $user->setLocale($locale);
                $entityManager->flush();
            }
        }

        return $this->redirect($this->backTo($request));
    }

    /** Only same-site paths: never an open redirect. */
    private function backTo(Request $request): string
    {
        $back = (string) $request->request->get('back');

        return preg_match('~^/(?!/)[^\s\\\\]*$~D', $back) === 1 ? $back : $this->generateUrl('app_pipboy');
    }
}
