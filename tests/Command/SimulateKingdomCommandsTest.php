<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SimulateKingdomAdvanceCommand;
use App\Command\SimulateKingdomInitCommand;
use App\Command\SimulateKingdomProcessCommand;
use App\Command\SimulateKingdomStatusCommand;
use App\Entity\Kingdom\Kingdom;
use App\Enum\PacePreset;
use App\Service\Auth\TestUserService;
use App\Service\Kingdom\KingdomInitializationService;
use App\Service\Kingdom\KingdomSimulationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class SimulateKingdomCommandsTest extends TestCase
{
    public function testInitWritesKingdomId(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Balance Sim');
        $kingdom->setPacePreset(PacePreset::Classic);
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 4);

        $initialization = $this->createMock(KingdomInitializationService::class);
        $initialization->expects($this->once())->method('initialize')->with('Balance Sim', false, null, 'classic')
            ->willReturn(['kingdom' => $kingdom, 'teams' => 60, 'heroes' => 600]);

        $tester = new CommandTester(new SimulateKingdomInitCommand(
            $initialization,
            $this->createMock(TestUserService::class),
        ));
        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('kingdom_id=4', $tester->getDisplay());
    }

    public function testAdvanceRequiresIdlePipeline(): void
    {
        $kingdom = new Kingdom();
        $simulation = $this->createMock(KingdomSimulationService::class);
        $simulation->method('requireKingdom')->willReturn($kingdom);
        $simulation->method('stepInterval')->willReturn(new \DateInterval('P7D'));
        $simulation->method('nextHorizon')->willReturn(new \DateTimeImmutable('2026-03-08 00:00:00', new \DateTimeZone('UTC')));
        $simulation->method('getPipelineSnapshot')->willReturn([
            'open_ticks' => 3,
            'messenger_async_count' => 1,
            'failed' => [],
            'blocked' => null,
            'completed_seasons' => 0,
        ]);
        $simulation->method('pipelineExitCode')->willReturn(KingdomSimulationService::EXIT_BUSY);
        $simulation->expects($this->never())->method('scheduleThrough');

        $tester = new CommandTester(new SimulateKingdomAdvanceCommand($simulation));
        $this->assertSame(KingdomSimulationService::EXIT_BUSY, $tester->execute(['--kingdom-id' => '1']));
    }

    public function testProcessReturnsIdleWithoutConsuming(): void
    {
        $kingdom = new Kingdom();
        $simulation = $this->createMock(KingdomSimulationService::class);
        $simulation->method('requireKingdom')->willReturn($kingdom);
        $simulation->method('getPipelineSnapshot')->willReturn([
            'kingdom_id' => 1,
            'open_ticks' => 0,
            'messenger_async_count' => 0,
            'failed' => [],
            'blocked' => null,
            'completed_seasons' => 2,
            'latest_completed_at' => '2026-03-01 18:00:00',
        ]);
        $simulation->method('pipelineExitCode')->willReturn(KingdomSimulationService::EXIT_IDLE);

        $tester = new CommandTester(new SimulateKingdomProcessCommand($simulation));
        $this->assertSame(KingdomSimulationService::EXIT_IDLE, $tester->execute(['--kingdom-id' => '1']));
        $this->assertStringContainsString('state=idle', $tester->getDisplay());
    }

    public function testStatusJsonUsesBusyExitCode(): void
    {
        $kingdom = new Kingdom();
        $simulation = $this->createMock(KingdomSimulationService::class);
        $simulation->method('requireKingdom')->willReturn($kingdom);
        $simulation->method('getPipelineSnapshot')->willReturn([
            'kingdom_id' => 1,
            'name' => 'Balance Sim',
            'completed_seasons' => 1,
            'open_ticks' => 4,
            'messenger_async_count' => 9,
            'latest_completed_at' => null,
            'oldest_pending_at' => '2026-03-02 00:00:00',
        ]);
        $simulation->method('pipelineExitCode')->willReturn(KingdomSimulationService::EXIT_BUSY);

        $tester = new CommandTester(new SimulateKingdomStatusCommand($simulation));
        $this->assertSame(KingdomSimulationService::EXIT_BUSY, $tester->execute([
            '--kingdom-id' => '1',
            '--json' => true,
        ]));
        $this->assertStringContainsString('"state":"busy"', $tester->getDisplay());
        $this->assertStringContainsString('"completed_seasons":1', $tester->getDisplay());
    }
}
