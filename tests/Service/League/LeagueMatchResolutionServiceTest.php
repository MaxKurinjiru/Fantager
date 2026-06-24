<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\Combat\Battle;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Enum\BattleResult;
use App\Enum\LeagueFixtureStatus;
use App\Enum\MatchType;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueStandingRepository;
use App\Service\Combat\MatchSimulatorInterface;
use App\Service\League\LeagueFixtureCompletionService;
use App\Service\League\LeagueMatchResolutionService;
use App\Service\League\LeagueStandingService;
use App\Service\Team\FanClubService;
use App\Service\Team\TeamMoraleReputationService;
use App\Service\Team\TeamRosterService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\ValueObject\Combat\MatchOutcome;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LeagueMatchResolutionServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&LeagueFixtureRepository */
    private $fixtureRepository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&LeagueStandingRepository */
    private $standingRepository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamRosterService */
    private $teamRosterService;
    /** @var \PHPUnit\Framework\MockObject\MockObject&MatchSimulatorInterface */
    private $matchSimulator;
    /** @var \PHPUnit\Framework\MockObject\MockObject&LeagueFixtureCompletionService */
    private $fixtureCompletionService;
    /** @var \PHPUnit\Framework\MockObject\MockObject&FanClubService */
    private $fanClubService;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamMoraleReputationService */
    private $teamMoraleReputationService;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamChronicleService */
    private $teamChronicleService;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $em;
    private LeagueMatchResolutionService $service;

    protected function setUp(): void
    {
        $this->fixtureRepository = $this->createMock(LeagueFixtureRepository::class);
        $this->standingRepository = $this->createMock(LeagueStandingRepository::class);
        $this->teamRosterService = $this->createMock(TeamRosterService::class);
        $this->matchSimulator = $this->createMock(MatchSimulatorInterface::class);
        $this->fixtureCompletionService = $this->createMock(LeagueFixtureCompletionService::class);
        $this->fanClubService = $this->createMock(FanClubService::class);
        $this->teamMoraleReputationService = $this->createMock(TeamMoraleReputationService::class);
        $this->teamChronicleService = $this->createMock(TeamChronicleService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new LeagueMatchResolutionService(
            $this->fixtureRepository,
            $this->standingRepository,
            $this->teamRosterService,
            $this->matchSimulator,
            new LeagueStandingService(),
            $this->fixtureCompletionService,
            $this->fanClubService,
            $this->teamMoraleReputationService,
            $this->teamChronicleService,
            $this->em,
        );
    }

    public function testResolveFixtureUsesSimulatorWhenBothTeamsEligible(): void
    {
        [$fixture, $homeStanding, $awayStanding] = $this->createFixtureContext();

        $this->teamRosterService->method('countCombatReadyHeroes')->willReturn(6);
        $this->standingRepository->method('findOneBy')->willReturnMap([
            [['group' => $fixture->getGroup(), 'team' => $fixture->getHomeTeam()], $homeStanding],
            [['group' => $fixture->getGroup(), 'team' => $fixture->getAwayTeam()], $awayStanding],
        ]);
        $this->matchSimulator->method('simulate')->willReturn(new MatchOutcome(2, 1));

        $this->em->expects($this->once())->method('persist')->with($this->callback(
            function (Battle $battle): bool {
                return 2 === $battle->getScoreA()
                    && 1 === $battle->getScoreB()
                    && BattleResult::WinA === $battle->getResult()
                    && MatchType::League === $battle->getMatchType()
                    && 'stub_random' === $battle->getCombatLog()['simulator'];
            }
        ));

        $this->fixtureCompletionService->expects($this->once())->method('complete');

        $this->fanClubService->expects($this->once())
            ->method('applyFixtureResult')
            ->with($fixture->getHomeTeam(), $fixture->getAwayTeam(), 2, 1);

        $result = $this->service->resolveFixture($fixture, new \DateTimeImmutable('2026-06-17 18:00:00'));

        $this->assertSame(2, $result['home_score']);
        $this->assertSame(1, $result['away_score']);
        $this->assertFalse($result['is_forfeit']);
        $this->assertSame(1, $homeStanding->getWins());
        $this->assertSame(3, $homeStanding->getPoints());
        $this->assertSame(1, $awayStanding->getLosses());
    }

    public function testResolveFixtureAppliesHomeForfeitWhenAwayUnderstaffed(): void
    {
        [$fixture, $homeStanding, $awayStanding] = $this->createFixtureContext();

        $this->teamRosterService->method('countCombatReadyHeroes')->willReturnMap([
            [$fixture->getHomeTeam(), 8],
            [$fixture->getAwayTeam(), 4],
        ]);
        $this->standingRepository->method('findOneBy')->willReturnMap([
            [['group' => $fixture->getGroup(), 'team' => $fixture->getHomeTeam()], $homeStanding],
            [['group' => $fixture->getGroup(), 'team' => $fixture->getAwayTeam()], $awayStanding],
        ]);
        $this->matchSimulator->expects($this->never())->method('simulate');

        $this->em->expects($this->once())->method('persist')->with($this->callback(
            function (Battle $battle): bool {
                return 3 === $battle->getScoreA()
                    && 0 === $battle->getScoreB()
                    && 'forfeit' === $battle->getCombatLog()['simulator'];
            }
        ));

        $result = $this->service->resolveFixture($fixture, new \DateTimeImmutable('2026-06-17 18:00:00'));

        $this->assertTrue($result['is_forfeit']);
        $this->assertSame(3, $result['home_score']);
        $this->assertSame(0, $result['away_score']);
        $this->assertSame(1, $homeStanding->getWins());
        $this->assertSame(1, $awayStanding->getLosses());
    }

    public function testResolveFixtureAppliesDrawForfeitWhenBothUnderstaffed(): void
    {
        [$fixture, $homeStanding, $awayStanding] = $this->createFixtureContext();

        $this->teamRosterService->method('countCombatReadyHeroes')->willReturn(3);
        $this->standingRepository->method('findOneBy')->willReturnMap([
            [['group' => $fixture->getGroup(), 'team' => $fixture->getHomeTeam()], $homeStanding],
            [['group' => $fixture->getGroup(), 'team' => $fixture->getAwayTeam()], $awayStanding],
        ]);

        $result = $this->service->resolveFixture($fixture, new \DateTimeImmutable('2026-06-17 18:00:00'));

        $this->assertTrue($result['is_forfeit']);
        $this->assertSame(0, $result['home_score']);
        $this->assertSame(0, $result['away_score']);
        $this->assertSame(1, $homeStanding->getDraws());
        $this->assertSame(1, $awayStanding->getDraws());
        $this->assertSame(1, $homeStanding->getPoints());
    }

    public function testProcessLeagueMatchTickResolvesAllScheduledFixtures(): void
    {
        $kingdom = new Kingdom();
        $scheduledAt = new \DateTimeImmutable('2026-06-17 18:00:00');
        $fixture = new LeagueFixture();

        $this->fixtureRepository
            ->expects($this->once())
            ->method('findScheduledFixturesAtTime')
            ->with($kingdom, $scheduledAt)
            ->willReturn([$fixture]);

        $partial = $this->createPartialMock(LeagueMatchResolutionService::class, ['resolveFixture']);
        $partial->__construct(
            $this->fixtureRepository,
            $this->standingRepository,
            $this->teamRosterService,
            $this->matchSimulator,
            new LeagueStandingService(),
            $this->fixtureCompletionService,
            $this->fanClubService,
            $this->teamMoraleReputationService,
            $this->teamChronicleService,
            $this->em,
        );
        $partial->expects($this->once())
            ->method('resolveFixture')
            ->with($fixture, $scheduledAt)
            ->willReturn(['fixture_id' => 42]);

        $results = $partial->processLeagueMatchTick($kingdom, $scheduledAt);

        $this->assertCount(1, $results);
        $this->assertSame(42, $results[0]['fixture_id']);
    }

    /**
     * @return array{0: LeagueFixture, 1: LeagueStanding, 2: LeagueStanding}
     */
    private function createFixtureContext(): array
    {
        $kingdom = new Kingdom();
        $homeTeam = new Team();
        $homeTeam->setKingdom($kingdom);
        $awayTeam = new Team();
        $awayTeam->setKingdom($kingdom);

        $group = new LeagueGroup();
        $fixture = new LeagueFixture();
        $fixture->setGroup($group);
        $fixture->setHomeTeam($homeTeam);
        $fixture->setAwayTeam($awayTeam);
        $fixture->setScheduledAt(new \DateTimeImmutable('2026-06-17 18:00:00'));
        $fixture->setStatus(LeagueFixtureStatus::Scheduled);

        $homeStanding = new LeagueStanding();
        $homeStanding->setGroup($group);
        $homeStanding->setTeam($homeTeam);

        $awayStanding = new LeagueStanding();
        $awayStanding->setGroup($group);
        $awayStanding->setTeam($awayTeam);

        return [$fixture, $homeStanding, $awayStanding];
    }
}
