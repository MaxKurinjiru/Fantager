<?php

declare(strict_types=1);

namespace App\Tests\Service\Team;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\MatchResult;
use App\Repository\Team\TeamRepository;
use App\Service\Team\FanClubService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FanClubServiceTest extends TestCase
{
    private TeamRepository $teamRepositoryMock;
    private FanClubService $fanClubService;

    protected function setUp(): void
    {
        $this->teamRepositoryMock = $this->createMock(TeamRepository::class);
        $this->fanClubService = new FanClubService($this->teamRepositoryMock);
    }

    public function testShowUpRateIsZeroForCollapsedMorale(): void
    {
        $team = new Team();
        $team->setReputation(0);
        $team->setMorale(0);
        $team->setChemistry(0);

        $this->assertSame(0.0, $this->fanClubService->calculateShowUpRate($team));
        $this->assertSame(0, $this->fanClubService->calculateHomeAttendance($team));
    }

    public function testMatchAttendanceCanBeZero(): void
    {
        $home = new Team();
        $home->setFanBase(0);
        $away = new Team();
        $away->setFanBase(0);

        $result = $this->fanClubService->calculateMatchAttendance($home, $away, 500);

        $this->assertSame(0, $result['attendance']);
        $this->assertSame(0, $result['home_attendees']);
        $this->assertSame(0, $result['away_attendees']);
    }

    public function testAwayAttendanceIsLowerThanHome(): void
    {
        $home = new Team();
        $home->setFanBase(400);
        $home->setMorale(60);
        $home->setReputation(20);

        $away = new Team();
        $away->setFanBase(400);
        $away->setMorale(60);
        $away->setReputation(20);

        $result = $this->fanClubService->calculateMatchAttendance($home, $away, 500);

        $this->assertGreaterThan($result['away_attendees'], $result['home_attendees']);
        $this->assertSame($result['attendance'], $result['home_attendees'] + $result['away_attendees']);
    }

    public function testEvolveFanBaseDriftsTowardTarget(): void
    {
        $team = new Team();
        $team->setFanBase(100);
        $team->setReputation(100);
        $team->setMorale(80);
        $team->setChemistry(30);

        $target = $this->fanClubService->calculateTargetFanBase($team);
        $this->assertGreaterThan(100, $target);

        $newBase = $this->fanClubService->evolveFanBase($team);

        $this->assertGreaterThan(100, $newBase);
        $this->assertLessThan($target, $newBase);
    }

    public function testApplyMatchResultAdjustsFanBase(): void
    {
        $team = new Team();
        $team->setFanBase(300);

        $this->fanClubService->applyMatchResult($team, MatchResult::Win);
        $this->assertSame(312, $team->getFanBase());

        $this->fanClubService->applyMatchResult($team, MatchResult::Loss);
        $this->assertSame(302, $team->getFanBase());
    }

    public function testProcessDailyEvolutionTickUpdatesAllTeams(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setFanBase(100);
        $team->setMorale(80);

        $this->teamRepositoryMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['kingdom' => $kingdom])
            ->willReturn([$team]);

        $updated = $this->fanClubService->processDailyEvolutionTick($kingdom);

        $this->assertSame(1, $updated);
        $this->assertGreaterThan(100, $team->getFanBase());
    }
}
