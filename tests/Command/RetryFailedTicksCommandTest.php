<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RetryFailedTicksCommand;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\KingdomTickRunnerService;
use App\Service\Calendar\KingdomTickOrchestrator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class RetryFailedTicksCommandTest extends TestCase
{
    public function testRequiresKingdomId(): void
    {
        $tester = new CommandTester(new RetryFailedTicksCommand(
            $this->createMock(KingdomRepository::class),
            $this->createMock(KingdomTickRunnerService::class),
            $this->createMock(KingdomTickOrchestrator::class),
        ));

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Pass --kingdom-id', $tester->getDisplay());
    }

    public function testResetsFailedTicks(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Main');
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 4);

        $log = new KingdomTickLog();
        $log->setTickType(TickType::WeeklyTraining);
        $log->setScheduledAt(new \DateTimeImmutable('2026-06-11 10:00:00', new \DateTimeZone('UTC')));
        $logId = new \ReflectionProperty(KingdomTickLog::class, 'id');
        $logId->setValue($log, 12);

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->expects($this->once())->method('find')->with(4)->willReturn($kingdom);

        $tickRunner = $this->createMock(KingdomTickRunnerService::class);
        $tickRunner->expects($this->once())
            ->method('retryFailedTicks')
            ->with($kingdom, null)
            ->willReturn([$log]);

        $tester = new CommandTester(new RetryFailedTicksCommand($kingdomRepository, $tickRunner, $this->createMock(KingdomTickOrchestrator::class)));

        $this->assertSame(0, $tester->execute(['--kingdom-id' => '4']));
        $this->assertStringContainsString('Reset 1 tick(s)', $tester->getDisplay());
        $this->assertStringContainsString('app:ticks:run --kingdom-id=4', $tester->getDisplay());
    }

    public function testCombatRecoveryRequiresTickId(): void
    {
        $repository = $this->createMock(KingdomRepository::class);
        $repository->method('find')->willReturn(new Kingdom());
        $orchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $orchestrator->expects($this->never())->method('resumeCombat');
        $tester = new CommandTester(new RetryFailedTicksCommand($repository, $this->createMock(KingdomTickRunnerService::class), $orchestrator));

        $this->assertSame(1, $tester->execute(['--kingdom-id' => '4', '--combat' => true]));
        $this->assertStringContainsString('Pass --tick-id', $tester->getDisplay());
    }

    public function testCombatRecoveryQueuesContinuation(): void
    {
        $kingdom = new Kingdom();
        $repository = $this->createMock(KingdomRepository::class);
        $repository->method('find')->willReturn($kingdom);
        $orchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $orchestrator->expects($this->once())->method('resumeCombat')->with($kingdom, 12);
        $tester = new CommandTester(new RetryFailedTicksCommand($repository, $this->createMock(KingdomTickRunnerService::class), $orchestrator));

        $this->assertSame(0, $tester->execute(['--kingdom-id' => '4', '--tick-id' => '12', '--combat' => true]));
        $this->assertStringContainsString('Combat continuation queued', $tester->getDisplay());
    }
}
