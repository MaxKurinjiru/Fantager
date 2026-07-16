<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\Combat\Battle;
use App\Enum\BattleStatus;
use App\Message\CombatWave;
use App\Message\CombatWaveHandler;
use App\Message\ProcessCombatRound;
use App\Message\ProcessCombatRoundHandler;
use App\Repository\Combat\BattleRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Combat\CombatEngine;
use App\Service\League\LeagueMatchResolutionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
}
