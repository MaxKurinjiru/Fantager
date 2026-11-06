<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\Kingdom\Kingdom;
use App\Message\CombatWave;
use App\Message\CombatWaveHandler;
use App\Repository\Combat\BattleRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Combat\CombatRoundProcessor;
use App\Service\Combat\CombatWaveProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class CombatWaveOrchestrationTest extends TestCase
{
    public function testHandlerDelegatesToBatchProcessor(): void
    {
        $message = new CombatWave(1, new \DateTimeImmutable('2026-06-17 18:00:00'), 1);
        $processor = $this->createMock(CombatWaveProcessor::class);
        $processor->expects($this->once())->method('process')->with($message);

        (new CombatWaveHandler($processor))($message);
    }

    public function testProcessesWholeWavesAndDispatchesOneContinuation(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $battleRepository->expects($this->exactly(3))->method('findSimulatingCohortRounds')
            ->with(1, $this->anything())
            ->willReturnOnConsecutiveCalls(
                [['id' => 10, 'currentRound' => 0], ['id' => 11, 'currentRound' => 0]],
                [['id' => 10, 'currentRound' => 1], ['id' => 11, 'currentRound' => 1]],
                [['id' => 10, 'currentRound' => 2], ['id' => 11, 'currentRound' => 2]],
            );
        $calls = [];
        $roundProcessor = $this->createMock(CombatRoundProcessor::class);
        $roundProcessor->expects($this->exactly(4))->method('process')
            ->willReturnCallback(static function (int $battleId, int $round) use (&$calls): void {
                $calls[] = [$battleId, $round];
            });
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->with($this->callback(static fn (CombatWave $message): bool => 3 === $message->getRound() && 1 === $message->getKingdomId()))
            ->willReturn(new Envelope(new \stdClass()));

        $this->processor($battleRepository, $roundProcessor, $messageBus)->process($this->message());

        $this->assertSame([[10, 1], [11, 1], [10, 2], [11, 2]], $calls);
    }

    public function testFinishesLaggingBattlesBeforeStartingNextRound(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $battleRepository->method('findSimulatingCohortRounds')->willReturnOnConsecutiveCalls(
            [['id' => 10, 'currentRound' => 2], ['id' => 11, 'currentRound' => 3]],
            [['id' => 10, 'currentRound' => 3], ['id' => 11, 'currentRound' => 3]],
            [],
        );
        $calls = [];
        $roundProcessor = $this->createMock(CombatRoundProcessor::class);
        $roundProcessor->expects($this->exactly(3))->method('process')
            ->willReturnCallback(static function (int $battleId, int $round) use (&$calls): void {
                $calls[] = [$battleId, $round];
            });
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $this->processor($battleRepository, $roundProcessor, $messageBus)->process($this->message());

        $this->assertSame([[10, 3], [10, 4], [11, 4]], $calls);
    }

    public function testTimeBudgetYieldsMidWaveWithoutSkippingRemainingBattle(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $battleRepository->method('findSimulatingCohortRounds')->willReturnOnConsecutiveCalls(
            [['id' => 10, 'currentRound' => 0], ['id' => 11, 'currentRound' => 0]],
            [['id' => 10, 'currentRound' => 1], ['id' => 11, 'currentRound' => 0]],
        );
        $roundProcessor = $this->createMock(CombatRoundProcessor::class);
        $roundProcessor->expects($this->once())->method('process')->with(10, 1);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())->method('dispatch')
            ->with($this->callback(static fn (CombatWave $message): bool => 1 === $message->getRound()))
            ->willReturn(new Envelope(new \stdClass()));

        $this->processor($battleRepository, $roundProcessor, $messageBus, timeBudgetSeconds: 0.0)->process($this->message());
    }

    public function testFinishedOrStalledCohortDispatchesNothing(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $battleRepository->method('findSimulatingCohortRounds')->willReturn([]);
        $roundProcessor = $this->createMock(CombatRoundProcessor::class);
        $roundProcessor->expects($this->never())->method('process');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $this->processor($battleRepository, $roundProcessor, $messageBus)->process($this->message());
    }

    public function testStalledBattleDoesNotStopOtherBattles(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $battleRepository->method('findSimulatingCohortRounds')->willReturnOnConsecutiveCalls(
            [['id' => 10, 'currentRound' => 0], ['id' => 11, 'currentRound' => 0]],
            [['id' => 11, 'currentRound' => 1]],
            [],
        );
        $calls = [];
        $roundProcessor = $this->createMock(CombatRoundProcessor::class);
        $roundProcessor->expects($this->exactly(3))->method('process')
            ->willReturnCallback(static function (int $battleId, int $round) use (&$calls): void {
                $calls[] = [$battleId, $round];
            });
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $this->processor($battleRepository, $roundProcessor, $messageBus)->process($this->message());

        $this->assertSame([[10, 1], [11, 1], [11, 2]], $calls);
    }

    public function testDuplicateWorkerDoesNoWorkWhileCohortIsLocked(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $battleRepository->expects($this->never())->method('findSimulatingCohortRounds');
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchOne')->willReturn('0');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $processor = new CombatWaveProcessor(
            $battleRepository,
            $this->createStub(CombatRoundProcessor::class),
            $em,
            $this->createStub(MessageBusInterface::class),
            $this->createStub(KingdomTickOrchestrator::class),
        );
        $processor->process($this->message());
    }

    public function testLockIsReleasedWhenRoundProcessingFails(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $battleRepository->method('findSimulatingCohortRounds')->willReturn([['id' => 10, 'currentRound' => 0]]);
        $roundProcessor = $this->createMock(CombatRoundProcessor::class);
        $roundProcessor->method('process')->willThrowException(new \RuntimeException('database unavailable'));

        $processor = $this->processor($battleRepository, $roundProcessor, $this->createStub(MessageBusInterface::class));
        $this->expectExceptionMessage('database unavailable');
        $processor->process($this->message());
    }

    private function processor(
        BattleRepository $battleRepository,
        CombatRoundProcessor $roundProcessor,
        MessageBusInterface $messageBus,
        float $timeBudgetSeconds = 60.0,
    ): CombatWaveProcessor {
        $connection = $this->createMock(Connection::class);
        $queries = [];
        $connection->expects($this->exactly(2))->method('fetchOne')
            ->willReturnCallback(static function (string $query, array $parameters) use (&$queries): string {
                $queries[] = $query;
                self::assertSame(1 === count($queries) ? 'SELECT GET_LOCK(?, 0)' : 'SELECT RELEASE_LOCK(?)', $query);
                self::assertStringStartsWith('combat_wave_1_', $parameters[0]);

                return '1';
            });
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $kingdom = $this->createStub(Kingdom::class);
        $em->method('find')->willReturn($kingdom);
        $orchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $orchestrator->expects($this->atMost(1))->method('orchestrate')->with($kingdom);

        return new CombatWaveProcessor($battleRepository, $roundProcessor, $em, $messageBus, $orchestrator, 2, $timeBudgetSeconds);
    }

    private function message(): CombatWave
    {
        return new CombatWave(1, new \DateTimeImmutable('2026-06-17 18:00:00'), 1);
    }
}
