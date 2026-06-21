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

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (FinancialRecord $record) use ($team) {
                return $record->getTeam() === $team &&
                    $record->getGoldChange() === -30 &&
                    $record->getType() === FinancialRecordType::SummonFee &&
                    $record->getActor() === FinancialRecordActor::Active &&
                    $record->getContext() === ['test' => 123];
            }));

        $this->economyService->deductGold(
            $team,
            30,
            FinancialRecordType::SummonFee,
            FinancialRecordActor::Active,
            ['test' => 123]
        );

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

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (FinancialRecord $record) use ($team) {
                return $record->getTeam() === $team &&
                    $record->getGoldChange() === 40 &&
                    $record->getType() === FinancialRecordType::ArenaRevenue &&
                    $record->getActor() === FinancialRecordActor::System;
            }));

        $this->economyService->addGold(
            $team,
            40,
            FinancialRecordType::ArenaRevenue,
            FinancialRecordActor::System
        );

        $this->assertSame(90, $team->getGold());
    }

    public function testEssenceLogging(): void
    {
        $team = new Team();
        $team->setEssenceCommon(2);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (FinancialRecord $record) use ($team) {
                return $record->getTeam() === $team &&
                    $record->getEssenceCommonChange() === -2;
            }));

        $this->economyService->deductEssence(
            $team,
            'common',
            2,
            FinancialRecordType::SpellLearningCost,
            FinancialRecordActor::Active
        );

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

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (FinancialRecord $record) use ($customTime) {
                return $record->getCreatedAt() === $customTime;
            }));

        $service->deductGold(
            $team,
            30,
            FinancialRecordType::SummonFee,
            FinancialRecordActor::Active
        );
    }
}
