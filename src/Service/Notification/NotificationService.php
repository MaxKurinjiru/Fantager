<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use App\Exception\UserFacingException;
use App\Repository\Notification\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<Notification>
     */
    public function listForUser(User $user, bool $unreadOnly = false, int $limit = 50, ?int $page = null): array
    {
        return $this->notificationRepository->findForUser($user, $limit, $unreadOnly, $page);
    }

    public function countForUser(User $user, bool $unreadOnly = false): int
    {
        return $this->notificationRepository->countForUser($user, $unreadOnly);
    }

    public function countUnread(User $user): int
    {
        return $this->notificationRepository->countUnreadForUser($user);
    }

    public function getForUser(User $user, int $id): Notification
    {
        $notification = $this->notificationRepository->findOneForUser($id, $user);
        if (null === $notification) {
            throw new UserFacingException('error.notification_not_found');
        }

        return $notification;
    }

    public function markRead(User $user, int $id): Notification
    {
        $notification = $this->getForUser($user, $id);
        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->em->flush();
        }

        return $notification;
    }

    public function markAllRead(User $user): int
    {
        /** @var list<Notification> $unread */
        $unread = $this->notificationRepository->findForUser($user, 100, true);
        $count = 0;

        foreach ($unread as $notification) {
            $notification->setIsRead(true);
            ++$count;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        return $count;
    }
}
