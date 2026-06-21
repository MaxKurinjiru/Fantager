<?php

declare(strict_types=1);

namespace App\Tests\Service\Team;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\Race;
use App\Repository\Hero\HeroRepository;
use App\Service\Config\RaceConfig;
use App\Service\Team\TeamChemistryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TeamChemistryServiceTest extends TestCase
{
    public function testRecalculateWithFewerThanTwoCombatants(): void
    {
        $team = new Team();

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findCombatantsByTeam')->willReturn([]);

        $raceConfig = $this->createMock(RaceConfig::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new TeamChemistryService($heroRepo, $raceConfig, $em);
        $service->recalculate($team);

        $this->assertSame(50, $team->getChemistry());
    }

    public function testRecalculateBasicAverages(): void
    {
        $team = new Team();

        $heroA = new Hero();
        $heroA->setRace(Race::Human);
        $heroA->setChaRaw(100); // getCha() -> 10

        $heroB = new Hero();
        $heroB->setRace(Race::Elf);
        $heroB->setChaRaw(100); // getCha() -> 10

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findCombatantsByTeam')->willReturn([$heroA, $heroB]);

        $raceConfig = $this->createMock(RaceConfig::class);
        $raceConfig->expects($this->any())->method('getRelationship')->with(Race::Human, Race::Elf)->willReturn(90);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new TeamChemistryService($heroRepo, $raceConfig, $em);
        $service->recalculate($team);

        $this->assertSame(90, $team->getChemistry());
    }

    public function testRecalculateCharismaMitigation(): void
    {
        $team = new Team();

        // Base relationship Elf-Orc is 0 (Hostile)
        $heroA = new Hero();
        $heroA->setRace(Race::Elf);
        $heroA->setChaRaw(150); // getCha() -> 15 (5 offset)

        $heroB = new Hero();
        $heroB->setRace(Race::Orc);
        $heroB->setChaRaw(150); // getCha() -> 15 (5 offset)

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findCombatantsByTeam')->willReturn([$heroA, $heroB]);

        $raceConfig = $this->createMock(RaceConfig::class);
        $raceConfig->expects($this->any())->method('getRelationship')->with(Race::Elf, Race::Orc)->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new TeamChemistryService($heroRepo, $raceConfig, $em);
        $service->recalculate($team);

        // Base = 0, offsets = 5 + 5 = 10, effective = 10.
        // Effective relationship (10) <= 20 is hostile, so hostility penalty of 15 applies.
        // Final: 10 - 15 = -5 -> clamped to 0.
        $this->assertSame(0, $team->getChemistry());

        // Test with higher charisma to reach a neutral relation and avoid hostility penalty
        $heroA->setChaRaw(200); // getCha() -> 20 (10 offset)
        $heroB->setChaRaw(200); // getCha() -> 20 (10 offset)

        $service->recalculate($team);
        // Base = 0, offsets = 10 + 10 = 20, effective = 20.
        // Since effective <= 20, it is still hostile. Penalty = 15.
        // Final: 20 - 15 = 5.
        $this->assertSame(5, $team->getChemistry());

        // If base relation is 10, offsets = 10 + 10 = 20, effective = 30.
        // 30 > 20, so no hostility penalty.
        // Final: 30.
        $raceConfig = $this->createMock(RaceConfig::class);
        $raceConfig->method('getRelationship')->willReturn(10);
        $service = new TeamChemistryService($heroRepo, $raceConfig, $em);
        $service->recalculate($team);
        $this->assertSame(30, $team->getChemistry());
    }

    public function testRecalculateHostilityPenalties(): void
    {
        $team = new Team();

        $heroA = new Hero();
        $heroA->setRace(Race::Orc);
        $heroA->setChaRaw(100); // getCha() -> 10

        $heroB = new Hero();
        $heroB->setRace(Race::Undead);
        $heroB->setChaRaw(100); // getCha() -> 10

        $heroC = new Hero();
        $heroC->setRace(Race::Elf);
        $heroC->setChaRaw(100); // getCha() -> 10

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findCombatantsByTeam')->willReturn([$heroA, $heroB, $heroC]);

        $raceConfig = $this->createMock(RaceConfig::class);
        $raceConfig->method('getRelationship')->willReturnMap([
            [Race::Orc, Race::Undead, 90],
            [Race::Undead, Race::Orc, 90],
            [Race::Orc, Race::Elf, 0],
            [Race::Elf, Race::Orc, 0],
            [Race::Undead, Race::Elf, 10],
            [Race::Elf, Race::Undead, 10],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new TeamChemistryService($heroRepo, $raceConfig, $em);
        $service->recalculate($team);

        // Effective relationships: 90, 0, 10.
        // Hostile pairs: 0, 10 (2 pairs).
        // Average: (90 + 0 + 10) / 3 = 33.33.
        // Hostility penalty: 2 * 15 = 30.
        // Final: round(33.33 - 30) = 3.
        $this->assertSame(3, $team->getChemistry());
    }
}
