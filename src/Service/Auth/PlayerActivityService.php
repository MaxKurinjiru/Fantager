<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use Doctrine\ORM\EntityManagerInterface;

class PlayerActivityService
{
    private const UPDATE_INTERVAL_SECONDS = 3600;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function recordActivity(User $user, bool $flush = true): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $lastActivity = $user->getLastActivityAt() ?? $user->getCreatedAt();

        if ($lastActivity >= $now->modify(sprintf('-%d seconds', self::UPDATE_INTERVAL_SECONDS))) {
            return;
        }

        $user->setLastActivityAt($now);
        $user->setInactiveWarningSentAt(null);

        if ($flush) {
            $this->em->flush();
        }
    }
}
