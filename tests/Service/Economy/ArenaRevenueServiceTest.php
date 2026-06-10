<?php

declare(strict_types=1);

namespace App\Tests\Service\Economy;

use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Team\TeamRepository;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Economy\EconomyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class ArenaRevenueServiceTest extends TestCase
{
    private $teamRepositoryMock;
    private $hqRepositoryMock;
    private $economyServiceMock;
    private $entityManagerMock;
    private ArenaRevenueService $arenaRevenueService;

    protected function setUp(): void
    {
        $this->teamRepositoryMock = $this->createMock(TeamRepository::class);
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->arenaRevenueService = new ArenaRevenueService(
            $this->teamRepositoryMock,
            $this->hqRepositoryMock,
            $this->economyServiceMock,
            $this->entityManagerMock
        );
    }

    public function testCalculateTeamRevenueNoHqUsesDefaults(): void
    {
        $team = new Team();
        $team->setName('Noob Team');
        $team->setReputation(0);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn(null);

        $report = $this->arenaRevenueService->calculateTeamRevenue($team);

        // Capacity: 500
        // Reputation: 0 -> Attendance ratio: 0.4 -> Attendance: 200
        // Price: 5 -> Base revenue: 1000 Gold
        // Multipliers: None
        $this->assertSame(500, $report['capacity']);
        $this->assertSame(200, $report['attendance']);
        $this->assertSame(1000, $report['gold_earned']);
    }

    public function testCalculateTeamRevenueWithHqUpgrades(): void
    {
        $team = new Team();
        $team->setName('Pro Team');
        $team->setReputation(100); // 100 reputation

        $hq = new Headquarters();
        
        // Level 2 Arena: arena_capacity +20%, ticket_revenue_pct +12%
        $arena = new Facility();
        $arena->setType(FacilityType::Arena);
        $arena->setPassiveBonuses([
            'arena_capacity' => 20.0,
            'ticket_revenue_pct' => 12.0
        ]);
        $hq->addFacility($arena);

        // Level 2 Treasury: gold_income_pct +8%
        $treasury = new Facility();
        $treasury->setType(FacilityType::Treasury);
        $treasury->setPassiveBonuses([
            'gold_income_pct' => 8.0
        ]);
        $hq->addFacility($treasury);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        $report = $this->arenaRevenueService->calculateTeamRevenue($team);

        // Seating Capacity: 500 * 1.20 = 600
        // Reputation: 100 -> Attendance ratio: 0.4 + 0.6 * (100 / 200) = 0.70
        // Attendance: 600 * 0.70 = 420
        // Base Revenue: 420 * 5 = 2100 Gold
        // Adjusted: 2100 * 1.12 * 1.08 = 2540.16 -> round = 2540 Gold
        $this->assertSame(600, $report['capacity']);
        $this->assertSame(420, $report['attendance']);
        $this->assertSame(2540, $report['gold_earned']);
    }

    public function testDistributeWeeklyRevenue(): void
    {
        $team1 = $this->createMock(Team::class);
        $team1->method('getId')->willReturn(1);
        $team1->method('getName')->willReturn('Team A');
        $team1->method('getReputation')->willReturn(0);

        $team2 = $this->createMock(Team::class);
        $team2->method('getId')->willReturn(2);
        $team2->method('getName')->willReturn('Team B');
        $team2->method('getReputation')->willReturn(50);

        $this->teamRepositoryMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['isNpc' => false])
            ->willReturn([$team1, $team2]);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn(null);

        $invocations = [];
        $this->economyServiceMock
            ->expects($this->exactly(2))
            ->method('addGold')
            ->willReturnCallback(function (Team $team, int $amount, $type, $actor, $context = []) use (&$invocations) {
                $invocations[] = [$team, $amount, $type, $actor, $context];
            });

        $this->economyServiceMock
            ->expects($this->once())
            ->method('flush');

        $reports = $this->arenaRevenueService->distributeWeeklyRevenue();

        $this->assertCount(2, $reports);
        
        $this->assertSame($team1, $invocations[0][0]);
        $this->assertSame(1000, $invocations[0][1]);
        $this->assertSame(FinancialRecordType::ArenaRevenue, $invocations[0][2]);
        $this->assertSame(FinancialRecordActor::System, $invocations[0][3]);

        $this->assertSame($team2, $invocations[1][0]);
        $this->assertSame(1500, $invocations[1][1]);
        $this->assertSame(FinancialRecordType::ArenaRevenue, $invocations[1][2]);
        $this->assertSame(FinancialRecordActor::System, $invocations[1][3]);
    }
}
