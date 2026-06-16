<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Service\Auth\PlayerInactivityService;
use App\Service\Notification\NotificationHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class PlayerInactivityServiceTest extends TestCase
{
    private NotificationHelper $notificationHelperMock;
    private MailerInterface $mailerMock;
    private TranslatorInterface $translatorMock;
    private EntityManagerInterface $entityManagerMock;
    private LoggerInterface $loggerMock;
    private PlayerInactivityService $service;

    protected function setUp(): void
    {
        $this->notificationHelperMock = $this->createMock(NotificationHelper::class);
        $this->mailerMock = $this->createMock(MailerInterface::class);
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->service = new PlayerInactivityService(
            $this->notificationHelperMock,
            $this->mailerMock,
            $this->translatorMock,
            $this->entityManagerMock,
            $this->loggerMock,
            'noreply@fantager.test',
        );
    }

    public function testExecuteInactivityReleaseReleasesTeamAndSetsCooldown(): void
    {
        $team = $this->createPlayerTeam('Idle FC');
        $user = new User();
        $user->setEmail('idle@example.com');
        $user->setIsVerified(true);
        $user->setTeam($team);
        $team->setUser($user);

        $this->notificationHelperMock
            ->expects($this->once())
            ->method('sendNotification');

        $this->mailerMock
            ->expects($this->once())
            ->method('send');

        $this->service->executeInactivityRelease($team, $user);

        $this->assertTrue($team->isNpc());
        $this->assertNull($team->getUser());
        $this->assertNull($user->getTeam());
        $this->assertNotNull($user->getTeamReassignmentAvailableAt());
        $this->assertNull($user->getInactiveWarningSentAt());
    }

    public function testCalculateInactiveDaysUsesLastActivityAt(): void
    {
        $user = new User();
        $user->setLastActivityAt(new \DateTimeImmutable('-5 days', new \DateTimeZone('UTC')));

        $this->assertSame(5, $this->service->calculateInactiveDays($user));
    }

    private function createPlayerTeam(string $name): Team
    {
        $team = new Team();
        $team->setKingdom(new Kingdom());
        $team->setName($name);
        $team->setIsNpc(false);

        return $team;
    }
}
