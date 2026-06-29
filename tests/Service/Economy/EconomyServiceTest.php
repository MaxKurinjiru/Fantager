<?php

declare(strict_types=1);

namespace App\Tests\Service\Economy;

use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Service\Economy\EconomyService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class EconomyServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Doctrine\ORM\EntityManagerInterface */
    private $entityManagerMock;
    private EconomyService $economyService;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->economyService = new EconomyService($this->entityManagerMock, new \App\Service\Calendar\TickClock());
    }

    public function testDeductGoldReducesBalanceAndRecordsTransaction(): void
    {
        $team = new Team();
        $team->setGold(100);

        $calledRecord = null;
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($record) use (&$calledRecord) {
                $calledRecord = $record;
            });

        $this->economyService->deductGold(
            $team,
            30,
            FinancialRecordType::SummonFee,
            FinancialRecordActor::Active,
            ['test' => 123]
        );

        $this->assertInstanceOf(FinancialRecord::class, $calledRecord);
        $this->assertSame($team, $calledRecord->getTeam());
        $this->assertSame(-30, $calledRecord->getGoldChange());
        $this->assertSame(FinancialRecordType::SummonFee, $calledRecord->getType());
        $this->assertSame(FinancialRecordActor::Active, $calledRecord->getActor());
        $this->assertSame(['test' => 123], $calledRecord->getContext());
        $this->assertSame(70, $team->getGold());
    }

    public function testDeductGoldInsufficientThrows(): void
    {
        $team = new Team();
        $team->setGold(20);

        $this->expectException(\DomainException::class);

        $this->economyService->deductGold(
            $team,
            30,
            FinancialRecordType::SummonFee,
            FinancialRecordActor::Active
        );
    }

    public function testAddGoldIncreasesBalanceAndRecordsTransaction(): void
    {
        $team = new Team();
        $team->setGold(50);

        $calledRecord = null;
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($record) use (&$calledRecord) {
                $calledRecord = $record;
            });

        $this->economyService->addGold(
            $team,
            40,
            FinancialRecordType::ArenaRevenue,
            FinancialRecordActor::System
        );

        $this->assertInstanceOf(FinancialRecord::class, $calledRecord);
        $this->assertSame($team, $calledRecord->getTeam());
        $this->assertSame(40, $calledRecord->getGoldChange());
        $this->assertSame(FinancialRecordType::ArenaRevenue, $calledRecord->getType());
        $this->assertSame(FinancialRecordActor::System, $calledRecord->getActor());
        $this->assertSame(90, $team->getGold());
    }

    public function testEssenceLogging(): void
    {
        $team = new Team();
        $team->setEssenceCommon(2);

        $calledRecord = null;
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($record) use (&$calledRecord) {
                $calledRecord = $record;
            });

        $this->economyService->deductEssence(
            $team,
            'common',
            2,
            FinancialRecordType::SpellLearningCost,
            FinancialRecordActor::Active
        );

        $this->assertInstanceOf(FinancialRecord::class, $calledRecord);
        $this->assertSame($team, $calledRecord->getTeam());
        $this->assertSame(-2, $calledRecord->getEssenceCommonChange());
        $this->assertSame(0, $team->getEssenceCommon());
    }

    public function testGoldTransactionUsesTickClockCustomTime(): void
    {
        $clock = new \App\Service\Calendar\TickClock();
        $customTime = new \DateTimeImmutable('2026-06-12 18:00:00', new \DateTimeZone('UTC'));
        $clock->setCustomTime($customTime);

        $service = new EconomyService($this->entityManagerMock, $clock);

        $team = new Team();
        $team->setGold(100);

        $calledRecord = null;
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($record) use (&$calledRecord) {
                $calledRecord = $record;
            });

        $service->deductGold(
            $team,
            30,
            FinancialRecordType::SummonFee,
            FinancialRecordActor::Active
        );

        $this->assertInstanceOf(FinancialRecord::class, $calledRecord);
        $this->assertSame($customTime, $calledRecord->getCreatedAt());
    }
}
