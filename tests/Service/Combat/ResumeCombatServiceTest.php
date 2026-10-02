<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\Combat\Battle;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\BattleStatus;
use App\Enum\MatchType;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Combat\CombatEngine;
use App\Service\Combat\ResumeCombatService;
use App\Service\League\LeagueMatchResolutionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class ResumeCombatServiceTest extends TestCase
{
    public function testResumeExitsWhenBattleIsNotStalled(): void
    {
        $battle = $this->makeBattle(BattleStatus::Completed, 3);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('beginTransaction');
        $em->expects($this->once())->method('rollback');
        $em->expects($this->never())->method('commit');
        $em->method('find')->willReturn($battle);

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->never())->method('simulateRound');

        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->expects($this->never())->method('completeBattle');

        $service = new ResumeCombatService(
            $em,
            $combatEngine,
            $league,
            $this->createStub(KingdomTickOrchestrator::class),
            $this->createStub(LoggerInterface::class),
        );

        $service->resume(42);

        $this->assertSame(BattleStatus::Completed, $battle->getStatus());
    }

    public function testResumeAdvancesStalledBattleToCompleted(): void
    {
        $battle = $this->makeBattle(BattleStatus::Stalled, 4);
        $battle->setRunState([
            'status' => 'simulating',
            'error' => 'previous failure',
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('beginTransaction');
        $em->expects($this->exactly(2))->method('commit');
        $em->expects($this->never())->method('rollback');
        $em->expects($this->exactly(2))->method('flush');
        $em->method('find')->willReturn($battle);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repo);

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->once())
            ->method('simulateRound')
            ->willReturnCallback(static function (array &$runState, int $round): array {
                self::assertSame(5, $round);
                $runState['status'] = 'completed';
                $runState['scoreA'] = 4;
                $runState['scoreB'] = 2;
                $runState['combatLog'] = ['version' => 1, 'events' => []];

                return [];
            });

        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->expects($this->once())->method('completeBattle')->with($battle);

        $orchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $orchestrator->expects($this->once())->method('orchestrate')->with($battle->getKingdom());

        $service = new ResumeCombatService(
            $em,
            $combatEngine,
            $league,
            $orchestrator,
            $this->createStub(LoggerInterface::class),
        );

        $service->resume(42);

        $this->assertSame(BattleStatus::Completed, $battle->getStatus());
        $this->assertSame(5, $battle->getCurrentRound());
        $this->assertSame(4, $battle->getScoreA());
        $this->assertSame(2, $battle->getScoreB());
        $this->assertNotNull($battle->getProcessedAt());
    }

    public function testResumeReStallsWhenSimulateRoundThrows(): void
    {
        $battle = $this->makeBattle(BattleStatus::Stalled, 1);
        $battle->setRunState(['status' => 'simulating']);

        $em = $this->createMock(EntityManagerInterface::class);
        // unlock stalled → simulating, then round attempt, then re-stall transaction
        $em->expects($this->exactly(3))->method('beginTransaction');
        $em->expects($this->exactly(2))->method('commit');
        $em->expects($this->once())->method('rollback');
        $em->method('find')->willReturn($battle);

        $combatEngine = $this->createMock(CombatEngine::class);
        $combatEngine->expects($this->once())
            ->method('simulateRound')
            ->willThrowException(new \RuntimeException('engine boom'));

        $league = $this->createMock(LeagueMatchResolutionService::class);
        $league->expects($this->never())->method('completeBattle');

        $service = new ResumeCombatService(
            $em,
            $combatEngine,
            $league,
            $this->createStub(KingdomTickOrchestrator::class),
            $this->createStub(LoggerInterface::class),
        );

        $service->resume(99);

        $this->assertSame(BattleStatus::Stalled, $battle->getStatus());
        $this->assertStringContainsString('engine boom', (string) ($battle->getRunState()['error'] ?? ''));
    }

    private function makeBattle(BattleStatus $status, int $currentRound): Battle
    {
        $kingdom = $this->createStub(Kingdom::class);
        $kingdom->method('getId')->willReturn(7);

        $battle = new Battle();
        $battle->setKingdom($kingdom);
        $battle->setTeamA($this->createStub(Team::class));
        $battle->setTeamB($this->createStub(Team::class));
        $battle->setMatchType(MatchType::League);
        $battle->setStatus($status);
        $battle->setCurrentRound($currentRound);
        $battle->setScheduledAt(new \DateTimeImmutable('2026-06-17 18:00:00', new \DateTimeZone('UTC')));

        return $battle;
    }
}
