<?php

declare(strict_types=1);

namespace App\Tests\Service\Headquarters;

use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Entity\Kingdom\Kingdom;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Economy\EconomyService;
use App\Service\Headquarters\HeadquartersService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class HeadquartersServiceTest extends TestCase
{
    private $hqRepositoryMock;
    private $economyServiceMock;
    private $entityManagerMock;
    private HeadquartersService $service;

    protected function setUp(): void
    {
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->service = new HeadquartersService(
            $this->hqRepositoryMock,
            $this->economyServiceMock,
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

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Race optimization is currently locked.');

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
}
