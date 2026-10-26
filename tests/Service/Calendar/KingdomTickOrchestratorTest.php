<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\Team\Team;
use App\Enum\TickType;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AllowMockObjectsWithoutExpectations]
class KingdomTickOrchestratorTest extends TestCase
{
    private KingdomTickOrchestrator $orchestrator;

    protected function setUp(): void
    {
        $this->orchestrator = new KingdomTickOrchestrator(
            $this->createMock(KingdomTickLogRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(MessageBusInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testWeeklyResetWavePaysTreasuryBeforeTeamCosts(): void
    {
        $kingdomTick = $this->weeklyResetLog(null);
        $teamTick = $this->weeklyResetLog(new Team());

        $wave = $this->orchestrator->deferTeamWeeklyResetUntilTreasuryFinishes([$teamTick, $kingdomTick]);

        $this->assertSame([$kingdomTick], $wave);
    }

    public function testWeeklyResetWaveDispatchesTeamsAfterTreasuryIsGone(): void
    {
        $teamTick = $this->weeklyResetLog(new Team());

        $wave = $this->orchestrator->deferTeamWeeklyResetUntilTreasuryFinishes([$teamTick]);

        $this->assertSame([$teamTick], $wave);
    }

    public function testWeeklyResetWaveKeepsUnrelatedTicksWithTreasury(): void
    {
        $kingdomTick = $this->weeklyResetLog(null);
        $teamTick = $this->weeklyResetLog(new Team());
        $other = new KingdomTickLog();
        $other->setTickType(TickType::DailyReset);

        $wave = $this->orchestrator->deferTeamWeeklyResetUntilTreasuryFinishes([$other, $teamTick, $kingdomTick]);

        $this->assertSame([$other, $kingdomTick], $wave);
    }

    private function weeklyResetLog(?Team $team): KingdomTickLog
    {
        $log = new KingdomTickLog();
        $log->setTickType(TickType::WeeklyReset);
        $log->setTeam($team);

        return $log;
    }
}
