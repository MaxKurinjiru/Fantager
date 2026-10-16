<?php

declare(strict_types=1);

namespace App\Tests\Service\Notification;

use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use App\Enum\NotificationType;
use App\Service\Notification\NotificationHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class NotificationHelperTest extends TestCase
{
    public function testSendTranslatedNotificationStoresKeysNotRenderedStrings(): void
    {
        /** @var Notification|null $persisted */
        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $em->expects($this->once())->method('flush');

        $helper = new NotificationHelper($em);
        $user = new User();
        $helper->sendTranslatedNotification(
            $user,
            NotificationType::MarketplaceSold,
            'notification.marketplace_sold_title',
            'notification.marketplace_sold_body',
            [],
            ['%listing%' => 7, '%buyer%' => 'Bob', '%amount%' => 100],
        );

        $this->assertInstanceOf(Notification::class, $persisted);
        $this->assertSame($user, $persisted->getUser());
        $this->assertSame(NotificationType::MarketplaceSold, $persisted->getType());
        $this->assertSame('notification.marketplace_sold_title', $persisted->getTitleKey());
        $this->assertSame('notification.marketplace_sold_body', $persisted->getBodyKey());
        $this->assertSame(
            ['%listing%' => 7, '%buyer%' => 'Bob', '%amount%' => 100],
            $persisted->getBodyParams(),
        );
        $this->assertNull($persisted->getTitle());
        $this->assertNull($persisted->getBody());
    }

    public function testSendNotificationStoresFrozenStrings(): void
    {
        /** @var Notification|null $persisted */
        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $em->expects($this->once())->method('flush');

        $helper = new NotificationHelper($em);
        $helper->sendNotification(new User(), NotificationType::System, 'Hello', 'World');

        $this->assertInstanceOf(Notification::class, $persisted);
        $this->assertSame('Hello', $persisted->getTitle());
        $this->assertSame('World', $persisted->getBody());
        $this->assertNull($persisted->getTitleKey());
        $this->assertNull($persisted->getBodyKey());
    }
}
