<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use App\Enum\NotificationType;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;

class NotificationHelper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserMessageTranslator $userMessages,
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

    /**
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
        $this->sendNotification(
            $user,
            $type,
            $this->userMessages->transForUser($titleKey, $user, $titleParams),
            $this->userMessages->transForUser($bodyKey, $user, $bodyParams),
        );
    }
}
