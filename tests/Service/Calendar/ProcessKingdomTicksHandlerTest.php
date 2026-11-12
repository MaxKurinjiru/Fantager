<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Message\ExecuteSingleTickHandler;
use App\Message\ExecuteSingleTickMessage;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Training\TrainingService;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Calendar\KingdomTickOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class ProcessKingdomTicksHandlerTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Repository\Kingdom\KingdomTickLogRepository */
    private $tickLogRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Repository\Hero\HeroRepository */
    private $heroRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Training\TrainingService */
    private $trainingServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\ArenaRevenueService */
    private $arenaRevenueServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Team\FanClubService */
    private $fanClubServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Team\TeamMoraleReputationService */
    private $teamMoraleReputationServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Headquarters\HeadquartersService */
    private $hqServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\FinancialCrisisService */
    private $financialCrisisServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\RoyalTreasuryService */
    private $royalTreasuryServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\TeamPayrollService */
    private $teamPayrollServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Doctrine\ORM\EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Psr\Log\LoggerInterface */
    private $loggerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\League\SeasonTransitionService */
    private $seasonTransitionServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Auth\PlayerInactivityService */
    private $playerInactivityServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Formation\FixtureFormationService */
    private $fixtureFormationServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\League\LeagueMatchResolutionService */
    private $leagueMatchResolutionServiceMock;
    private KingdomTickOrchestrator&MockObject $orchestratorMock;
    private \App\Service\Calendar\TickScheduleCalculator&MockObject $scheduleCalculatorMock;
    private \Doctrine\DBAL\Connection&MockObject $connectionMock;
    private LoggerInterface&MockObject $metricsLoggerMock;
    private ExecuteSingleTickHandler $handler;

    protected function setUp(): void
    {
        $this->tickLogRepositoryMock = $this->createMock(KingdomTickLogRepository::class);
        $this->heroRepositoryMock = $this->createMock(HeroRepository::class);
        $heroQbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $heroQueryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $heroQbMock->method('join')->willReturnSelf();
        $heroQbMock->method('where')->willReturnSelf();
        $heroQbMock->method('andWhere')->willReturnSelf();
        $heroQbMock->method('setParameter')->willReturnSelf();
        $heroQbMock->method('getQuery')->willReturn($heroQueryMock);
        $heroQueryMock->method('getResult')->willReturn([]);
        $this->heroRepositoryMock->method('createQueryBuilder')->willReturn($heroQbMock);

        $this->trainingServiceMock = $this->createMock(TrainingService::class);
        $this->arenaRevenueServiceMock = $this->createMock(ArenaRevenueService::class);
        $this->fanClubServiceMock = $this->createMock(\App\Service\Team\FanClubService::class);
        $this->teamMoraleReputationServiceMock = $this->createMock(\App\Service\Team\TeamMoraleReputationService::class);
        $this->hqServiceMock = $this->createMock(\App\Service\Headquarters\HeadquartersService::class);
        $this->financialCrisisServiceMock = $this->createMock(\App\Service\Economy\FinancialCrisisService::class);
        $this->royalTreasuryServiceMock = $this->createMock(\App\Service\Economy\RoyalTreasuryService::class);
        $this->teamPayrollServiceMock = $this->createMock(\App\Service\Economy\TeamPayrollService::class);
        $this->playerInactivityServiceMock = $this->createMock(\App\Service\Auth\PlayerInactivityService::class);
        $this->fixtureFormationServiceMock = $this->createMock(\App\Service\Formation\FixtureFormationService::class);
        $this->leagueMatchResolutionServiceMock = $this->createMock(\App\Service\League\LeagueMatchResolutionService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->connectionMock = $this->createMock(\Doctrine\DBAL\Connection::class);
        $this->connectionMock->method('fetchOne')->willReturn(1);
        $this->entityManagerMock->method('getConnection')->willReturn($this->connectionMock);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->metricsLoggerMock = $this->createMock(LoggerInterface::class);
        $this->seasonTransitionServiceMock = $this->createMock(\App\Service\League\SeasonTransitionService::class);
        $this->orchestratorMock = $this->createMock(KingdomTickOrchestrator::class);
        $this->scheduleCalculatorMock = $this->createMock(\App\Service\Calendar\TickScheduleCalculator::class);

        $this->handler = new ExecuteSingleTickHandler(
            $this->tickLogRepositoryMock,
            $this->heroRepositoryMock,
            $this->trainingServiceMock,
            $this->arenaRevenueServiceMock,
            $this->fanClubServiceMock,
            $this->teamMoraleReputationServiceMock,
            $this->hqServiceMock,
            $this->financialCrisisServiceMock,
            $this->royalTreasuryServiceMock,
            $this->teamPayrollServiceMock,
            $this->playerInactivityServiceMock,
            $this->fixtureFormationServiceMock,
            $this->leagueMatchResolutionServiceMock,
            $this->createMock(\App\Service\FriendlyMatch\FriendlyMatchResolutionService::class),
            $this->entityManagerMock,
            $this->loggerMock,
            $this->seasonTransitionServiceMock,
            $this->createMock(\App\Service\TeamChronicle\TeamChronicleService::class),
            new \App\Service\Calendar\TickClock(),
            $this->createMock(\App\Service\Team\NpcSimulationService::class),
            $this->orchestratorMock,
            $this->createMock(\App\Service\Hero\HeroMasteryService::class),
            $this->scheduleCalculatorMock,
            $this->metricsLoggerMock,
        );
    }

    private function setupQueryBuilderMock(int $rowsUpdated): void
    {
        $queryBuilderMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManagerMock
            ->method('createQueryBuilder')
            ->willReturn($queryBuilderMock);

        $queryBuilderMock->method('update')->willReturnSelf();
        $queryBuilderMock->method('set')->willReturnSelf();
        $queryBuilderMock->method('where')->willReturnSelf();
        $queryBuilderMock->method('andWhere')->willReturnSelf();
        $queryBuilderMock->method('setParameter')->willReturnSelf();
        $queryBuilderMock->method('getQuery')->willReturn($queryMock);

        $queryMock->method('execute')->willReturn($rowsUpdated);
    }

    public function testHandlerHaltsOnFailure(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');

        $team = new \App\Entity\Team\Team();
        $team->setKingdom($kingdom);

        $log = new KingdomTickLog();
        $log->setKingdom($kingdom);
        $log->setTickType(TickType::WeeklyTraining);
        $log->setScheduledAt(new \DateTimeImmutable('2026-06-12T10:00:00Z'));
        $log->setStatus('pending');
        $log->setTeam($team);

        $this->tickLogRepositoryMock
            ->method('find')
            ->willReturn($log);

        // Simulating training tick failure
        $this->trainingServiceMock
            ->method('processTrainingTick')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        $this->entityManagerMock
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('rollback');

        $this->entityManagerMock->expects($this->never())->method('commit');
        $this->entityManagerMock->expects($this->once())->method('clear');
        $this->entityManagerMock->expects($this->never())->method('flush');
        $this->connectionMock->expects($this->once())->method('executeStatement')
            ->with(
                $this->stringContains('UPDATE kingdom_tick_log'),
                $this->callback(static fn (array $parameters): bool => 'failed' === $parameters[0]
                    && str_contains($parameters[2], 'Database connection lost')
                    && 1 === $parameters[3]
                    && 'processing' === $parameters[4]),
            )->willReturn(1);

        $this->setupQueryBuilderMock(1);

        // Orchestrator must NEVER be called on failure to halt execution
        $this->orchestratorMock
            ->expects($this->never())
            ->method('orchestrate');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection lost');

        $this->handler->__invoke(new ExecuteSingleTickMessage(1));
    }

    public function testHandlerProcessesSingleTickAndTriggersOrchestrator(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');

        $team = new \App\Entity\Team\Team();
        $team->setKingdom($kingdom);

        $log = new KingdomTickLog();
        $log->setKingdom($kingdom);
        $log->setTickType(TickType::WeeklyTraining);
        $log->setScheduledAt(new \DateTimeImmutable('2026-06-12T10:00:00Z'));
        $log->setStatus('pending');
        $log->setTeam($team);

        $this->tickLogRepositoryMock
            ->method('find')
            ->willReturn($log);

        // First transaction starts, training tick succeeds, transaction commits
        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('beginTransaction');

        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('commit');

        $this->setupQueryBuilderMock(1);

        // Orchestrator MUST be called once on success with this kingdom
        $this->orchestratorMock
            ->expects($this->once())
            ->method('orchestrate')
            ->willReturnCallback(static function (Kingdom $actualKingdom) use ($kingdom): void {
                TestCase::assertSame($kingdom, $actualKingdom);
            });

        // Run the handler
        $message = new ExecuteSingleTickMessage(1);
        $this->handler->__invoke($message);

        // Assert log is set to completed
        $this->assertSame('completed', $log->getStatus());
    }

    public function testMatchKickoffWaitsForOrchestratorToStartCombat(): void
    {
        $kingdom = (new Kingdom())->setName('Test Kingdom');
        $battle = (new \App\Entity\Combat\Battle())->setStatus(\App\Enum\BattleStatus::Simulating);
        $fixture = (new \App\Entity\League\LeagueFixture())->setBattle($battle);
        $scheduledAt = new \DateTimeImmutable('2026-06-12 18:00:00 UTC');
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
            ->setScheduledAt($scheduledAt)->setFixture($fixture)->setStatus('dispatched');
        $this->tickLogRepositoryMock->method('find')->willReturn($log);
        $this->leagueMatchResolutionServiceMock->expects($this->once())->method('resolveFixture')
            ->with($fixture, $scheduledAt, false)->willReturn([]);
        $this->orchestratorMock->expects($this->once())->method('orchestrate')->with($kingdom);
        $this->setupQueryBuilderMock(1);

        $this->handler->__invoke(new ExecuteSingleTickMessage(1));

        $this->assertSame('awaiting_combat', $log->getStatus());
    }

    public function testSynchronousNonMatchTickDoesNotDispatchAsyncWork(): void
    {
        $kingdom = (new Kingdom())->setName('Test Kingdom');
        $team = (new \App\Entity\Team\Team())->setKingdom($kingdom);
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::WeeklyTraining)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-11 10:00:00 UTC'))->setTeam($team)->setStatus('pending');
        $this->tickLogRepositoryMock->method('find')->willReturn($log);
        $this->orchestratorMock->expects($this->never())->method('orchestrate');
        $this->setupQueryBuilderMock(1);

        $this->handler->__invoke(new ExecuteSingleTickMessage(1), true);

        $this->assertSame('completed', $log->getStatus());
    }

    public function testAsyncOptimisticConflictRequeuesTheRolledBackTick(): void
    {
        $kingdom = (new Kingdom())->setName('Test Kingdom');
        $team = (new \App\Entity\Team\Team())->setKingdom($kingdom);
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::WeeklyTraining)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-11 10:00:00 UTC'))->setTeam($team)->setStatus('pending');
        $this->tickLogRepositoryMock->method('find')->willReturn($log);
        $this->trainingServiceMock->method('processTrainingTick')->willThrowException(new \Doctrine\ORM\OptimisticLockException('stale', null));
        $this->entityManagerMock->expects($this->once())->method('rollback');
        $this->orchestratorMock->expects($this->once())->method('retryOptimisticConflict')->with(1)->willReturn(true);
        $this->orchestratorMock->expects($this->never())->method('orchestrate');
        $this->setupQueryBuilderMock(1);

        $this->handler->__invoke(new ExecuteSingleTickMessage(1));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('optimisticConflictFailureModes')]
    public function testOptimisticConflictEventuallyFails(bool $synchronous): void
    {
        $kingdom = (new Kingdom())->setName('Test Kingdom');
        $team = (new \App\Entity\Team\Team())->setKingdom($kingdom);
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::WeeklyTraining)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-11 10:00:00 UTC'))->setTeam($team)->setStatus('pending');
        $this->tickLogRepositoryMock->method('find')->willReturn($log);
        $this->trainingServiceMock->method('processTrainingTick')->willThrowException(new \Doctrine\ORM\OptimisticLockException('stale', null));
        $this->orchestratorMock->expects($synchronous ? $this->never() : $this->once())->method('retryOptimisticConflict')->willReturn(false);
        $this->connectionMock->expects($this->once())->method('executeStatement')
            ->with($this->stringContains('UPDATE kingdom_tick_log'), $this->callback(static fn (array $parameters): bool => 'failed' === $parameters[0]))
            ->willReturn(1);
        $this->setupQueryBuilderMock(1);

        $this->expectException(\Doctrine\ORM\OptimisticLockException::class);
        $this->handler->__invoke(new ExecuteSingleTickMessage(1), $synchronous);
    }

    /** @return iterable<string, array{bool}> */
    public static function optimisticConflictFailureModes(): iterable
    {
        yield 'asynchronous retry budget exhausted' => [false];
        yield 'synchronous execution' => [true];
    }

    public function testKingdomDailyResetExecutesItsOwnPhase(): void
    {
        $kingdom = (new Kingdom())->setName('Test Kingdom');
        $scheduledAt = new \DateTimeImmutable('2026-06-11 00:00:00 UTC');
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
            ->setScheduledAt($scheduledAt)->setStatus('pending');
        $this->tickLogRepositoryMock->method('find')->willReturn($log);
        $this->orchestratorMock->expects($this->once())->method('processDailyKingdomPhase')->with($kingdom, $log);
        $this->metricsLoggerMock->expects($this->once())->method('info')->with('Tick execution metrics.', $this->callback(
            static fn (array $context): bool => 1 === $context['tick_id'] && 'daily_reset' === $context['tick_type']
                && $context['duration_ms'] >= 0 && array_key_exists('memory_delta_bytes', $context),
        ));
        $this->setupQueryBuilderMock(1);

        $this->handler->__invoke(new ExecuteSingleTickMessage(1), true);

        $this->assertSame('completed', $log->getStatus());
    }

    public function testSeasonTransitionSchedulesNewFixturesThroughRequestedHorizon(): void
    {
        $kingdom = (new Kingdom())->setName('Test Kingdom')->setTimezone('UTC');
        $transitionTime = new \DateTimeImmutable('2026-03-20 19:00:00 UTC');
        $matchTime = new \DateTimeImmutable('2026-03-24 18:00:00 UTC');
        $nextTransitionTime = new \DateTimeImmutable('2026-06-05 19:00:00 UTC');
        $horizon = new \DateTimeImmutable('2026-06-06 04:00:00 UTC');
        $startDate = new \DateTimeImmutable('2026-03-23 00:00:00 UTC');
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::SeasonTransition)
            ->setScheduledAt($transitionTime)->setStatus('pending');
        $fixture = new \App\Entity\League\LeagueFixture();
        $existingFixture = new \App\Entity\League\LeagueFixture();
        $existing = new KingdomTickLog();
        $season = (new \App\Entity\League\LeagueSeason())->setStartDate($startDate);
        $seasonRepository = $this->createMock(\App\Repository\League\LeagueSeasonRepository::class);
        $seasonRepository->method('findOneBy')->willReturn($season);
        $fixtureRepository = $this->createMock(\App\Repository\League\LeagueFixtureRepository::class);
        $fixtureRepository->expects($this->once())->method('findScheduledFixturesAtTime')
            ->with($kingdom, $matchTime)->willReturn([$fixture, $existingFixture]);
        $this->entityManagerMock->method('getRepository')->willReturnCallback(static fn (string $class) => match ($class) {
            \App\Entity\League\LeagueSeason::class => $seasonRepository,
            \App\Entity\League\LeagueFixture::class => $fixtureRepository,
            default => throw new \LogicException('Unexpected repository '.$class),
        });
        $this->tickLogRepositoryMock->method('find')->willReturn($log);
        $kingdom->setTickPlanningHorizon($horizon);
        $this->tickLogRepositoryMock->method('findOneBy')->willReturnCallback(
            static fn (array $criteria): ?KingdomTickLog => $criteria['fixture'] === $existingFixture ? $existing : null,
        );
        $this->scheduleCalculatorMock->expects($this->once())->method('generateOccurrences')
            ->with($transitionTime, $horizon, 'UTC', $startDate)
            ->willReturn([
                ['type' => TickType::LeagueMatch, 'time' => $matchTime],
                ['type' => TickType::SeasonTransition, 'time' => $nextTransitionTime],
                ['type' => TickType::DailyReset, 'time' => $horizon],
            ]);
        /** @var list<KingdomTickLog> $persisted */
        $persisted = [];
        $this->entityManagerMock->expects($this->exactly(2))->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                self::assertInstanceOf(KingdomTickLog::class, $entity);
                $persisted[] = $entity;
            });
        $this->setupQueryBuilderMock(1);

        $this->handler->__invoke(new ExecuteSingleTickMessage(1), true);

        $this->assertSame($fixture, $persisted[0]->getFixture());
        $this->assertSame($matchTime, $persisted[0]->getScheduledAt());
        $this->assertSame(TickType::SeasonTransition, $persisted[1]->getTickType());
        $this->assertSame($nextTransitionTime, $persisted[1]->getScheduledAt());
    }
}
