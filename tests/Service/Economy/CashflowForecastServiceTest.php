<?php

declare(strict_types=1);

namespace App\Tests\Service\Economy;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Repository\League\LeagueFixtureRepository;
use App\Service\Calendar\TickClock;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Economy\CashflowForecastService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CashflowForecastServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&LeagueFixtureRepository */
    private $fixtureRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&ArenaRevenueService */
    private $arenaRevenueServiceMock;
    private CashflowForecastService $service;

    protected function setUp(): void
    {
        $this->fixtureRepositoryMock = $this->createMock(LeagueFixtureRepository::class);
        $this->arenaRevenueServiceMock = $this->createMock(ArenaRevenueService::class);

        $this->service = new CashflowForecastService(
            $this->fixtureRepositoryMock,
            $this->arenaRevenueServiceMock,
            new TickClock(),
        );
    }

    public function testResolveNextWeeklyResetUsesThisSundayBeforeTick(): void
    {
        $tz = new \DateTimeZone('Europe/Prague');
        $now = new \DateTimeImmutable('2026-10-25 10:00:00', $tz);

        $reset = $this->service->resolveNextWeeklyReset($tz, $now);

        $this->assertSame('2026-10-25 23:59:00', $reset->format('Y-m-d H:i:s'));
        $this->assertSame('Europe/Prague', $reset->getTimezone()->getName());
    }

    public function testResolveNextWeeklyResetRollsForwardAtTickInstant(): void
    {
        $tz = new \DateTimeZone('Europe/Prague');
        $now = new \DateTimeImmutable('2026-10-25 23:59:00', $tz);

        $reset = $this->service->resolveNextWeeklyReset($tz, $now);

        $this->assertSame('2026-11-01 23:59:00', $reset->format('Y-m-d H:i:s'));
    }

    public function testResolveNextWeeklyResetFromWednesday(): void
    {
        $tz = new \DateTimeZone('Europe/Prague');
        $now = new \DateTimeImmutable('2026-10-21 12:00:00', $tz);

        $reset = $this->service->resolveNextWeeklyReset($tz, $now);

        $this->assertSame('2026-10-25 23:59:00', $reset->format('Y-m-d H:i:s'));
    }

    public function testBuildForecastProjectsArenaAndShortfall(): void
    {
        $team = $this->createTeam();
        $away = $this->createTeam('Rivals');
        $now = new \DateTimeImmutable('2026-10-21 12:00:00', new \DateTimeZone('Europe/Prague'));
        $kickoff = new \DateTimeImmutable('2026-10-23 18:00:00', new \DateTimeZone('UTC'));
        $fixture = $this->createFixture($away, $kickoff);

        $this->fixtureRepositoryMock
            ->expects($this->once())
            ->method('findScheduledHomeFixturesUntil')
            ->willReturnCallback(function (Team $queried, \DateTimeImmutable $until) use ($team, $fixture) {
                $this->assertSame($team, $queried);
                $this->assertSame('2026-10-25 23:59:00', $until->format('Y-m-d H:i:s'));

                return [$fixture];
            });

        $this->arenaRevenueServiceMock
            ->expects($this->once())
            ->method('calculateMatchRevenue')
            ->with($team, $away)
            ->willReturn(['gold_earned' => 400]);

        $forecast = $this->service->buildForecast($team, [
            'gold' => 100,
            'unpaid_debt' => 50,
            'weekly_payroll' => 600,
            'weekly_maintenance' => 200,
            'weekly_expenses' => 800,
        ], $now);

        $this->assertSame(400, $forecast['estimated_arena']);
        $this->assertSame(1, $forecast['home_match_count']);
        $this->assertSame('Rivals', $forecast['home_matches'][0]['opponent_name']);
        $this->assertSame(400, $forecast['home_matches'][0]['projected_gold']);
        $this->assertSame(-400, $forecast['estimated_net']);
        $this->assertFalse($forecast['covers_weekly_costs']);
        $this->assertSame(0, $forecast['gold_after_tick']);
        $this->assertSame(350, $forecast['debt_after_tick']);
    }

    public function testBuildForecastCoversCostsAndRepaysDebt(): void
    {
        $team = $this->createTeam();
        $now = new \DateTimeImmutable('2026-10-21 12:00:00', new \DateTimeZone('Europe/Prague'));

        $this->fixtureRepositoryMock
            ->expects($this->once())
            ->method('findScheduledHomeFixturesUntil')
            ->willReturn([]);

        $forecast = $this->service->buildForecast($team, [
            'gold' => 1000,
            'unpaid_debt' => 200,
            'weekly_payroll' => 400,
            'weekly_maintenance' => 100,
            'weekly_expenses' => 500,
        ], $now);

        $this->assertSame(0, $forecast['estimated_arena']);
        $this->assertSame(0, $forecast['home_match_count']);
        $this->assertSame(-500, $forecast['estimated_net']);
        $this->assertTrue($forecast['covers_weekly_costs']);
        $this->assertSame(300, $forecast['gold_after_tick']);
        $this->assertSame(0, $forecast['debt_after_tick']);
    }

    private function createTeam(string $name = 'Home'): Team
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $kingdom->setLanguage('en');
        $kingdom->setTimezone('Europe/Prague');

        $team = new Team();
        $team->setKingdom($kingdom);
        $team->setName($name);

        return $team;
    }

    private function createFixture(Team $away, \DateTimeImmutable $scheduledAt): LeagueFixture
    {
        $fixture = $this->createMock(LeagueFixture::class);
        $fixture->method('getAwayTeam')->willReturn($away);
        $fixture->method('getScheduledAt')->willReturn($scheduledAt);

        return $fixture;
    }
}
