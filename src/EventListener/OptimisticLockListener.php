<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener]
final class OptimisticLockListener
{
    public function __construct(private readonly UserMessageTranslator $userMessages)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof OptimisticLockException || !str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => $this->userMessages->fromException($event->getThrowable()),
        ], Response::HTTP_CONFLICT));
    }
}
