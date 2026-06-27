<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Enum\NotificationType;
use App\Entity\Auth\User;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Service\Auth\PlayerInactivityService;
use App\Service\TeamChronicle\TeamChronicleService;
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
    /** @var \PHPUnit\Framework\MockObject\MockObject&NotificationHelper */
    private $notificationHelperMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&MailerInterface */
    private $mailerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TranslatorInterface */
    private $translatorMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&LoggerInterface */
    private $loggerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamChronicleService */
    private $teamChronicleServiceMock;
    private PlayerInactivityService $service;

    protected function setUp(): void
    {
        $this->notificationHelperMock = $this->createMock(NotificationHelper::class);
        $this->mailerMock = $this->createMock(MailerInterface::class);
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);

        $this->service = new PlayerInactivityService(
            $this->notificationHelperMock,
            $this->mailerMock,
            $this->translatorMock,
            $this->entityManagerMock,
            $this->loggerMock,
            $this->teamChronicleServiceMock,
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

    public function testProcessDailyInactivityTickIgnoresTestAccounts(): void
    {
        $kingdom = new Kingdom();
        $scheduledAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // 1. Create a regular user who is inactive and should be warned
        $regularUser = new User();
        $regularUser->setEmail('regular@example.com');
        $regularUser->setIsVerified(true);
        $regularUser->setLastActivityAt($scheduledAt->modify('-25 days'));
        $regularTeam = $this->createPlayerTeam('Regular FC');
        $regularTeam->setUser($regularUser);
        $regularUser->setTeam($regularTeam);

        // 2. Create a test user who is also inactive and would be warned, but has ROLE_TEST
        $testUser = new User();
        $testUser->setEmail('test@example.com');
        $testUser->setIsVerified(true);
        $testUser->setRoles(['ROLE_TEST']);
        $testUser->setLastActivityAt($scheduledAt->modify('-25 days'));
        $testTeam = $this->createPlayerTeam('Test FC');
        $testTeam->setUser($testUser);
        $testUser->setTeam($testTeam);

        // Mock the query builder to return both users
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn([$regularUser, $testUser]);

        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('innerJoin')->willReturnSelf();
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $userRepositoryMock = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $userRepositoryMock->method('createQueryBuilder')->willReturn($qbMock);

        $this->entityManagerMock
            ->method('getRepository')
            ->willReturn($userRepositoryMock);

        // The regular user should receive a warning (notification & email)
        // The test user should NOT receive any warning or email
        // Therefore, sendNotification and send should only be called ONCE (for the regular user)
        $this->notificationHelperMock
            ->expects($this->once())
            ->method('sendNotification')
            ->with($regularUser, NotificationType::System, 'Inactivity warning');

        $this->mailerMock
            ->expects($this->once())
            ->method('send');

        $this->service->processDailyInactivityTick($kingdom, $scheduledAt);

        // Verify regular user has warning timestamp set, but test user does not
        $this->assertNotNull($regularUser->getInactiveWarningSentAt());
        $this->assertNull($testUser->getInactiveWarningSentAt());
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
