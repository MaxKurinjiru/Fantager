<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RetryFailedTicksCommand;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\KingdomTickRunnerService;
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
        $kingdomRepository->method('find')->with(4)->willReturn($kingdom);

        $tickRunner = $this->createMock(KingdomTickRunnerService::class);
        $tickRunner->expects($this->once())
            ->method('retryFailedTicks')
            ->with($kingdom, null)
            ->willReturn([$log]);

        $tester = new CommandTester(new RetryFailedTicksCommand($kingdomRepository, $tickRunner));

        $this->assertSame(0, $tester->execute(['--kingdom-id' => '4']));
        $this->assertStringContainsString('Reset 1 failed tick(s)', $tester->getDisplay());
        $this->assertStringContainsString('app:ticks:run --kingdom-id=4', $tester->getDisplay());
    }
}
