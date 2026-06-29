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
use App\Service\Economy\TeamPayrollService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Notification\NotificationHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class FinancialCrisisServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeadquartersRepository */
    private $hqRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EconomyService */
    private $economyServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamPayrollService */
    private $teamPayrollServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&NotificationHelper */
    private $notificationHelperMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamChronicleService */
    private $teamChronicleServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Psr\Log\LoggerInterface */
    private $loggerMock;
    private FinancialCrisisService $service;

    protected function setUp(): void
    {
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->teamPayrollServiceMock = $this->createMock(TeamPayrollService::class);
        $this->notificationHelperMock = $this->createMock(NotificationHelper::class);
        $this->teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->service = new FinancialCrisisService(
            $this->hqRepositoryMock,
            $this->economyServiceMock,
            $this->teamPayrollServiceMock,
            $this->notificationHelperMock,
            $this->teamChronicleServiceMock,
            $this->entityManagerMock,
            $this->loggerMock,
        );

        $this->teamPayrollServiceMock
            ->method('calculateWeeklyPayrollFee')
            ->willReturn(0);
    }

    public function testResolveCrisisLevelNoneForStableTeam(): void
    {
        $team = $this->createPlayerTeam(gold: 1000, debt: 0, crisisWeeks: 0);

        $this->assertSame(
            FinancialCrisisLevel::None,
            $this->service->resolveCrisisLevel($team, 271)
        );
    }

    public function testResolveCrisisLevelNoneWithLowGoldButNoDebt(): void
    {
        $team = $this->createPlayerTeam(gold: 300, debt: 0, crisisWeeks: 0);

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
            ->willReturnCallback(function (array $criteria) use ($team, $hq) {
                $this->assertSame(['team' => $team], $criteria);
                return $hq;
            });

        $this->expectException(\DomainException::class);
        $this->service->assertSpendingAllowed($team, 'summon');
    }

    public function testApplyGoldToDebtReducesDebt(): void
    {
        $team = $this->createPlayerTeam(gold: 300, debt: 500, crisisWeeks: 1);

        $calledDeduct = null;
        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold')
            ->willReturnCallback(function (Team $t, int $amount) use (&$calledDeduct) {
                $calledDeduct = [$t, $amount];
                return true;
            });

        $paid = $this->service->applyGoldToDebt($team);

        $this->assertNotNull($calledDeduct);
        $this->assertSame($team, $calledDeduct[0]);
        $this->assertSame(300, $calledDeduct[1]);
        $this->assertSame(300, $paid);
        $this->assertSame(200, $team->getUnpaidDebt());
    }

    public function testExecuteBankruptcyReleasesTeamAndSetsCooldown(): void
    {
        $team = $this->createPlayerTeam(gold: 0, debt: 1200, crisisWeeks: 6);
        $team->setName('Bankrupt FC');
        $user = new User();
        $user->setEmail('player@example.com');
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
