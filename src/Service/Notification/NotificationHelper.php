<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

class NotificationHelper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Persist an already-rendered title/body (legacy rows / tests).
     * Production writers use sendTranslatedNotification().
     */
    public function sendNotification(User $user, NotificationType $type, string $title, string $body): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setBody($body);

        $this->em->persist($notification);
        $this->em->flush();
    }

    /**
     * Store translation keys + params; NotificationPresenter renders in the reader's locale.
     *
     * @param array<string, int|string|float> $titleParams
     * @param array<string, int|string|float> $bodyParams
     */
    public function sendTranslatedNotification(
        User $user,
        NotificationType $type,
        string $titleKey,
        string $bodyKey,
        array $titleParams = [],
        array $bodyParams = [],
    ): void {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitleKey($titleKey);
        $notification->setBodyKey($bodyKey);
        $notification->setTitleParams($titleParams);
        $notification->setBodyParams($bodyParams);

        $this->em->persist($notification);
        $this->em->flush();
    }
}
