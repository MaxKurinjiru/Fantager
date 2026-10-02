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
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Combat\CombatEngine;
use App\Service\League\LeagueMatchResolutionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

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
        $handler(new ProcessCombatRound(42, 3));

        $this->assertSame(3, $battle->getCurrentRound());
    }

    public function testAdvancesRoundAndDispatchesNextWaveWhenBarrierClear(): void
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

        $this->stubBarrierCounts($em, laggingSimulating: 0, stillSimulating: 1);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (CombatWave $wave) use ($scheduledAt): bool {
                return 7 === $wave->getKingdomId()
                    && 3 === $wave->getRound()
                    && $wave->getScheduledAt() == $scheduledAt;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $handler = $this->handler($em, $combatEngine, messageBus: $messageBus);
        $handler(new ProcessCombatRound(42, 2));

        $this->assertSame(2, $battle->getCurrentRound());
        $this->assertSame(BattleStatus::Simulating, $battle->getStatus());
    }

    public function testCompletesBattleWhenRoundEndsMatch(): void
    {
        $kingdom = $this->createStub(Kingdom::class);
        $kingdom->method('getId')->willReturn(7);
        $scheduledAt = new \DateTimeImmutable('2026-06-17 18:00:00');

        $battle = $this->makeBattle(BattleStatus::Simulating, 4, $kingdom, $scheduledAt);
        $battle->setRunState(['status' => 'simulating']);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('commit');
        $em->expects($this->once())->method('flush');
        $em->method('find')->willReturn($battle);

        $fixtureRepo = $this->createStub(EntityRepository::class);
        $fixtureRepo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($fixtureRepo);

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->once())
            ->method('simulateRound')
            ->willReturnCallback(static function (array &$runState, int $round): array {
                self::assertSame(5, $round);
                $runState['status'] = 'completed';
                $runState['scoreA'] = 4;
                $runState['scoreB'] = 1;
                $runState['combatLog'] = ['version' => 1, 'events' => []];

                return [['type' => 'match_end']];
            });

        $this->stubBarrierCounts($em, laggingSimulating: 0, stillSimulating: 0);

        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->expects($this->once())->method('completeBattle')->with($battle);

        $orchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $orchestrator->expects($this->once())->method('orchestrate')->with($kingdom);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $handler = $this->handler($em, $combatEngine, $league, $messageBus, $orchestrator);
        $handler(new ProcessCombatRound(42, 5));

        $this->assertSame(BattleStatus::Completed, $battle->getStatus());
        $this->assertSame(5, $battle->getCurrentRound());
        $this->assertSame(4, $battle->getScoreA());
        $this->assertSame(1, $battle->getScoreB());
        $this->assertNotNull($battle->getProcessedAt());
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

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->once())
            ->method('simulateRound')
            ->willThrowException(new \RuntimeException('engine boom'));

        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->expects($this->never())->method('completeBattle');

        $handler = $this->handler($em, $combatEngine, $league);

        try {
            $handler(new ProcessCombatRound(42, 1));
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('engine boom', $e->getMessage());
        }

        $this->assertSame(BattleStatus::Stalled, $battle->getStatus());
        $this->assertStringContainsString('engine boom', (string) ($battle->getRunState()['error'] ?? ''));
    }

    private function handler(
        EntityManagerInterface $em,
        CombatEngine $combatEngine,
        ?LeagueMatchResolutionService $league = null,
        ?MessageBusInterface $messageBus = null,
        ?KingdomTickOrchestrator $orchestrator = null,
    ): ProcessCombatRoundHandler {
        return new ProcessCombatRoundHandler(
            $em,
            $combatEngine,
            $messageBus ?? $this->createStub(MessageBusInterface::class),
            $league ?? $this->createStub(LeagueMatchResolutionService::class),
            $orchestrator ?? $this->createStub(KingdomTickOrchestrator::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function stubBarrierCounts(
        EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em,
        int $laggingSimulating,
        int $stillSimulating,
    ): void {
        $lagQuery = $this->createMock(Query::class);
        $lagQuery->method('getSingleScalarResult')->willReturn($laggingSimulating);

        $stillQuery = $this->createMock(Query::class);
        $stillQuery->method('getSingleScalarResult')->willReturn($stillSimulating);

        $lagQb = $this->createMock(QueryBuilder::class);
        $lagQb->method('select')->willReturnSelf();
        $lagQb->method('from')->willReturnSelf();
        $lagQb->method('where')->willReturnSelf();
        $lagQb->method('andWhere')->willReturnSelf();
        $lagQb->method('setParameter')->willReturnSelf();
        $lagQb->method('getQuery')->willReturn($lagQuery);

        $stillQb = $this->createMock(QueryBuilder::class);
        $stillQb->method('select')->willReturnSelf();
        $stillQb->method('from')->willReturnSelf();
        $stillQb->method('where')->willReturnSelf();
        $stillQb->method('andWhere')->willReturnSelf();
        $stillQb->method('setParameter')->willReturnSelf();
        $stillQb->method('getQuery')->willReturn($stillQuery);

        $em->method('createQueryBuilder')->willReturnOnConsecutiveCalls($lagQb, $stillQb);
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
