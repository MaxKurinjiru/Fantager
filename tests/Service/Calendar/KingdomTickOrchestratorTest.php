<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueSeason;
use App\Entity\Team\Team;
use App\Enum\TickType;
use App\Message\CombatWave;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\League\SeasonTransitionService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Calendar\TickClock;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

#[AllowMockObjectsWithoutExpectations]
class KingdomTickOrchestratorTest extends TestCase
{
    private KingdomTickOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->orchestrator = new KingdomTickOrchestrator(
            $this->createMock(KingdomTickLogRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(\App\Service\League\SeasonTransitionService::class),
            $this->createMock(MarketplaceService::class),
            new TickClock(),
        );
    }

    public function testWeeklyResetWavePaysTreasuryBeforeTeamCosts(): void
    {
        $kingdomTick = $this->weeklyResetLog(null);
        $teamTick = $this->weeklyResetLog(new Team());

        $wave = $this->orchestrator->deferTeamWeeklyResetUntilTreasuryFinishes([$teamTick, $kingdomTick]);

        $this->assertSame([$kingdomTick], $wave);
    }

    public function testDailyKingdomPhasePrecedesTeamResets(): void
    {
        $kingdomTick = (new KingdomTickLog())->setTickType(TickType::DailyReset);
        $teamTick = (new KingdomTickLog())->setTickType(TickType::DailyReset)->setTeam(new Team());

        $this->assertTrue(TickType::DailyReset->createsKingdomScopedLogs());
        $this->assertSame([$kingdomTick], $this->orchestrator->deferTeamDailyResetUntilKingdomFinishes([$teamTick, $kingdomTick]));
        $this->assertSame([$teamTick], $this->orchestrator->deferTeamDailyResetUntilKingdomFinishes([$teamTick]));
    }

    public function testTickPhaseRejectsMultipleScopes(): void
    {
        $tick = (new KingdomTickLog())->setTeam(new Team());

        $this->expectException(\InvalidArgumentException::class);
        $tick->setFixture(new \App\Entity\League\LeagueFixture());
    }

    public function testStaleSimulatingCombatIsRequeuedFromPersistedRound(): void
    {
        $kingdom = (new Kingdom())->setName('Test')->setTimezone('UTC');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable('2026-06-09 18:00:00 UTC');
        $battle = (new \App\Entity\Combat\Battle())->setScheduledAt($scheduledAt)
            ->setStatus(\App\Enum\BattleStatus::Simulating)->setCurrentRound(12);
        $fixture = (new \App\Entity\League\LeagueFixture())->setBattle($battle);
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
            ->setScheduledAt($scheduledAt)->setFixture($fixture)->setStatus('processing')
            ->setExecutedAt(new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC')));
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->method('findBy')->willReturn([$tick]);
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->expects($this->exactly(2))->method('fetchOne')->willReturn('1');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')
            ->with($this->callback(static fn (CombatWave $message): bool => 13 === $message->getRound() && $scheduledAt == $message->getScheduledAt()))
            ->willReturn(new Envelope(new \stdClass()));
        $orchestrator = new KingdomTickOrchestrator($logs, $entityManager, $bus, $this->createStub(LoggerInterface::class),
            $this->createStub(SeasonTransitionService::class), $this->createStub(MarketplaceService::class), new TickClock());

        $this->assertTrue($orchestrator->recoverCombatContinuation($kingdom));
        $this->assertGreaterThan(new \DateTimeImmutable('-1 minute'), $tick->getExecutedAt());
    }

    public function testWeeklyResetWaveDispatchesTeamsAfterTreasuryIsGone(): void
    {
        $teamTick = $this->weeklyResetLog(new Team());

        $wave = $this->orchestrator->deferTeamWeeklyResetUntilTreasuryFinishes([$teamTick]);

        $this->assertSame([$teamTick], $wave);
    }

    public function testWeeklyResetProcessesOnlyOneTeamAtATime(): void
    {
        $first = $this->weeklyResetLog(new Team());
        $second = $this->weeklyResetLog(new Team());

        $this->assertSame([$first], $this->orchestrator->deferTeamWeeklyResetUntilTreasuryFinishes([$first, $second]));
    }

    public function testWeeklyResetWaveKeepsUnrelatedTicksWithTreasury(): void
    {
        $kingdomTick = $this->weeklyResetLog(null);
        $teamTick = $this->weeklyResetLog(new Team());
        $other = new KingdomTickLog();
        $other->setTickType(TickType::DailyReset);

        $wave = $this->orchestrator->deferTeamWeeklyResetUntilTreasuryFinishes([$other, $teamTick, $kingdomTick]);

        $this->assertSame([$other, $kingdomTick], $wave);
    }

    #[DataProvider('dailyPhaseTimes')]
    public function testDailyPhasePreparesSeasonUsingKingdomLocalMonday(string $time, bool $isMonday, bool $prepare): void
    {
        $kingdom = (new Kingdom())->setName('Test')->setTimezone('Europe/Prague');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable($time);
        $season = (new LeagueSeason())->setStartDate(new \DateTimeImmutable('2026-06-01'));
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $seasons = $this->createMock(LeagueSeasonRepository::class);
        $seasons->method('findOneBy')->willReturn($season);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($isMonday ? $this->once() : $this->never())->method('getRepository')->with(LeagueSeason::class)->willReturn($seasons);
        $transition = $this->createMock(SeasonTransitionService::class);
        $transition->expects($prepare ? $this->once() : $this->never())->method('prepareUpcomingSeason')->with($kingdom)->willReturn($season);
        $marketplace = $this->createMock(MarketplaceService::class);
        $clock = new TickClock();
        $marketplace->method('getMaximumListingId')->willReturn(42);
        $marketplace->expects($this->once())->method('processExpiredListingsBatch')
            ->willReturnCallback(static function (Kingdom $actualKingdom, \DateTimeImmutable $actualTime, int $maximumListingId, int $limit) use ($kingdom, $scheduledAt, $clock): int {
                self::assertSame($kingdom, $actualKingdom);
                self::assertEquals($scheduledAt, $actualTime);
                self::assertEquals($scheduledAt, $clock->getCurrentTime());
                self::assertSame(42, $maximumListingId);
                self::assertSame(25, $limit);

                return 0;
            });

        $orchestrator = new KingdomTickOrchestrator(
            $logs,
            $entityManager,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(LoggerInterface::class),
            $transition,
            $marketplace,
            $clock,
        );
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt);
        $orchestrator->processDailyKingdomPhase($kingdom, $tick);

        $this->assertNull($clock->getCustomTime());
    }

    public function testFullMarketplaceBatchCreatesOrderedContinuation(): void
    {
        $kingdom = (new Kingdom())->setName('Test')->setTimezone('UTC');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable('2026-08-11 00:00:00 UTC');
        $event = new \App\Entity\Kingdom\KingdomTickEvent($kingdom, TickType::DailyReset, $scheduledAt);
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt)->setEvent($event);
        $marketplace = $this->createMock(MarketplaceService::class);
        $marketplace->expects($this->once())->method('getMaximumListingId')->with($kingdom)->willReturn(42);
        $marketplace->expects($this->once())->method('processExpiredListingsBatch')->with($kingdom, $scheduledAt, 42, 25)->willReturn(25);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $continuation = null;
        $entityManager->expects($this->once())->method('persist')->willReturnCallback(static function (object $entity) use (&$continuation): void {
            $continuation = $entity;
        });
        $orchestrator = new KingdomTickOrchestrator(
            $this->createStub(KingdomTickLogRepository::class),
            $entityManager,
            $this->createStub(MessageBusInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(SeasonTransitionService::class),
            $marketplace,
            new TickClock(),
        );

        $orchestrator->processDailyKingdomPhase($kingdom, $tick);

        self::assertInstanceOf(KingdomTickLog::class, $continuation);
        self::assertSame($event, $continuation->getEvent());
        self::assertSame(1, $continuation->getMarketplaceBatch());
        self::assertSame(42, $continuation->getMarketplaceMaxListingId());
        $teamTick = (new KingdomTickLog())->setTickType(TickType::DailyReset)->setTeam(new Team());
        self::assertSame([$continuation], $orchestrator->deferTeamDailyResetUntilKingdomFinishes([$teamTick, $continuation]));
    }

    public function testMarketplaceContinuationReusesBoundaryWithoutPreparingSeason(): void
    {
        $kingdom = (new Kingdom())->setName('Test')->setTimezone('UTC');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable('2026-08-10 00:00:00 UTC');
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
            ->setScheduledAt($scheduledAt)->setMarketplaceBatch(2, 42);
        $marketplace = $this->createMock(MarketplaceService::class);
        $marketplace->expects($this->never())->method('getMaximumListingId');
        $marketplace->expects($this->once())->method('processExpiredListingsBatch')->with($kingdom, $scheduledAt, 42, 25)->willReturn(3);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('getRepository');
        $entityManager->expects($this->never())->method('persist');
        $orchestrator = new KingdomTickOrchestrator(
            $this->createStub(KingdomTickLogRepository::class),
            $entityManager,
            $this->createStub(MessageBusInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(SeasonTransitionService::class),
            $marketplace,
            new TickClock(),
        );

        $orchestrator->processDailyKingdomPhase($kingdom, $tick);
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function dailyPhaseTimes(): iterable
    {
        yield 'local Monday of week eleven' => ['2026-08-09 22:00:00 UTC', true, true];
        yield 'local Monday before week eleven' => ['2026-08-02 22:00:00 UTC', true, false];
        yield 'local Tuesday' => ['2026-08-10 22:00:00 UTC', false, false];
    }

    public function testStartsCombatAfterAllKickoffsFinish(): void
    {
        $kingdom = (new Kingdom())->setName('Test')->setTimezone('UTC');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable('2026-06-09 18:00:00 UTC');
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
            ->setScheduledAt($scheduledAt)->setStatus('awaiting_combat');
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->method('findOldestAwaitingCombat')->willReturn($tick);
        $logs->expects($this->once())->method('findAwaitingCombatAt')->with($kingdom, $scheduledAt)->willReturn([$tick]);
        $logs->method('getOldestPendingTime')->willReturn($scheduledAt->modify('+1 day'));
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')
            ->with($this->callback(static fn (CombatWave $message): bool => 1 === $message->getKingdomId() && $scheduledAt == $message->getScheduledAt()))
            ->willReturn(new Envelope(new \stdClass()));

        $orchestrator = new KingdomTickOrchestrator(
            $logs,
            $this->createMock(EntityManagerInterface::class),
            $bus,
            $this->createMock(LoggerInterface::class),
            $this->createMock(SeasonTransitionService::class),
            $this->createMock(MarketplaceService::class),
            new TickClock(),
        );
        $orchestrator->orchestrate($kingdom);

        $this->assertSame('processing', $tick->getStatus());
    }

    public function testCompletesPendingKickoffsBeforeStartingCombat(): void
    {
        $kingdom = (new Kingdom())->setName('Test')->setTimezone('UTC');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable('2026-06-09 18:00:00 UTC');
        $awaiting = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
            ->setScheduledAt($scheduledAt)->setStatus('awaiting_combat');
        $pending = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
            ->setScheduledAt($scheduledAt)->setStatus('pending');
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->method('findOldestAwaitingCombat')->willReturn($awaiting);
        $logs->method('getOldestPendingTime')->willReturn($scheduledAt);
        $logs->method('getHighestPendingPriorityAt')->willReturn(TickType::LeagueMatch->getPriority());
        $logs->method('findPendingTicksInGroup')->willReturn([$pending]);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $orchestrator = new KingdomTickOrchestrator(
            $logs,
            $this->createMock(EntityManagerInterface::class),
            $bus,
            $this->createMock(LoggerInterface::class),
            $this->createMock(SeasonTransitionService::class),
            $this->createMock(MarketplaceService::class),
            new TickClock(),
        );
        $orchestrator->orchestrate($kingdom);

        $this->assertSame('awaiting_combat', $awaiting->getStatus());
        $this->assertSame('dispatched', $pending->getStatus());
    }

    public function testDoesNotStartCombatWhileAnotherKickoffIsRunning(): void
    {
        $kingdom = (new Kingdom())->setName('Test')->setTimezone('UTC');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->method('hasProcessingTicks')->willReturn(true);
        $logs->expects($this->never())->method('findOldestAwaitingCombat');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $orchestrator = new KingdomTickOrchestrator(
            $logs,
            $this->createMock(EntityManagerInterface::class),
            $bus,
            $this->createMock(LoggerInterface::class),
            $this->createMock(SeasonTransitionService::class),
            $this->createMock(MarketplaceService::class),
            new TickClock(),
        );
        $orchestrator->orchestrate($kingdom);
    }

    public function testResumesStalledCombatFromItsSavedRound(): void
    {
        $kingdom = new Kingdom();
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable('2026-06-09 18:00:00 UTC');
        $battle = (new \App\Entity\Combat\Battle())->setScheduledAt($scheduledAt)
            ->setStatus(\App\Enum\BattleStatus::Stalled)->setCurrentRound(6);
        $fixture = (new \App\Entity\League\LeagueFixture())->setBattle($battle);
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setFixture($fixture)
            ->setScheduledAt($scheduledAt)->setStatus('processing');
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->method('findOneBy')->willReturn($tick);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $em->expects($this->once())->method('commit');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())->method('dispatch')
            ->with($this->callback(static fn (CombatWave $message): bool => 1 === $message->getKingdomId()
                && $scheduledAt == $message->getScheduledAt() && 7 === $message->getRound()))
            ->willReturn(new Envelope(new \stdClass()));
        $orchestrator = new KingdomTickOrchestrator(
            $logs,
            $em,
            $bus,
            $this->createMock(LoggerInterface::class),
            $this->createMock(SeasonTransitionService::class),
            $this->createMock(MarketplaceService::class),
            new TickClock(),
        );

        $orchestrator->resumeCombat($kingdom, 42);

        $this->assertSame(\App\Enum\BattleStatus::Simulating, $battle->getStatus());
        $this->assertSame('processing', $tick->getStatus());
    }

    public function testCombatRecoveryRollsBackWhenDispatchFails(): void
    {
        $kingdom = new Kingdom();
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $scheduledAt = new \DateTimeImmutable('2026-06-09 18:00:00 UTC');
        $battle = (new \App\Entity\Combat\Battle())->setScheduledAt($scheduledAt)->setStatus(\App\Enum\BattleStatus::Stalled);
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setScheduledAt($scheduledAt)
            ->setFixture((new \App\Entity\League\LeagueFixture())->setBattle($battle))->setStatus('processing');
        $logs = $this->createStub(KingdomTickLogRepository::class);
        $logs->method('findOneBy')->willReturn($tick);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('commit');
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Transport unavailable'));
        $orchestrator = new KingdomTickOrchestrator(
            $logs,
            $em,
            $bus,
            $this->createMock(LoggerInterface::class),
            $this->createMock(SeasonTransitionService::class),
            $this->createMock(MarketplaceService::class),
            new TickClock(),
        );

        $this->expectExceptionMessage('Transport unavailable');
        $orchestrator->resumeCombat($kingdom, 42);
    }

    private function weeklyResetLog(?Team $team): KingdomTickLog
    {
        $log = new KingdomTickLog();
        $log->setTickType(TickType::WeeklyReset);
        $log->setTeam($team);

        return $log;
    }
}
