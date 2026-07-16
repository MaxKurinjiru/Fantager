<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\MatchType;
use App\Enum\Race;
use App\Service\Combat\CombatMatchRequestBuilder;
use App\Service\Combat\CombatStatCalculator;
use App\ValueObject\Combat\DerivedCombatStats;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CombatMatchRequestBuilderTest extends TestCase
{
    public function testFromFormationsBuildsSixCombatantsPerSide(): void
    {
        $stats = new DerivedCombatStats(100, 100, 10, 10, 10, 0.1, 10, 0.1, 10, 80.0, 10.0, 5.0);
        $calculator = $this->createMock(CombatStatCalculator::class);
        $calculator->method('calculate')->willReturn($stats);

        $builder = new CombatMatchRequestBuilder($calculator);
        $request = $builder->fromFormations(
            $this->createFullFormation(1, 10),
            $this->createFullFormation(2, 20),
            MatchType::League,
            55,
        );

        $this->assertSame(55, $request->getSeed());
        $this->assertSame(MatchType::League, $request->getMatchType());
        $this->assertSame(1, $request->getSideA()->getTeamId());
        $this->assertSame(2, $request->getSideB()->getTeamId());
        $this->assertCount(6, $request->getSideA()->getCombatants());
        $this->assertSame(FormationPosition::Front1, $request->getSideA()->getCombatants()[0]->getSlot());
        $this->assertSame(FormationApproach::Aggressive, $request->getSideA()->getApproach());
    }

    public function testFromFormationsRequiresFilledSlots(): void
    {
        $stats = new DerivedCombatStats(100, 100, 10, 10, 10, 0.1, 10, 0.1, 10, 80.0, 10.0, 5.0);
        $calculator = $this->createMock(CombatStatCalculator::class);
        $calculator->method('calculate')->willReturn($stats);
        $builder = new CombatMatchRequestBuilder($calculator);

        $formationA = $this->createFullFormation(1, 10);
        $formationB = new Formation();
        $team = new Team();
        $this->setId($team, 2);
        $formationB->setTeam($team);
        $formationB->setName('Empty');
        $formationB->setApproach(FormationApproach::Balanced);
        $this->setId($formationB, 200);

        $this->expectException(\InvalidArgumentException::class);
        $builder->fromFormations($formationA, $formationB, MatchType::League, 1);
    }

    private function createFullFormation(int $teamId, int $heroIdBase): Formation
    {
        $team = new Team();
        $this->setId($team, $teamId);

        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setName('Test');
        $formation->setApproach(FormationApproach::Aggressive);
        $this->setId($formation, $teamId * 100);

        foreach (FormationPosition::cases() as $i => $position) {
            $hero = new Hero();
            $hero->setName('H'.($heroIdBase + $i));
            $hero->setRace(Race::Human);
            $hero->setLevel(5);
            $hero->setForm(100);
            $hero->setFatigue(0);
            $hero->setMorale(50);
            $this->setId($hero, $heroIdBase + $i);

            $slot = new FormationSlot();
            $slot->setFormation($formation);
            $slot->setPosition($position);
            $slot->setHero($hero);
            $slot->setStrategy([]);
            $slot->setSpellPriorities([]);
            $formation->addSlot($slot);
        }

        return $formation;
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
