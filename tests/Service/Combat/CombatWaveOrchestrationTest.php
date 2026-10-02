<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\Combat\Battle;
use App\Enum\BattleStatus;
use App\Message\CombatWave;
use App\Message\CombatWaveHandler;
use App\Message\ProcessCombatRound;
use App\Repository\Combat\BattleRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class CombatWaveOrchestrationTest extends TestCase
{
    public function testCombatWaveHandlerDispatchesProcessCombatRound(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $battle = $this->createMock(Battle::class);
        $battle->method('getId')->willReturn(123);
        $battle->method('getCurrentRound')->willReturn(0);

        $mockQb = $this->createMock(QueryBuilder::class);
        $mockQb->method('where')->willReturnSelf();
        $mockQb->method('andWhere')->willReturnSelf();
        $mockQb->method('setParameter')->willReturnSelf();
        
        $mockQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $mockQuery->method('getResult')->willReturn([$battle]);
        $mockQb->method('getQuery')->willReturn($mockQuery);

        $battleRepository->method('createQueryBuilder')->willReturn($mockQb);

        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ProcessCombatRound $msg) {
                return $msg->getBattleId() === 123 && $msg->getRound() === 1;
            }))
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $handler = new CombatWaveHandler($battleRepository, $messageBus);
        
        $scheduledAt = new \DateTimeImmutable('2026-06-17 18:00:00');
        $handler(new CombatWave(1, $scheduledAt, 1));
    }

    public function testCombatWaveHandlerDispatchesNothingWhenNoSimulatingBattlesRemain(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $mockQb = $this->createMock(QueryBuilder::class);
        $mockQb->method('where')->willReturnSelf();
        $mockQb->method('andWhere')->willReturnSelf();
        $mockQb->method('setParameter')->willReturnCallback(function (string $key, mixed $value) use ($mockQb) {
            if ('status' === $key) {
                $this->assertSame(BattleStatus::Simulating, $value);
            }

            return $mockQb;
        });

        $mockQuery = $this->createMock(\Doctrine\ORM\Query::class);
        // Cohort may still have stalled/completed battles; the query only returns simulating.
        $mockQuery->method('getResult')->willReturn([]);
        $mockQb->method('getQuery')->willReturn($mockQuery);
        $battleRepository->method('createQueryBuilder')->willReturn($mockQb);

        $messageBus->expects($this->never())->method('dispatch');

        $handler = new CombatWaveHandler($battleRepository, $messageBus);
        $handler(new CombatWave(1, new \DateTimeImmutable('2026-06-17 18:00:00'), 5));
    }

    public function testCombatWaveHandlerOnlyAdvancesBattlesReadyForRequestedRound(): void
    {
        $battleRepository = $this->createMock(BattleRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);

        $ready = $this->createMock(Battle::class);
        $ready->method('getId')->willReturn(10);
        $ready->method('getCurrentRound')->willReturn(2);

        $alreadyAhead = $this->createMock(Battle::class);
        $alreadyAhead->method('getId')->willReturn(11);
        $alreadyAhead->method('getCurrentRound')->willReturn(3);

        $mockQb = $this->createMock(QueryBuilder::class);
        $mockQb->method('where')->willReturnSelf();
        $mockQb->method('andWhere')->willReturnSelf();
        $mockQb->method('setParameter')->willReturnSelf();

        $mockQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $mockQuery->method('getResult')->willReturn([$ready, $alreadyAhead]);
        $mockQb->method('getQuery')->willReturn($mockQuery);
        $battleRepository->method('createQueryBuilder')->willReturn($mockQb);

        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (ProcessCombatRound $msg): bool {
                return 10 === $msg->getBattleId() && 3 === $msg->getRound();
            }))
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $handler = new CombatWaveHandler($battleRepository, $messageBus);
        $handler(new CombatWave(1, new \DateTimeImmutable('2026-06-17 18:00:00'), 3));
    }
}
