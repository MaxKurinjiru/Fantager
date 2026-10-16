<?php

declare(strict_types=1);

namespace App\Tests\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Service\Formation\FormationService;
use App\Service\Formation\MatchLineupService;
use App\Service\Team\TeamRosterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MatchLineupServiceTest extends TestCase
{
    public function testUsesAssignedWhenMatchReady(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createMatchReadyFormation($team, 10);

        $service = $this->createService($team, [], null);
        $resolved = $service->resolveForKickoff($team, $assigned);

        $this->assertSame($assigned, $resolved);
        $this->assertTrue($service->canField($team, $assigned));
    }

    public function testFallsBackToDefaultWhenAssignedIsIncomplete(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createEmptyFormation($team, 'Custom');
        $default = $this->createMatchReadyFormation($team, 20);
        $default->setIsDefault(true);

        $service = $this->createService($team, [], $default);
        $resolved = $service->resolveForKickoff($team, $assigned);

        $this->assertSame($default, $resolved);
    }

    public function testFillsRemainingSlotsFromRosterWhenNeitherLineupIsReady(): void
    {
        $team = $this->createTeam(1);
        $kept = $this->createHero($team, 1);
        $assigned = $this->createEmptyFormation($team, 'Draft');
        $slot = new FormationSlot();
        $slot->setPosition(FormationPosition::Front1);
        $slot->setHero($kept);
        $slot->setStrategy(['movement_goal' => 'advance_to_melee']);
        $slot->setSpellPriorities([]);
        $assigned->addSlot($slot);

        $bench = [$kept];
        for ($i = 2; $i <= 6; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($team, $bench, null, $em);
        $fixture = new LeagueFixture();
        $resolved = $service->resolveForKickoff($team, $assigned, $fixture);

        $this->assertInstanceOf(Formation::class, $resolved);
        $this->assertTrue($resolved->isTemporary());
        $this->assertSame($fixture, $resolved->getSourceFixture());
        $this->assertCount(6, $resolved->getSlots());

        $heroIds = [];
        foreach ($resolved->getSlots() as $filledSlot) {
            $hero = $filledSlot->getHero();
            $this->assertInstanceOf(Hero::class, $hero);
            $heroIds[] = $hero->getId();
        }
        sort($heroIds);
        $this->assertSame([1, 2, 3, 4, 5, 6], $heroIds);
    }

    public function testCannotFieldWhenFewerThanSixCombatReadyHeroes(): void
    {
        $team = $this->createTeam(1);
        $bench = [];
        for ($i = 1; $i <= 5; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $service = $this->createService($team, $bench, null);
        $this->assertFalse($service->canField($team, null));
        $this->assertNull($service->resolveForKickoff($team, null));
    }

    public function testSkipsDuplicateAndUnreadyAssignedHeroesWhenFilling(): void
    {
        $team = $this->createTeam(1);
        $readyA = $this->createHero($team, 1);
        $unready = $this->createHero($team, 99);
        $unready->setStatus(HeroStatus::Recovering);

        $assigned = $this->createEmptyFormation($team, 'Dupes');
        foreach ([FormationPosition::Front1, FormationPosition::Front2] as $position) {
            $slot = new FormationSlot();
            $slot->setPosition($position);
            $slot->setHero($readyA);
            $slot->setStrategy([]);
            $slot->setSpellPriorities([]);
            $assigned->addSlot($slot);
        }
        $badSlot = new FormationSlot();
        $badSlot->setPosition(FormationPosition::Front3);
        $badSlot->setHero($unready);
        $badSlot->setStrategy([]);
        $badSlot->setSpellPriorities([]);
        $assigned->addSlot($badSlot);

        $bench = [$readyA];
        for ($i = 2; $i <= 6; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($team, $bench, null, $em);
        $resolved = $service->resolveForKickoff($team, $assigned);

        $this->assertInstanceOf(Formation::class, $resolved);
        $ids = [];
        foreach ($resolved->getSlots() as $slot) {
            $ids[] = $slot->getHero()?->getId();
        }
        $this->assertCount(6, array_unique($ids));
        $this->assertNotContains(99, $ids);
        $this->assertContains(1, $ids);
    }

    /**
     * @param list<Hero> $bench
     */
    private function createService(
        Team $team,
        array $bench,
        ?Formation $default,
        ?EntityManagerInterface $em = null,
    ): MatchLineupService {
        $formationService = $this->createMock(FormationService::class);
        $formationService->method('findDefaultForTeam')->willReturnCallback(
            function (Team $t) use ($team, $default): ?Formation {
                return $t === $team ? $default : null;
            }
        );

        $roster = $this->createMock(TeamRosterService::class);
        $roster->method('listCombatReadyHeroes')->willReturn($bench);
        $roster->method('isCombatReady')->willReturnCallback(
            static fn (Hero $hero): bool => HeroRole::Combatant === $hero->getRole()
                && HeroStatus::Available === $hero->getStatus()
        );

        return new MatchLineupService(
            $formationService,
            $roster,
            $em ?? $this->createMock(EntityManagerInterface::class),
        );
    }

    private function createTeam(int $id): Team
    {
        $team = new Team();
        $team->setName('Testers');
        $this->setId($team, $id);

        return $team;
    }

    private function createHero(Team $team, int $id): Hero
    {
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setName('Hero '.$id);
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);
        $this->setId($hero, $id);

        return $hero;
    }

    private function createEmptyFormation(Team $team, string $name): Formation
    {
        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setName($name);
        $formation->setApproach(FormationApproach::Balanced);
        $this->setId($formation, 50);

        return $formation;
    }

    private function createMatchReadyFormation(Team $team, int $heroIdBase): Formation
    {
        $formation = $this->createEmptyFormation($team, 'Ready');
        foreach (FormationPosition::cases() as $i => $position) {
            $slot = new FormationSlot();
            $slot->setPosition($position);
            $slot->setHero($this->createHero($team, $heroIdBase + $i));
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
