<?php

declare(strict_types=1);

namespace App\Tests\Service\Economy;

use App\Entity\Auth\User;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FinancialCrisisLevel;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Notification\NotificationHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class FinancialCrisisServiceTest extends TestCase
{
    private HeadquartersRepository $hqRepositoryMock;
    private EconomyService $economyServiceMock;
    private NotificationHelper $notificationHelperMock;
    private EntityManagerInterface $entityManagerMock;
    private LoggerInterface $loggerMock;
    private FinancialCrisisService $service;

    protected function setUp(): void
    {
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->notificationHelperMock = $this->createMock(NotificationHelper::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->service = new FinancialCrisisService(
            $this->hqRepositoryMock,
            $this->economyServiceMock,
            $this->notificationHelperMock,
            $this->entityManagerMock,
            $this->loggerMock,
        );
    }

    public function testResolveCrisisLevelNoneForStableTeam(): void
    {
        $team = $this->createPlayerTeam(gold: 1000, debt: 0, crisisWeeks: 0);

        $this->assertSame(
            FinancialCrisisLevel::None,
            $this->service->resolveCrisisLevel($team, 271)
        );
    }

    public function testResolveCrisisLevelWarningForDebt(): void
    {
        $team = $this->createPlayerTeam(gold: 100, debt: 200, crisisWeeks: 0);

        $this->assertSame(
            FinancialCrisisLevel::Warning,
            $this->service->resolveCrisisLevel($team, 271)
        );
    }

    public function testResolveCrisisLevelRestrictedAfterTwoWeeks(): void
    {
        $team = $this->createPlayerTeam(gold: 0, debt: 500, crisisWeeks: 2);

        $this->assertSame(
            FinancialCrisisLevel::Restricted,
            $this->service->resolveCrisisLevel($team, 271)
        );
    }

    public function testAssertSpendingAllowedThrowsWhenRestricted(): void
    {
        $team = $this->createPlayerTeam(gold: 0, debt: 500, crisisWeeks: 3);
        $hq = $this->createHeadquarters($team);

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $this->expectException(\DomainException::class);
        $this->service->assertSpendingAllowed($team, 'summon');
    }

    public function testApplyGoldToDebtReducesDebt(): void
    {
        $team = $this->createPlayerTeam(gold: 300, debt: 500, crisisWeeks: 1);

        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold')
            ->with($team, 300, $this->anything(), $this->anything(), $this->anything());

        $paid = $this->service->applyGoldToDebt($team);

        $this->assertSame(300, $paid);
        $this->assertSame(200, $team->getUnpaidDebt());
    }

    public function testExecuteBankruptcyReleasesTeamAndSetsCooldown(): void
    {
        $team = $this->createPlayerTeam(gold: 0, debt: 1200, crisisWeeks: 6);
        $team->setName('Bankrupt FC');
        $user = new User();
        $user->setTeam($team);
        $team->setUser($user);

        $this->notificationHelperMock
            ->expects($this->once())
            ->method('sendNotification');

        $this->service->executeBankruptcy($team, $user);

        $this->assertTrue($team->isNpc());
        $this->assertNull($team->getUser());
        $this->assertSame(0, $team->getUnpaidDebt());
        $this->assertSame(0, $team->getCrisisWeeks());
        $this->assertNull($user->getTeam());
        $this->assertNotNull($user->getTeamReassignmentAvailableAt());
    }

    private function createPlayerTeam(int $gold, int $debt, int $crisisWeeks): Team
    {
        $team = new Team();
        $team->setKingdom(new Kingdom());
        $team->setName('Test Team');
        $team->setGold($gold);
        $team->setUnpaidDebt($debt);
        $team->setCrisisWeeks($crisisWeeks);
        $team->setIsNpc(false);
        $team->setUser(new User());

        return $team;
    }

    private function createHeadquarters(Team $team): Headquarters
    {
        $hq = new Headquarters();
        $hq->setTeam($team);
        foreach (\App\Enum\FacilityType::cases() as $type) {
            $facility = new \App\Entity\Headquarters\Facility();
            $facility->setType($type);
            $facility->setLevel(1);
            $hq->addFacility($facility);
        }
        $hq->syncTotalLevel();

        return $hq;
    }
}
