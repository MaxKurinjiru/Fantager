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
}
