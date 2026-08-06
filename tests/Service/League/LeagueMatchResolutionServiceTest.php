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
use App\Service\Combat\CombatMatchRequestBuilder;
use App\Service\Combat\CombatSeedGenerator;
use App\Service\Combat\CombatEngine;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Service\Config\RaceConfig;
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
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Repository\Formation\FormationRepository */
    private $formationRepository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&CombatMatchRequestBuilder */
    private $requestBuilder;
    /** @var CombatSeedGenerator */
    private $seedGenerator;
    /** @var \PHPUnit\Framework\MockObject\MockObject&CombatEngine */
    private $combatEngine;
    /** @var \PHPUnit\Framework\MockObject\MockObject&MessageBusInterface */
    private $messageBus;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Graveyard\GraveyardService */
    private $graveyardService;
    /** @var \PHPUnit\Framework\MockObject\MockObject&RaceConfig */
    private $raceConfig;
    private LeagueMatchResolutionService $service;

    protected function setUp(): void
    {
        $this->fixtureRepository = $this->createMock(LeagueFixtureRepository::class);
        $this->standingRepository = $this->createMock(LeagueStandingRepository::class);
        $this->teamRosterService = $this->createMock(TeamRosterService::class);
        $this->fixtureCompletionService = $this->createMock(LeagueFixtureCompletionService::class);
        $this->fanClubService = $this->createMock(FanClubService::class);
        $this->teamMoraleReputationService = $this->createMock(TeamMoraleReputationService::class);
        $this->teamChronicleService = $this->createMock(TeamChronicleService::class);
        $this->formationRepository = $this->createMock(\App\Repository\Formation\FormationRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->requestBuilder = $this->createMock(CombatMatchRequestBuilder::class);
        $this->seedGenerator = new CombatSeedGenerator();
        $this->combatEngine = $this->createMock(CombatEngine::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->graveyardService = $this->createMock(\App\Service\Graveyard\GraveyardService::class);
        $this->raceConfig = $this->createMock(RaceConfig::class);

        $this->service = new LeagueMatchResolutionService(
            $this->fixtureRepository,
            $this->standingRepository,
            $this->teamRosterService,
            new LeagueStandingService(),
            $this->fixtureCompletionService,
            $this->fanClubService,
            $this->teamMoraleReputationService,
            $this->teamChronicleService,
            $this->createMock(\App\Service\Hero\HeroMasteryService::class),
            $this->createMock(\App\Service\Hero\HeroChronicleService::class),
            $this->formationRepository,
            $this->em,
            $this->requestBuilder,
            $this->seedGenerator,
            $this->combatEngine,
            $this->messageBus,
            $this->graveyardService,
            $this->raceConfig,
            $this->createMock(\App\Service\Notification\NotificationHelper::class),
        );
    }


    public function testResolveFixtureInitializesSimulatingBattle(): void
    {
        [$fixture, $homeStanding, $awayStanding] = $this->createFixtureContext();
        $this->teamRosterService->method('countCombatReadyHeroes')->willReturn(6);

        $formation = new \App\Entity\Formation\Formation();
        $this->formationRepository->method('findOneBy')->willReturn($formation);

        $sideA = new \App\ValueObject\Combat\CombatSide(1, 101, \App\Enum\FormationApproach::Balanced, $this->buildCombatants(100));
        $sideB = new \App\ValueObject\Combat\CombatSide(2, 102, \App\Enum\FormationApproach::Balanced, $this->buildCombatants(200));
        $realRequest = new \App\ValueObject\Combat\CombatMatchRequest($sideA, $sideB, MatchType::League, 42);
        $this->requestBuilder->method('fromFormations')->willReturn($realRequest);
        
        $this->combatEngine->method('initializeRunState')->willReturn([
            'seed' => 42,
            'status' => 'simulating',
            'events' => [],
        ]);

        $mockQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $mockQuery->method('getSingleScalarResult')->willReturn(1);
        
        $mockQb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $mockQb->method('select')->willReturnSelf();
        $mockQb->method('from')->willReturnSelf();
        $mockQb->method('join')->willReturnSelf();
        $mockQb->method('where')->willReturnSelf();
        $mockQb->method('andWhere')->willReturnSelf();
        $mockQb->method('setParameter')->willReturnSelf();
        $mockQb->method('getQuery')->willReturn($mockQuery);

        $this->em->method('createQueryBuilder')->willReturn($mockQb);

        $calledBattle = null;
        $this->em->expects($this->once())->method('persist')->willReturnCallback(function ($battle) use (&$calledBattle) {
            $calledBattle = $battle;
        });

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $result = $this->service->resolveFixture($fixture, new \DateTimeImmutable('2026-06-17 18:00:00'));

        $this->assertInstanceOf(Battle::class, $calledBattle);
        $this->assertSame(\App\Enum\BattleStatus::Simulating, $calledBattle->getStatus());
        $this->assertSame(0, $calledBattle->getScoreA());
        $this->assertSame(0, $calledBattle->getScoreB());
        
        $this->assertSame(0, $result['home_score']);
        $this->assertSame(0, $result['away_score']);
        $this->assertFalse($result['is_forfeit']);
        $this->assertSame($calledBattle->getId(), $result['battle_id']);
    }

    public function testCompleteBattleRunsPostMatchSideEffects(): void
    {
        [$fixture, $homeStanding, $awayStanding] = $this->createFixtureContext();
        $battle = new Battle();
        $battle->setKingdom($fixture->getHomeTeam()->getKingdom());
        $battle->setMatchType(MatchType::League);
        $battle->setTeamA($fixture->getHomeTeam());
        $battle->setTeamB($fixture->getAwayTeam());
        $battle->setScoreA(2);
        $battle->setScoreB(1);
        $battle->setResult(BattleResult::WinA);
        $battle->setCombatLog([
            'seed' => 42,
            'events' => [],
        ]);

        $mockRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $mockRepo->method('findOneBy')->willReturn($fixture);
        $this->em->method('getRepository')->willReturnMap([
            [LeagueFixture::class, $mockRepo],
        ]);

        $this->standingRepository->method('findOneBy')->willReturnMap([
            [['group' => $fixture->getGroup(), 'team' => $fixture->getHomeTeam()], $homeStanding],
            [['group' => $fixture->getGroup(), 'team' => $fixture->getAwayTeam()], $awayStanding],
        ]);

        $calledParams = null;
        $this->fanClubService->expects($this->once())
            ->method('applyFixtureResult')
            ->willReturnCallback(function ($home, $away, $scoreHome, $scoreAway) use (&$calledParams) {
                $calledParams = [$home, $away, $scoreHome, $scoreAway];
            });

        $this->fixtureCompletionService->expects($this->once())->method('complete')->with($fixture, $battle);

        $this->service->completeBattle($battle);

        $this->assertNotNull($calledParams);
        $this->assertSame($fixture->getHomeTeam(), $calledParams[0]);
        $this->assertSame($fixture->getAwayTeam(), $calledParams[1]);
        $this->assertSame(2, $calledParams[2]);
        $this->assertSame(1, $calledParams[3]);

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
        // No simulator call expected as it is handled by the forfeit resolution

        $calledBattle = null;
        $this->em->expects($this->once())->method('persist')->willReturnCallback(function ($battle) use (&$calledBattle) {
            $calledBattle = $battle;
        });

        $result = $this->service->resolveFixture($fixture, new \DateTimeImmutable('2026-06-17 18:00:00'));

        $this->assertInstanceOf(Battle::class, $calledBattle);
        $this->assertSame(3, $calledBattle->getScoreA());
        $this->assertSame(0, $calledBattle->getScoreB());
        $this->assertSame('forfeit', $calledBattle->getCombatLog()['simulator']);

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
            ->willReturnCallback(function (Kingdom $k, \DateTimeImmutable $dt) use ($kingdom, $scheduledAt, $fixture) {
                $this->assertSame($kingdom, $k);
                $this->assertSame($scheduledAt, $dt);
                return [$fixture];
            });

        $partial = $this->createPartialMock(LeagueMatchResolutionService::class, ['resolveFixture']);
        $partial->__construct(
            $this->fixtureRepository,
            $this->standingRepository,
            $this->teamRosterService,
            new LeagueStandingService(),
            $this->fixtureCompletionService,
            $this->fanClubService,
            $this->teamMoraleReputationService,
            $this->teamChronicleService,
            $this->createMock(\App\Service\Hero\HeroMasteryService::class),
            $this->createMock(\App\Service\Hero\HeroChronicleService::class),
            $this->formationRepository,
            $this->em,
            $this->requestBuilder,
            $this->seedGenerator,
            $this->combatEngine,
            $this->messageBus,
            $this->createMock(\App\Service\Graveyard\GraveyardService::class),
            $this->createMock(RaceConfig::class),
            $this->createMock(\App\Service\Notification\NotificationHelper::class),
        );

        $partial->expects($this->once())
            ->method('resolveFixture')
            ->willReturnCallback(function (LeagueFixture $f, \DateTimeImmutable $dt) use ($fixture, $scheduledAt) {
                $this->assertSame($fixture, $f);
                $this->assertSame($scheduledAt, $dt);
                return ['fixture_id' => 42];
            });

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

        $season = new \App\Entity\League\LeagueSeason();
        $tier = new \App\Entity\League\LeagueTier();
        $tier->setSeason($season);

        $group = new LeagueGroup();
        $group->setTier($tier);

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

    /**
     * @return list<\App\ValueObject\Combat\CombatantSnapshot>
     */
    private function buildCombatants(int $heroIdBase): array
    {
        $combatants = [];
        $derived = new \App\ValueObject\Combat\DerivedCombatStats(
            100, 100, 10, 10, 10, 0.1, 10, 0.1, 10, 80.0, 10.0, 5.0
        );
        foreach (\App\Enum\FormationPosition::cases() as $i => $position) {
            $combatants[] = new \App\ValueObject\Combat\CombatantSnapshot(
                $heroIdBase + $i,
                'Hero ' . ($heroIdBase + $i),
                $position,
                \App\Enum\Race::Human,
                5,
                100,
                0,
                50,
                $derived,
            );
        }
        return $combatants;
    }

    public function testCompleteBattleProcessesCombatDeathAndGraveyard(): void
    {
        [$fixture, $homeStanding, $awayStanding] = $this->createFixtureContext();

        $hero = new \App\Entity\Hero\Hero();
        $hero->setTeam($fixture->getHomeTeam());
        $hero->setRace(\App\Enum\Race::Human);
        $hero->setAgeRaw(3000); // 300 years old (100% death chance)

        $battle = new Battle();
        $battle->setKingdom($fixture->getHomeTeam()->getKingdom());
        $battle->setMatchType(MatchType::League);
        $battle->setTeamA($fixture->getHomeTeam());
        $battle->setTeamB($fixture->getAwayTeam());
        $battle->setResult(BattleResult::WinA);
        $battle->setScoreA(2);
        $battle->setScoreB(1);
        $battle->setCurrentRound(10);
        $battle->setCombatLog([
            'result' => [
                'killed_hero_ids' => [77],
                'item_hit_counts' => [],
            ],
        ]);

        $fixtureRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $fixtureRepo->expects($this->once())->method('findOneBy')->with(['battle' => $battle])->willReturn($fixture);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($fixtureRepo) {
            if (LeagueFixture::class === $class) {
                return $fixtureRepo;
            }
            return $this->createMock(\Doctrine\ORM\EntityRepository::class);
        });

        $this->standingRepository->method('findOneBy')->willReturnMap([
            [['group' => $fixture->getGroup(), 'team' => $fixture->getHomeTeam()], $homeStanding],
            [['group' => $fixture->getGroup(), 'team' => $fixture->getAwayTeam()], $awayStanding],
        ]);

        $this->em->expects($this->once())->method('find')->with(\App\Entity\Hero\Hero::class, 77)->willReturn($hero);
        $this->raceConfig->method('isAtOrAboveMortalityThreshold')->willReturn(true);
        $this->raceConfig->method('getMortalityThreshold')->willReturn(50);

        $this->graveyardService->expects($this->once())->method('prepareCombatDeath')->with($hero);
        $this->graveyardService->expects($this->once())
            ->method('recordMemorial')
            ->with($hero, $fixture->getHomeTeam(), \App\Enum\MemorialCause::CombatDeath);

        $this->service->completeBattle($battle);

        $this->assertSame(\App\Enum\HeroStatus::Dead, $hero->getStatus());
    }
}
