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
    private $entityManagerMock;
    private EconomyService $economyService;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->economyService = new EconomyService($this->entityManagerMock);
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
                    $record->getType() === FinancialRecordType::TrainingCost &&
                    $record->getActor() === FinancialRecordActor::Active &&
                    $record->getContext() === ['test' => 123];
            }));

        $this->economyService->deductGold(
            $team,
            30,
            FinancialRecordType::TrainingCost,
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
        $this->expectExceptionMessage('Insufficient gold');

        $this->economyService->deductGold(
            $team,
            30,
            FinancialRecordType::TrainingCost,
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
}
