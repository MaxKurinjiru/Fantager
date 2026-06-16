<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Auth\User;
use App\Service\Auth\PlayerActivityService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

final class UserActivityListener
{
    public function __construct(
        private readonly PlayerActivityService $playerActivityService,
    ) {
    }

    #[AsEventListener(event: InteractiveLoginEvent::class)]
    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        if (!$user instanceof User || !$user->isVerified()) {
            return;
        }

        $this->playerActivityService->recordActivity($user);
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 8)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (!is_string($route)) {
            return;
        }

        if (!str_starts_with($route, 'app_') && !str_starts_with($route, 'api_v1_')) {
            return;
        }

        $user = $request->getUser();
        if (!$user instanceof User || !$user->isVerified()) {
            return;
        }

        $this->playerActivityService->recordActivity($user, flush: true);
    }
}
