<?php

declare(strict_types=1);

namespace App\Tests\Service\Economy;

use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\Team\TeamRepository;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Team\FanClubService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ArenaRevenueServiceTest extends TestCase
{
    private HeadquartersRepository $hqRepositoryMock;
    private LeagueFixtureRepository $fixtureRepositoryMock;
    private EconomyService $economyServiceMock;
    private FinancialCrisisService $financialCrisisServiceMock;
    private ArenaRevenueService $arenaRevenueService;

    protected function setUp(): void
    {
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->fixtureRepositoryMock = $this->createMock(LeagueFixtureRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->financialCrisisServiceMock = $this->createMock(FinancialCrisisService::class);
        $this->financialCrisisServiceMock
            ->method('areHqBonusesActive')
            ->willReturn(true);
        $teamRepositoryMock = $this->createMock(TeamRepository::class);
        $fanClubService = new FanClubService($teamRepositoryMock);

        $this->arenaRevenueService = new ArenaRevenueService(
            $this->hqRepositoryMock,
            $this->fixtureRepositoryMock,
            $this->economyServiceMock,
            $this->financialCrisisServiceMock,
            $fanClubService,
        );
    }

    public function testCalculateMatchRevenueUsesFanClubAttendance(): void
    {
        $home = new Team();
        $home->setName('Home FC');
        $home->setFanBase(400);
        $home->setReputation(100);
        $home->setMorale(80);
        $home->setChemistry(25);

        $away = new Team();
        $away->setName('Away FC');
        $away->setFanBase(100);
        $away->setReputation(0);
        $away->setMorale(50);
        $away->setChemistry(0);

        $this->hqRepositoryMock->method('findOneBy')->willReturn(null);

        $report = $this->arenaRevenueService->calculateMatchRevenue($home, $away);

        $this->assertSame(ArenaRevenueService::TICKET_PRICE, $report['ticket_price']);
        $this->assertSame(500, $report['capacity']);
        $this->assertGreaterThan($report['away_attendees'], $report['home_attendees']);
        $this->assertSame($report['attendance'], $report['home_attendees'] + $report['away_attendees']);
        $this->assertGreaterThan(0, $report['gold_earned']);
        $this->assertSame(400, $report['home_fan_base']);
        $this->assertSame(100, $report['away_fan_base']);
    }

    public function testCalculateMatchRevenueCanBeEmptyWhenFanBaseIsZero(): void
    {
        $home = new Team();
        $home->setFanBase(0);
        $home->setMorale(0);

        $away = new Team();
        $away->setFanBase(0);
        $away->setMorale(0);

        $this->hqRepositoryMock->method('findOneBy')->willReturn(null);

        $report = $this->arenaRevenueService->calculateMatchRevenue($home, $away);

        $this->assertSame(0, $report['attendance']);
        $this->assertSame(0, $report['home_attendees']);
        $this->assertSame(0, $report['away_attendees']);
        $this->assertSame(0, $report['gold_earned']);
    }

    public function testCalculateMatchRevenueAppliesArenaBonuses(): void
    {
        $home = new Team();
        $home->setFanBase(350);
        $home->setReputation(50);
        $home->setMorale(50);
        $home->setChemistry(10);

        $away = new Team();
        $away->setFanBase(350);
        $away->setReputation(50);
        $away->setMorale(50);
        $away->setChemistry(10);

        $hq = new Headquarters();
        $arena = new Facility();
        $arena->setType(FacilityType::Arena);
        $arena->setPassiveBonuses(['arena_capacity' => 20.0, 'ticket_revenue_pct' => 10.0]);
        $hq->addFacility($arena);

        $this->hqRepositoryMock->method('findOneBy')->willReturn($hq);

        $report = $this->arenaRevenueService->calculateMatchRevenue($home, $away);

        $this->assertSame(600, $report['capacity']);
        $this->assertGreaterThan($report['attendance'] * ArenaRevenueService::TICKET_PRICE, $report['gold_earned']);
    }

    public function testPayFixtureRevenueCreditsHomeTeamOnly(): void
    {
        $home = new Team();
        $home->setName('Home FC');
        $home->setFanBase(350);
        $home->setReputation(20);
        $home->setMorale(60);
        $home->setChemistry(5);

        $away = new Team();
        $away->setName('Away FC');
        $away->setFanBase(200);
        $away->setReputation(10);
        $away->setMorale(55);
        $away->setChemistry(0);

        $fixture = new LeagueFixture();
        $fixture->setHomeTeam($home);
        $fixture->setAwayTeam($away);

        $this->hqRepositoryMock->method('findOneBy')->willReturn(null);

        $this->economyServiceMock
            ->expects($this->once())
            ->method('addGold')
            ->with(
                $home,
                $this->greaterThan(0),
                FinancialRecordType::ArenaRevenue,
                FinancialRecordActor::System,
                $this->callback(static fn(array $ctx): bool => isset($ctx['away_team_name']) && 'Away FC' === $ctx['away_team_name'])
            );

        $this->arenaRevenueService->payFixtureRevenue($fixture);
    }

    public function testPayFixtureRevenueSkipsGoldWhenNobodyAttends(): void
    {
        $home = new Team();
        $home->setFanBase(0);
        $away = new Team();
        $away->setFanBase(0);

        $fixture = new LeagueFixture();
        $fixture->setHomeTeam($home);
        $fixture->setAwayTeam($away);

        $this->hqRepositoryMock->method('findOneBy')->willReturn(null);
        $this->economyServiceMock->expects($this->never())->method('addGold');

        $report = $this->arenaRevenueService->payFixtureRevenue($fixture);

        $this->assertSame(0, $report['gold_earned']);
    }

    public function testProcessLeagueMatchTickProcessesFixturesAtTime(): void
    {
        $kingdom = new Kingdom();
        $scheduledAt = new \DateTimeImmutable('2026-06-10 18:00:00');

        $home = new Team();
        $home->setFanBase(350);
        $home->setReputation(10);
        $home->setMorale(50);
        $home->setChemistry(0);
        $away = new Team();
        $away->setFanBase(350);
        $away->setReputation(10);
        $away->setMorale(50);
        $away->setChemistry(0);

        $fixture = new LeagueFixture();
        $fixture->setHomeTeam($home);
        $fixture->setAwayTeam($away);

        $this->fixtureRepositoryMock
            ->expects($this->once())
            ->method('findScheduledFixturesAtTime')
            ->with($kingdom, $scheduledAt)
            ->willReturn([$fixture]);

        $this->hqRepositoryMock->method('findOneBy')->willReturn(null);

        $this->economyServiceMock->expects($this->once())->method('addGold');
        $this->economyServiceMock->expects($this->once())->method('flush');

        $results = $this->arenaRevenueService->processLeagueMatchTick($kingdom, $scheduledAt);

        $this->assertCount(1, $results);
    }
}
