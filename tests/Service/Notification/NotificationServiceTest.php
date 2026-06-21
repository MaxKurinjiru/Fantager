<?php

declare(strict_types=1);

namespace App\Tests\Service\Notification;

use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use App\Enum\NotificationType;
use App\Repository\Notification\NotificationRepository;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class NotificationServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&NotificationRepository */
    private $notificationRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->notificationRepositoryMock = $this->createMock(NotificationRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->service = new NotificationService($this->notificationRepositoryMock, $this->entityManagerMock);
    }

    public function testCountUnreadDelegatesToRepository(): void
    {
        $user = new User();
        $this->notificationRepositoryMock
            ->expects($this->once())
            ->method('countUnreadForUser')
            ->with($user)
            ->willReturn(3);

        $this->assertSame(3, $this->service->countUnread($user));
    }

    public function testMarkReadIsIdempotent(): void
    {
        $user = new User();
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(NotificationType::System);
        $notification->setTitle('Test');
        $notification->setBody('Body');
        $notification->setIsRead(true);

        $this->notificationRepositoryMock
            ->method('findOneForUser')
            ->willReturn($notification);

        $this->entityManagerMock->expects($this->never())->method('flush');

        $result = $this->service->markRead($user, 1);

        $this->assertTrue($result->isRead());
    }

    public function testMarkReadFlushesWhenUnread(): void
    {
        $user = new User();
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(NotificationType::System);
        $notification->setTitle('Test');
        $notification->setBody('Body');

        $this->notificationRepositoryMock
            ->method('findOneForUser')
            ->willReturn($notification);

        $this->entityManagerMock->expects($this->once())->method('flush');

        $result = $this->service->markRead($user, 1);

        $this->assertTrue($result->isRead());
    }

    public function testGetForUserThrowsWhenMissing(): void
    {
        $user = new User();
        $this->notificationRepositoryMock
            ->method('findOneForUser')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->service->getForUser($user, 99);
    }
}
