<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Auth\UserSettings;
use App\Service\Auth\UserSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class UserSettingsServiceTest extends TestCase
{
    public function testGetOrCreateReturnsExistingSettings(): void
    {
        $user = new User();
        $settings = new UserSettings();
        $settings->setUser($user);
        $user->setSettings($settings);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $service = new UserSettingsService($entityManager);

        $this->assertSame($settings, $service->getOrCreate($user));
    }

    public function testGetOrCreateCreatesDefaultSettings(): void
    {
        $user = new User();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(UserSettings::class));

        $service = new UserSettingsService($entityManager);
        $settings = $service->getOrCreate($user);

        $this->assertSame($user, $settings->getUser());
        $this->assertSame($settings, $user->getSettings());
        $this->assertFalse($settings->isCloseModalOnBackdrop());
    }
}
