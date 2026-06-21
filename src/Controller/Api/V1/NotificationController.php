<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use App\Service\Notification\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/notifications')]
#[IsGranted('ROLE_PLAYER')]
class NotificationController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $unreadOnly = filter_var($request->query->get('unread_only', false), FILTER_VALIDATE_BOOL);
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 10)));

        $notifications = $this->notificationService->listForUser($user, $unreadOnly, $limit, $page);
        $totalItems = $this->notificationService->countForUser($user, $unreadOnly);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        return $this->json([
            'items' => array_map([$this, 'serializeNotification'], $notifications),
            'page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
        ]);
    }

    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json(['count' => $this->notificationService->countUnread($user)]);
    }

    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['PUT'])]
    public function readAll(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $count = $this->notificationService->markAllRead($user);

        return $this->json(['marked' => $count]);
    }

    #[Route('/{id}', name: 'api_notifications_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $notification = $this->notificationService->markRead($user, $id);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 404);
        }

        return $this->json($this->serializeNotification($notification));
    }

    #[Route('/{id}/read', name: 'api_notifications_mark_read', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function markRead(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $notification = $this->notificationService->markRead($user, $id);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 404);
        }

        return $this->json($this->serializeNotification($notification));
    }

    /** @return array<string, mixed> */
    private function serializeNotification(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType()->value,
            'title' => $notification->getTitle(),
            'body' => $notification->getBody(),
            'is_read' => $notification->isRead(),
            'created_at' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
