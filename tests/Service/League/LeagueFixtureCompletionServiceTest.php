<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\Combat\Battle;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\LeagueFixtureStatus;
use App\Enum\MatchType;
use App\Service\League\LeagueFixtureCompletionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LeagueFixtureCompletionServiceTest extends TestCase
{
    public function testCompleteMarksFixtureWithoutImmediateFormationCleanup(): void
    {
        $homeTeam = new Team();
        $awayTeam = new Team();

        $fixture = new LeagueFixture();
        $fixture->setHomeTeam($homeTeam);
        $fixture->setAwayTeam($awayTeam);
        $fixture->setScheduledAt(new \DateTimeImmutable('2026-06-20 18:00:00'));
        $fixture->setStatus(LeagueFixtureStatus::Scheduled);

        $battle = new Battle();
        $battle->setMatchType(MatchType::League);
        $battle->setTeamA($homeTeam);
        $battle->setTeamB($awayTeam);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new LeagueFixtureCompletionService($em);
        $service->complete($fixture, $battle);

        $this->assertSame(LeagueFixtureStatus::Completed, $fixture->getStatus());
        $this->assertSame($battle, $fixture->getBattle());
    }
}
