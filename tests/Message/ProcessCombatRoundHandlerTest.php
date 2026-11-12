<?php

declare(strict_types=1);

namespace App\Tests\Message;

use App\Entity\Combat\Battle;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\BattleStatus;
use App\Enum\MatchType;
use App\Message\CombatWave;
use App\Message\ProcessCombatRound;
use App\Message\ProcessCombatRoundHandler;
use App\Service\Calendar\TickClock;
use App\Service\Combat\CombatEngine;
use App\Service\Combat\CombatRoundProcessor;
use App\Service\Combat\CombatWaveProcessor;
use App\Service\League\LeagueMatchResolutionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class ProcessCombatRoundHandlerTest extends TestCase
{
    public function testNoOpWhenRoundAlreadyApplied(): void
    {
        $battle = $this->makeBattle(BattleStatus::Simulating, 3);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('commit');
        $em->method('find')->willReturn($battle);

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->never())->method('simulateRound');

        $handler = $this->handler($em, $combatEngine);
        $handler->process(42, 3);

        $this->assertSame(3, $battle->getCurrentRound());
    }

    public function testAdvancesRoundWithoutMessengerFanOut(): void
    {
        $kingdom = $this->createStub(Kingdom::class);
        $kingdom->method('getId')->willReturn(7);
        $scheduledAt = new \DateTimeImmutable('2026-06-17 18:00:00');

        $battle = $this->makeBattle(BattleStatus::Simulating, 1, $kingdom, $scheduledAt);
        $battle->setRunState(['status' => 'simulating', 'events' => []]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('commit');
        $em->expects($this->once())->method('flush');
        $em->method('find')->willReturn($battle);

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->once())
            ->method('simulateRound')
            ->willReturnCallback(static function (array &$runState, int $round): array {
                self::assertSame(2, $round);
                $runState['status'] = 'simulating';

                return [['type' => 'round_start', 'round' => 2]];
            });

        $handler = $this->handler($em, $combatEngine);
        $handler->process(42, 2);

        $this->assertSame(2, $battle->getCurrentRound());
        $this->assertSame(BattleStatus::Simulating, $battle->getStatus());
    }

    #[DataProvider('completionCases')]
    public function testCompletesBattleWhenRoundEndsMatch(bool $friendly, int $savedRound): void
    {
        $kingdom = $this->createStub(Kingdom::class);
        $kingdom->method('getId')->willReturn(7);
        $scheduledAt = new \DateTimeImmutable('2026-06-17 18:00:00');

        $battle = $this->makeBattle(BattleStatus::Simulating, $savedRound, $kingdom, $scheduledAt);
        $clock = new TickClock();
        $previousTime = new \DateTimeImmutable('2026-06-16 12:00:00');
        $clock->setCustomTime($previousTime);
        $battle->setRunState(['status' => 'simulating']);
        $battle->setMatchType($friendly ? MatchType::Friendly : MatchType::League);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('commit');
        $em->expects($this->once())->method('flush');
        $em->method('find')->willReturn($battle);

        $fixture = $this->createStub(\App\Entity\League\LeagueFixture::class);
        $friendlyMatch = $this->createStub(\App\Entity\FriendlyMatch\FriendlyMatch::class);
        $tickLog = $this->createMock(\App\Entity\Kingdom\KingdomTickLog::class);
        $tickLog->expects($this->once())->method('setStatus')->with('completed');
        $tickLog->expects($this->once())->method('setExecutedAt')->with($this->isInstanceOf(\DateTimeImmutable::class));
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($fixture, $friendlyMatch, $friendly, $tickLog) {
            $repo = $this->createStub(EntityRepository::class);
            $repo->method('findOneBy')->willReturn(match ($class) {
                \App\Entity\League\LeagueFixture::class => $friendly ? null : $fixture,
                \App\Entity\FriendlyMatch\FriendlyMatch::class => $friendlyMatch,
                \App\Entity\Kingdom\KingdomTickLog::class => $tickLog,
                default => null,
            });

            return $repo;
        });

        $combatEngine = $this->createMock(CombatEngine::class);
        $finish = static function (array &$runState): void {
            $runState['status'] = 'completed';
            $runState['scoreA'] = 4;
            $runState['scoreB'] = 1;
            $runState['combatLog'] = ['version' => 1, 'events' => []];
        };
        $combatEngine->expects($savedRound < CombatEngine::MAX_ROUNDS ? $this->once() : $this->never())
            ->method('simulateRound')
            ->willReturnCallback(static function (array &$runState, int $round) use ($savedRound, $finish): array {
                self::assertSame($savedRound + 1, $round);
                if ($round < CombatEngine::MAX_ROUNDS) {
                    $finish($runState);
                }

                return [['type' => 'match_end']];
            });
        $combatEngine->expects($savedRound >= CombatEngine::MAX_ROUNDS - 1 ? $this->once() : $this->never())
            ->method('forceCompleteAtRoundCap')->willReturnCallback($finish);

        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->expects($friendly ? $this->never() : $this->once())->method('completeBattle')->with($battle)
            ->willReturnCallback(static function () use ($clock, $scheduledAt): void {
                self::assertSame($scheduledAt, $clock->getCurrentTime());
            });
        $friendlyService = $this->createMock(\App\Service\FriendlyMatch\FriendlyMatchResolutionService::class);
        $friendlyService->expects($friendly ? $this->once() : $this->never())->method('completeBattle')->with($battle)
            ->willReturnCallback(static function () use ($clock, $scheduledAt): void {
                self::assertSame($scheduledAt, $clock->getCurrentTime());
            });

        $handler = $this->handler($em, $combatEngine, $league, $friendlyService, $clock);
        $handler->process(42, $savedRound + 1);

        $this->assertSame($previousTime, $clock->getCustomTime());
        $this->assertSame(BattleStatus::Completed, $battle->getStatus());
        $this->assertSame(min($savedRound + 1, CombatEngine::MAX_ROUNDS), $battle->getCurrentRound());
        $this->assertSame(4, $battle->getScoreA());
        $this->assertSame(1, $battle->getScoreB());
        $this->assertNotNull($battle->getProcessedAt());
    }

    /**
     * @return iterable<string, array{bool, int}>
     */
    public static function completionCases(): iterable
    {
        yield 'league' => [false, 4];
        yield 'friendly' => [true, 4];
        yield 'league round cap' => [false, 199];
        yield 'friendly round cap' => [true, 199];
        yield 'league saved at cap' => [false, 200];
        yield 'friendly saved at cap' => [true, 200];
    }

    public function testMarksBattleStalledWhenSimulateRoundThrows(): void
    {
        $battle = $this->makeBattle(BattleStatus::Simulating, 0);
        $battle->setRunState(['status' => 'simulating']);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('beginTransaction');
        $em->expects($this->once())->method('rollback');
        $em->expects($this->once())->method('commit');
        $em->expects($this->once())->method('flush');
        $em->method('find')->willReturn($battle);
        $em->method('isOpen')->willReturn(true);

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->once())
            ->method('simulateRound')
            ->willThrowException(new \RuntimeException('engine boom'));

        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->expects($this->never())->method('completeBattle');

        $handler = $this->handler($em, $combatEngine, $league);

        $handler->process(42, 1);

        $this->assertSame(BattleStatus::Stalled, $battle->getStatus());
        $this->assertStringContainsString('engine boom', (string) ($battle->getRunState()['error'] ?? ''));
    }

    public function testCompletionFailureRestoresLastCheckpointBeforeMarkingStalled(): void
    {
        $battle = $this->makeBattle(BattleStatus::Simulating, 4);
        $clock = new TickClock();
        $previousTime = new \DateTimeImmutable('2026-06-16 12:00:00');
        $clock->setCustomTime($previousTime);
        $battle->setRunState(['status' => 'simulating']);
        $persistedBattle = clone $battle;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturnOnConsecutiveCalls($battle, $persistedBattle);
        $em->method('isOpen')->willReturn(true);
        $em->expects($this->exactly(2))->method('beginTransaction');
        $em->expects($this->once())->method('rollback');
        $em->expects($this->once())->method('commit');
        $em->expects($this->exactly(2))->method('clear');
        $fixture = $this->createStub(\App\Entity\League\LeagueFixture::class);
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($fixture);
        $em->method('getRepository')->willReturn($repo);
        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->method('simulateRound')->willReturnCallback(static function (array &$state): array {
            $state = ['status' => 'completed', 'scoreA' => 6, 'scoreB' => 0, 'combatLog' => []];

            return [];
        });
        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->method('completeBattle')->willThrowException(new \RuntimeException('completion failed'));

        $this->handler($em, $combatEngine, $league, null, $clock)->process(42, 5);

        $this->assertSame($previousTime, $clock->getCustomTime());
        $this->assertSame(BattleStatus::Stalled, $persistedBattle->getStatus());
        $this->assertSame(4, $persistedBattle->getCurrentRound());
        $this->assertSame('simulating', $persistedBattle->getRunState()['status'] ?? null);
        $this->assertStringContainsString('completion failed', $persistedBattle->getRunState()['error']);
    }

    private function handler(
        EntityManagerInterface $em,
        CombatEngine $combatEngine,
        ?LeagueMatchResolutionService $league = null,
        ?\App\Service\FriendlyMatch\FriendlyMatchResolutionService $friendly = null,
        ?TickClock $clock = null,
    ): CombatRoundProcessor {
        return new CombatRoundProcessor(
            $em,
            $combatEngine,
            $league ?? $this->createStub(LeagueMatchResolutionService::class),
            $friendly ?? $this->createStub(\App\Service\FriendlyMatch\FriendlyMatchResolutionService::class),
            $this->createStub(LoggerInterface::class),
            $clock ?? new TickClock(),
        );
    }

    public function testRejectsRoundThatWouldSkipPersistedProgress(): void
    {
        $battle = $this->makeBattle(BattleStatus::Simulating, 1);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($battle);
        $em->expects($this->once())->method('rollback');
        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->never())->method('simulateRound');

        $this->handler($em, $combatEngine)->process(42, 3);

        $this->assertSame(1, $battle->getCurrentRound());
    }

    public function testLockFailurePropagatesWithoutMarkingBattleStalled(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('find')->willThrowException(new \RuntimeException('lock unavailable'));
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('flush');
        $em->expects($this->never())->method('commit');

        $this->expectExceptionMessage('lock unavailable');
        $this->handler($em, $this->createStub(CombatEngine::class))->process(42, 1);
    }

    public function testLegacyMessageDelegatesToCohortBatch(): void
    {
        $battle = $this->makeBattle(BattleStatus::Simulating, 53);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($battle);
        $processor = $this->createMock(CombatWaveProcessor::class);
        $processor->expects($this->once())->method('process')
            ->with($this->callback(static fn (CombatWave $message): bool => 7 === $message->getKingdomId()
                && $message->getScheduledAt() == $battle->getScheduledAt()));

        (new ProcessCombatRoundHandler($em, $processor))(new ProcessCombatRound(42, 54));
    }

    public function testLegacyMessageDoesNotRestartCompletedBattle(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($this->makeBattle(BattleStatus::Completed, 54));
        $processor = $this->createMock(CombatWaveProcessor::class);
        $processor->expects($this->never())->method('process');

        (new ProcessCombatRoundHandler($em, $processor))(new ProcessCombatRound(42, 54));
    }

    private function makeBattle(
        BattleStatus $status,
        int $currentRound,
        ?Kingdom $kingdom = null,
        ?\DateTimeImmutable $scheduledAt = null,
    ): Battle {
        if (null === $kingdom) {
            $kingdom = $this->createStub(Kingdom::class);
            $kingdom->method('getId')->willReturn(7);
        }

        $teamA = $this->createStub(Team::class);
        $teamB = $this->createStub(Team::class);

        $battle = new Battle();
        $battle->setKingdom($kingdom);
        $battle->setMatchType(MatchType::League);
        $battle->setTeamA($teamA);
        $battle->setTeamB($teamB);
        $battle->setStatus($status);
        $battle->setCurrentRound($currentRound);
        $battle->setScheduledAt($scheduledAt ?? new \DateTimeImmutable('2026-06-17 18:00:00'));

        return $battle;
    }
}
