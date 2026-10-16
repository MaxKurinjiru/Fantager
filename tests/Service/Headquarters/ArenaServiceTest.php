<?php

declare(strict_types=1);

namespace App\Tests\Service\Headquarters;

use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\Team\FinancialRecordRepository;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Headquarters\ArenaService;
use App\Service\Team\FanClubService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ArenaServiceTest extends TestCase
{
    /** @var FinancialRecordRepository&MockObject */
    private $financialRecordRepository;
    private ArenaService $service;
    private Team $team;

    protected function setUp(): void
    {
        $this->financialRecordRepository = $this->createMock(FinancialRecordRepository::class);
        $this->team = new Team();
        $this->service = new ArenaService(
            $this->createStub(HeadquartersRepository::class),
            $this->createStub(ArenaRevenueService::class),
            $this->createStub(FanClubService::class),
            $this->financialRecordRepository,
            $this->createStub(LeagueFixtureRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    public function testBuildRevenueHistoryUsesEighteenMatchWindow(): void
    {
        $this->financialRecordRepository
            ->expects($this->once())
            ->method('findRecentByTeamAndType')
            ->with($this->team, FinancialRecordType::ArenaRevenue->value, ArenaService::HISTORY_WINDOW)
            ->willReturn([]);

        $history = $this->service->buildRevenueHistory($this->team);

        $this->assertSame(18, ArenaService::HISTORY_WINDOW);
        $this->assertSame([], $history['recent_revenue']);
        $this->assertSame([
            'window' => 18,
            'match_count' => 0,
            'total_gold' => 0,
            'average_attendance' => null,
        ], $history['analytics']);
    }

    public function testBuildRevenueHistoryComputesOccupancyAndSummary(): void
    {
        $this->financialRecordRepository
            ->expects($this->once())
            ->method('findRecentByTeamAndType')
            ->willReturn([
                $this->makeRecord(1200, [
                    'attendance' => 400,
                    'capacity' => 500,
                    'away_team_name' => 'Ravens',
                    'ticket_price' => 6,
                ], '2026-10-10T18:00:00+00:00'),
                $this->makeRecord(800, [
                    'attendance' => 200,
                    'capacity' => 500,
                    'away_team_name' => 'Wolves',
                    'ticket_price' => 8,
                ], '2026-10-03T18:00:00+00:00'),
            ]);

        $history = $this->service->buildRevenueHistory($this->team);

        $this->assertCount(2, $history['recent_revenue']);
        $this->assertSame(80.0, $history['recent_revenue'][0]['occupancy_pct']);
        $this->assertSame(40.0, $history['recent_revenue'][1]['occupancy_pct']);
        $this->assertSame(400, $history['recent_revenue'][0]['attendance']);
        $this->assertSame('Ravens', $history['recent_revenue'][0]['opponent_name']);
        $this->assertSame([
            'window' => 18,
            'match_count' => 2,
            'total_gold' => 2000,
            'average_attendance' => 300,
        ], $history['analytics']);
    }

    public function testBuildRevenueHistorySkipsMissingAttendanceInAverage(): void
    {
        $this->financialRecordRepository
            ->expects($this->once())
            ->method('findRecentByTeamAndType')
            ->willReturn([
                $this->makeRecord(500, [
                    'attendance' => 100,
                    'capacity' => 500,
                ], '2026-10-10T18:00:00+00:00'),
                $this->makeRecord(250, [], '2026-10-03T18:00:00+00:00'),
            ]);

        $history = $this->service->buildRevenueHistory($this->team);

        $this->assertNull($history['recent_revenue'][1]['attendance']);
        $this->assertNull($history['recent_revenue'][1]['occupancy_pct']);
        $this->assertSame(750, $history['analytics']['total_gold']);
        $this->assertSame(100, $history['analytics']['average_attendance']);
        $this->assertSame(2, $history['analytics']['match_count']);
    }

    public function testBuildRevenueHistoryLeavesOccupancyNullWhenCapacityIsZero(): void
    {
        $this->financialRecordRepository
            ->expects($this->once())
            ->method('findRecentByTeamAndType')
            ->willReturn([
                $this->makeRecord(0, [
                    'attendance' => 0,
                    'capacity' => 0,
                ], '2026-10-10T18:00:00+00:00'),
            ]);

        $history = $this->service->buildRevenueHistory($this->team);

        $this->assertSame(0, $history['recent_revenue'][0]['attendance']);
        $this->assertNull($history['recent_revenue'][0]['occupancy_pct']);
        $this->assertSame(0, $history['analytics']['average_attendance']);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function makeRecord(int $gold, array $context, string $createdAt): FinancialRecord
    {
        $record = new FinancialRecord();
        $record->setGoldChange($gold);
        $record->setContext($context);
        $record->setCreatedAt(new \DateTimeImmutable($createdAt));

        return $record;
    }
}
