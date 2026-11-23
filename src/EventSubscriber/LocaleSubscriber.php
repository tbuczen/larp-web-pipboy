<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Picks the interface language: the language saved in the session (set from
 * the account's choice at sign-in or by the language switch), otherwise the
 * browser's preferred language among the supported ones. The organizer
 * panel stays English.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public const string SESSION_KEY = '_locale';

    /** @param list<string> $locales */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')] private readonly array $locales,
        #[Autowire('%kernel.default_locale%')] private readonly string $defaultLocale,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before Symfony's locale listeners pass the locale to the translator.
            KernelEvents::REQUEST => [['onRequest', 20]],
            LoginSuccessEvent::class => 'onLogin',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        // The organizer panel is English only.
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            $request->setLocale('en');

            return;
        }
        $chosen = $request->hasPreviousSession() ? $request->getSession()->get(self::SESSION_KEY) : null;

        $request->setLocale(in_array($chosen, $this->locales, true)
            ? $chosen
            : ($request->getPreferredLanguage($this->locales) ?? $this->defaultLocale));
    }

    /** The account's saved language follows it to every device it signs in on. */
    public function onLogin(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();
        if ($user instanceof User && in_array($user->getLocale(), $this->locales, true) && $request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, $user->getLocale());
        }
    }
}
