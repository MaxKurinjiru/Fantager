<?php

declare(strict_types=1);

namespace App\Tests\Service\Headquarters;

use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Entity\Kingdom\Kingdom;
use App\Exception\UserFacingException;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Headquarters\HeadquartersService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class HeadquartersServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Repository\Headquarters\HeadquartersRepository */
    private $hqRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\EconomyService */
    private $economyServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\FinancialCrisisService */
    private $financialCrisisServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\RoyalTreasuryService */
    private $royalTreasuryServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Doctrine\ORM\EntityManagerInterface */
    private $entityManagerMock;
    private HeadquartersService $service;

    protected function setUp(): void
    {
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->financialCrisisServiceMock = $this->createMock(FinancialCrisisService::class);
        $this->royalTreasuryServiceMock = $this->createMock(\App\Service\Economy\RoyalTreasuryService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->service = new HeadquartersService(
            $this->hqRepositoryMock,
            $this->economyServiceMock,
            $this->financialCrisisServiceMock,
            $this->royalTreasuryServiceMock,
            $this->entityManagerMock
        );
    }

    public function testUpdateRaceOptimizationThrowsExceptionWhenLocked(): void
    {
        $team = new Team();
        $hq = new Headquarters();
        $hq->setPendingRaceOptimization('elf');
        $hq->setHasPendingRaceOptimizationChange(true);

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.hq_race_optimization_locked');

        $this->service->updateRaceOptimization($team, 'orc');
    }

    public function testUpdateRaceOptimizationSetsPending(): void
    {
        $team = new Team();
        $hq = new Headquarters();
        $hq->setRaceOptimization('human');

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $result = $this->service->updateRaceOptimization($team, 'elf');

        $this->assertSame('human', $result->getRaceOptimization());
        $this->assertSame('elf', $result->getPendingRaceOptimization());
        $this->assertTrue($result->hasPendingRaceOptimizationChange());
    }

    public function testUpdateRaceOptimizationNoOpForSameRace(): void
    {
        $team = new Team();
        $hq = new Headquarters();
        $hq->setRaceOptimization('human');

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $result = $this->service->updateRaceOptimization($team, 'human');

        $this->assertNull($result->getPendingRaceOptimization());
        $this->assertFalse($result->hasPendingRaceOptimizationChange());
    }

    public function testProcessRaceOptimizationTick(): void
    {
        $kingdom = new Kingdom();
        $hq = new Headquarters();
        $hq->setRaceOptimization('human');
        $hq->setPendingRaceOptimization('elf');
        $hq->setHasPendingRaceOptimizationChange(true);

        $this->hqRepositoryMock
            ->expects($this->exactly(2))
            ->method('findByKingdom')
            ->with($kingdom)
            ->willReturn([$hq]);

        $this->service->processRaceOptimizationTick($kingdom);

        // First tick: pending applies, lock cycle becomes true
        $this->assertSame('elf', $hq->getRaceOptimization());
        $this->assertNull($hq->getPendingRaceOptimization());
        $this->assertFalse($hq->hasPendingRaceOptimizationChange());
        $this->assertTrue($hq->isRaceOptimizationLockCycle());

        // Second tick: lock cycle becomes false
        $this->service->processRaceOptimizationTick($kingdom);
        $this->assertFalse($hq->isRaceOptimizationLockCycle());
    }

    public function testGetRosterLimitReturnsDefaultWhenNoHq(): void
    {
        $team = new Team();
        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn(null);

        $limit = $this->service->getRosterLimit($team);
        $this->assertSame(10, $limit);
    }

    public function testGetRosterLimitCalculatesCorrectly(): void
    {
        $team = new Team();
        $hq = new Headquarters();
        $facility = new \App\Entity\Headquarters\Facility();
        $facility->setType(\App\Enum\FacilityType::Barracks);
        $facility->setLevel(2); // Level 2 barracks adds 4.0, total 14
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $limit = $this->service->getRosterLimit($team);
        $this->assertSame(14, $limit);
    }

    public function testUpgradeFacilityCalculatesDurationAndSetsCompletedAt(): void
    {
        $team = new Team();
        $kingdom = new Kingdom();
        $kingdom->setGameSpeed('1.00');
        $team->setKingdom($kingdom);

        $hq = new Headquarters();
        $facility = new \App\Entity\Headquarters\Facility();
        $facility->setType(\App\Enum\FacilityType::Library);
        $facility->setLevel(2);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $now = new \DateTimeImmutable('2026-06-12 12:00:00', new \DateTimeZone('UTC'));

        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold')
            ->with($team, $this->anything(), $this->anything(), $this->anything(), $this->anything());

        $this->financialCrisisServiceMock
            ->expects($this->once())
            ->method('assertSpendingAllowed')
            ->with($team, 'hq_upgrade');

        $result = $this->service->upgradeFacility($team, \App\Enum\FacilityType::Library, $now);

        $this->assertSame($facility, $result);
        $this->assertSame($facility, $hq->getUpgradingFacility());
        $expectedCompletion = $now->modify('+259200 seconds');
        $this->assertEquals($expectedCompletion, $hq->getUpgradeCompletedAt());
    }

    public function testUpgradeFacilityThrowsExceptionIfAnotherUpgradeInProgress(): void
    {
        $team = new Team();
        $hq = new Headquarters();
        $facility = new \App\Entity\Headquarters\Facility();
        $facility->setType(\App\Enum\FacilityType::Library);
        $facility->setLevel(1);
        $hq->addFacility($facility);
        $hq->setUpgradingFacility($facility);

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.hq_facility_change_in_progress');

        $this->service->upgradeFacility($team, \App\Enum\FacilityType::Library);
    }

    public function testCalculateUpgradeCostScalesWithTotalLevel(): void
    {
        $baseCost = $this->service->calculateUpgradeCost(\App\Enum\FacilityType::Training, 1, 7);
        $this->assertSame(500, $baseCost);

        $scaledCost = $this->service->calculateUpgradeCost(\App\Enum\FacilityType::Training, 1, 11);
        $this->assertSame(550, $scaledCost);
    }

    public function testCalculateWeeklyMaintenanceFee(): void
    {
        $hq = new Headquarters();
        foreach (\App\Enum\FacilityType::cases() as $type) {
            $facility = new \App\Entity\Headquarters\Facility();
            $facility->setType($type);
            $facility->setLevel(1);
            $hq->addFacility($facility);
        }

        // HQ: 50 + 7*3 = 71, facilities: 25+20+30+22+18+40+45 = 200
        $this->assertSame(271, $this->service->calculateWeeklyMaintenanceFee($hq));
    }

    public function testProcessMaintenanceTickDeductsGold(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setGold(500);
        $team->setKingdom($kingdom);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $facility = new \App\Entity\Headquarters\Facility();
        $facility->setType(\App\Enum\FacilityType::Training);
        $facility->setLevel(1);
        $hq->addFacility($facility);
        $hq->syncTotalLevel();

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findByKingdom')
            ->with($kingdom)
            ->willReturn([$hq]);

        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold')
            ->with(
                $team,
                $this->callback(static fn (int $amount): bool => $amount > 0 && $amount <= 500),
                \App\Enum\FinancialRecordType::HqMaintenanceFee,
                \App\Enum\FinancialRecordActor::System,
                $this->callback(static fn (array $context): bool => isset($context['fee_due'], $context['hq_fee'], $context['facilities_fee']))
            );

        $this->financialCrisisServiceMock
            ->expects($this->never())
            ->method('addUnpaidDebt');

        $this->royalTreasuryServiceMock
            ->expects($this->once())
            ->method('collectFee');

        $this->service->processMaintenanceTick($kingdom);
    }

    public function testProcessMaintenanceTickAddsDebtWhenGoldInsufficient(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setGold(50);
        $team->setKingdom($kingdom);

        $hq = new Headquarters();
        $hq->setTeam($team);
        foreach (\App\Enum\FacilityType::cases() as $type) {
            $facility = new \App\Entity\Headquarters\Facility();
            $facility->setType($type);
            $facility->setLevel(1);
            $hq->addFacility($facility);
        }
        $hq->syncTotalLevel();

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findByKingdom')
            ->with($kingdom)
            ->willReturn([$hq]);

        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold');

        $this->financialCrisisServiceMock
            ->expects($this->once())
            ->method('addUnpaidDebt')
            ->with($team, $this->greaterThan(0));

        $this->royalTreasuryServiceMock
            ->expects($this->once())
            ->method('collectFee');

        $this->service->processMaintenanceTick($kingdom);
    }

    public function testProcessMaintenanceTickRecordsFullyUnpaidFee(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setGold(0);
        $team->setKingdom($kingdom);

        $hq = new Headquarters();
        $hq->setTeam($team);
        foreach (\App\Enum\FacilityType::cases() as $type) {
            $facility = new \App\Entity\Headquarters\Facility();
            $facility->setType($type);
            $facility->setLevel(1);
            $hq->addFacility($facility);
        }
        $hq->syncTotalLevel();

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findByKingdom')
            ->with($kingdom)
            ->willReturn([$hq]);

        $this->economyServiceMock
            ->expects($this->never())
            ->method('deductGold');

        $this->economyServiceMock
            ->expects($this->once())
            ->method('recordLedgerEntry');

        $this->financialCrisisServiceMock
            ->expects($this->once())
            ->method('addUnpaidDebt')
            ->with($team, 271);

        $this->royalTreasuryServiceMock
            ->expects($this->never())
            ->method('collectFee');

        $this->service->processMaintenanceTick($kingdom);
    }

    public function testProcessFacilityUpgradesTickCompletesUpgrades(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setName('Test Team');
        $hq = new Headquarters();
        $hq->setTeam($team);
        $facility = new \App\Entity\Headquarters\Facility();
        $facility->setType(\App\Enum\FacilityType::Library);
        $facility->setLevel(2);
        $hq->addFacility($facility);

        $hq->setUpgradingFacility($facility);
        $now = new \DateTimeImmutable('2026-06-12 12:00:00', new \DateTimeZone('UTC'));
        $hq->setUpgradeCompletedAt($now);
        $hq->setFacilityOperation(\App\Enum\FacilityOperation::Upgrade);

        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findByKingdom')
            ->with($kingdom)
            ->willReturn([$hq]);

        $this->service->processFacilityUpgradesTick($kingdom, $now);

        $this->assertSame(3, $facility->getLevel());
        $this->assertNull($hq->getUpgradingFacility());
        $this->assertNull($hq->getUpgradeCompletedAt());
        $this->assertSame(3, $hq->getComputedTotalLevel());
    }
}
