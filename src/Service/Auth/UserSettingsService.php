<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Auth\UserSettings;
use Doctrine\ORM\EntityManagerInterface;

class UserSettingsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreate(User $user): UserSettings
    {
        $settings = $user->getSettings();
        if ($settings instanceof UserSettings) {
            return $settings;
        }

        $settings = new UserSettings();
        $settings->setUser($user);
        $user->setSettings($settings);

        $this->entityManager->persist($settings);

        return $settings;
    }
}
