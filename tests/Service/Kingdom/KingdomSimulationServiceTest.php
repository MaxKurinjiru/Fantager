<?php

declare(strict_types=1);

namespace App\Tests\Service\Kingdom;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueSeason;
use App\Enum\LeagueSeasonStatus;
use App\Enum\TickType;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Repository\League\SeasonReportRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Calendar\KingdomTickRunnerService;
use App\Service\Kingdom\KingdomSimulationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class KingdomSimulationServiceTest extends TestCase
{
    public function testIsIdleRequiresEmptyPipeline(): void
    {
        $service = $this->createService();

        $this->assertTrue($service->isIdle([
            'open_ticks' => 0,
            'messenger_async_count' => 0,
            'failed' => [],
            'blocked' => null,
        ]));
        $this->assertFalse($service->isIdle([
            'open_ticks' => 1,
            'messenger_async_count' => 0,
            'failed' => [],
            'blocked' => null,
        ]));
        $this->assertFalse($service->isIdle([
            'open_ticks' => 0,
            'messenger_async_count' => 4,
            'failed' => [],
            'blocked' => null,
        ]));
    }

    public function testPipelineExitCode(): void
    {
        $service = $this->createService();

        $this->assertSame(KingdomSimulationService::EXIT_IDLE, $service->pipelineExitCode([
            'open_ticks' => 0,
            'messenger_async_count' => 0,
            'failed' => [],
            'blocked' => null,
        ]));
        $this->assertSame(KingdomSimulationService::EXIT_BUSY, $service->pipelineExitCode([
            'open_ticks' => 2,
            'messenger_async_count' => 0,
            'failed' => [],
            'blocked' => null,
        ]));
        $this->assertSame(KingdomSimulationService::EXIT_FAILED, $service->pipelineExitCode([
            'open_ticks' => 0,
            'messenger_async_count' => 0,
            'failed' => [['id' => 1]],
            'blocked' => null,
        ]));
    }

    public function testIsFailedWhenBlockedOrFailedTicks(): void
    {
        $service = $this->createService();

        $this->assertTrue($service->isFailed([
            'failed' => [['id' => 9]],
            'blocked' => null,
        ]));
        $this->assertTrue($service->isFailed([
            'failed' => [],
            'blocked' => ['id' => 3],
        ]));
        $this->assertFalse($service->isFailed([
            'failed' => [],
            'blocked' => null,
        ]));
    }

    public function testNextHorizonAddsStepToLatestCompletedTick(): void
    {
        $kingdom = new Kingdom();
        $log = new KingdomTickLog();
        $log->setScheduledAt(new \DateTimeImmutable('2026-03-01 18:00:00', new \DateTimeZone('UTC')));

        $tickLogs = $this->createMock(KingdomTickLogRepository::class);
        $tickLogs->expects($this->once())->method('findLatestCompleted')->with($kingdom)->willReturn($log);

        $service = $this->createService(tickLogs: $tickLogs);
        $until = $service->nextHorizon($kingdom, new \DateInterval('P7D'));

        $this->assertSame('2026-03-08 18:00:00', $until->format('Y-m-d H:i:s'));
    }

    public function testNextHorizonUsesSeasonStartWhenNoTicksCompleted(): void
    {
        $kingdom = new Kingdom();
        $season = new LeagueSeason();
        $season->setStartDate(new \DateTimeImmutable('2026-01-05 00:00:00', new \DateTimeZone('UTC')));

        $tickLogs = $this->createMock(KingdomTickLogRepository::class);
        $tickLogs->method('findLatestCompleted')->willReturn(null);

        $seasons = $this->createMock(LeagueSeasonRepository::class);
        $seasons->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($season): ?LeagueSeason {
                if (($criteria['status'] ?? null) === LeagueSeasonStatus::Active) {
                    return $season;
                }

                return null;
            }
        );

        $service = $this->createService(tickLogs: $tickLogs, seasons: $seasons);
        $until = $service->nextHorizon($kingdom, new \DateInterval('P7D'));

        $this->assertSame('2026-01-12 00:00:00', $until->format('Y-m-d H:i:s'));
    }

    public function testScheduleThroughRefusesBlockedKingdom(): void
    {
        $kingdom = new Kingdom();
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 4);

        $blocked = new KingdomTickLog();
        $blocked->setTickType(TickType::DailyReset);
        $blocked->setStatus('failed');
        $logId = new \ReflectionProperty(KingdomTickLog::class, 'id');
        $logId->setValue($blocked, 12);

        $runner = $this->createMock(KingdomTickRunnerService::class);
        $runner->method('findBlockedTick')->willReturn($blocked);

        $service = $this->createService(runner: $runner);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('blocked tick');
        $service->scheduleThrough($kingdom, new \DateTimeImmutable('2026-01-12 00:00:00', new \DateTimeZone('UTC')));
    }

    public function testScheduleThroughOrchestratesWhenTicksArePending(): void
    {
        $kingdom = new Kingdom();
        $until = new \DateTimeImmutable('2026-01-12 00:00:00', new \DateTimeZone('UTC'));

        $runner = $this->createMock(KingdomTickRunnerService::class);
        $runner->method('findBlockedTick')->willReturn(null);
        $runner->expects($this->once())->method('recoverStaleTicks')->with($kingdom);
        $runner->expects($this->once())->method('schedulePendingTicks')->with($kingdom, $until)->willReturn(3);
        $runner->method('hasPendingTicks')->willReturn(true);

        $orchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $orchestrator->expects($this->once())->method('orchestrate')->with($kingdom);

        $service = $this->createService(runner: $runner, orchestrator: $orchestrator);
        $this->assertSame(3, $service->scheduleThrough($kingdom, $until));
    }

    private function createService(
        ?KingdomTickRunnerService $runner = null,
        ?KingdomTickOrchestrator $orchestrator = null,
        ?KingdomTickLogRepository $tickLogs = null,
        ?LeagueSeasonRepository $seasons = null,
        ?SeasonReportRepository $reports = null,
        ?EntityManagerInterface $em = null,
    ): KingdomSimulationService {
        return new KingdomSimulationService(
            $runner ?? $this->createMock(KingdomTickRunnerService::class),
            $orchestrator ?? $this->createMock(KingdomTickOrchestrator::class),
            $tickLogs ?? $this->createMock(KingdomTickLogRepository::class),
            $seasons ?? $this->createMock(LeagueSeasonRepository::class),
            $reports ?? $this->createMock(SeasonReportRepository::class),
            $this->createMock(KingdomRepository::class),
            $em ?? $this->createMock(EntityManagerInterface::class),
        );
    }
}
