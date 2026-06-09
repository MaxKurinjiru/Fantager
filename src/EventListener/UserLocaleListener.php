<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Auth\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

#[AsEventListener(event: InteractiveLoginEvent::class)]
class UserLocaleListener
{
    public function __invoke(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof User) {
            $event->getRequest()->getSession()->set('_locale', $user->getLocale());
        }
    }
}
