<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\League\LeagueStanding;
use App\Service\League\LeagueStandingService;
use PHPUnit\Framework\TestCase;

class LeagueStandingServiceTest extends TestCase
{
    private LeagueStandingService $service;

    protected function setUp(): void
    {
        $this->service = new LeagueStandingService();
    }

    public function testApplyMatchResultRecordsHomeWin(): void
    {
        $home = new LeagueStanding();
        $away = new LeagueStanding();

        $this->service->applyMatchResult($home, $away, 4, 2);

        $this->assertSame(1, $home->getPlayed());
        $this->assertSame(1, $away->getPlayed());
        $this->assertSame(1, $home->getWins());
        $this->assertSame(3, $home->getPoints());
        $this->assertSame(2, $home->getGoalDifference());
        $this->assertSame(1, $away->getLosses());
        $this->assertSame(-2, $away->getGoalDifference());
    }

    public function testApplyMatchResultRecordsDraw(): void
    {
        $home = new LeagueStanding();
        $away = new LeagueStanding();

        $this->service->applyMatchResult($home, $away, 3, 3);

        $this->assertSame(1, $home->getDraws());
        $this->assertSame(1, $away->getDraws());
        $this->assertSame(1, $home->getPoints());
        $this->assertSame(1, $away->getPoints());
        $this->assertSame(0, $home->getGoalDifference());
    }

    public function testApplyMatchResultRecordsAwayWin(): void
    {
        $home = new LeagueStanding();
        $away = new LeagueStanding();

        $this->service->applyMatchResult($home, $away, 1, 5);

        $this->assertSame(1, $away->getWins());
        $this->assertSame(3, $away->getPoints());
        $this->assertSame(1, $home->getLosses());
        $this->assertSame(-4, $home->getGoalDifference());
        $this->assertSame(4, $away->getGoalDifference());
    }
}
