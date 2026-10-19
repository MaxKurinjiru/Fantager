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

    public function testPlayerForfeitsWhenAssignedFormationIsIncomplete(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createEmptyFormation($team, 'Custom');
        $default = $this->createMatchReadyFormation($team, 20);
        $default->setIsDefault(true);

        $service = $this->createService($team, [], $default);

        $this->assertFalse($service->canField($team, $assigned));
        $this->assertNull($service->resolveForKickoff($team, $assigned));
    }

    public function testNpcIgnoresAssignmentAndFillsAShortDefaultFromTheRoster(): void
    {
        $team = $this->createTeam(1);
        $team->setIsNpc(true);
        $kept = $this->createHero($team, 1);
        $assigned = $this->createMatchReadyFormation($team, 40);
        $default = $this->createEmptyFormation($team, 'Draft');
        $default->setIsDefault(true);
        $slot = new FormationSlot();
        $slot->setPosition(FormationPosition::Front1);
        $slot->setHero($kept);
        $slot->setStrategy(['movement_goal' => 'advance_to_melee']);
        $slot->setSpellPriorities([]);
        $default->addSlot($slot);

        $bench = [$kept];
        for ($i = 2; $i <= 6; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $service = $this->createService($team, $bench, $default, $em);
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

    public function testPreviewReportsAssignedFormationWhenItIsMatchReady(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createMatchReadyFormation($team, 10);

        $preview = $this->createService($team, [], null)->preview($team, $assigned);

        $this->assertSame('assigned', $preview['source']);
        $this->assertSame(6, $preview['ready_slots']);
        $this->assertFalse($preview['will_forfeit']);
        $this->assertFalse($preview['will_autofill']);
        $this->assertFalse($preview['uses_default_fallback']);
    }

    public function testPreviewForfeitsAShortAssignedFormationEvenWhenTheDefaultIsReady(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createEmptyFormation($team, 'Custom');
        $default = $this->createMatchReadyFormation($team, 20);
        $default->setIsDefault(true);

        $preview = $this->createService($team, [], $default)->preview($team, $assigned);

        $this->assertSame('assigned', $preview['source']);
        $this->assertSame('Custom', $preview['formation_name']);
        $this->assertTrue($preview['will_forfeit']);
        $this->assertFalse($preview['will_autofill']);
        $this->assertFalse($preview['uses_default_fallback']);
    }

    public function testPreviewOffersPlayerFillWhenRosterCanComplete(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createEmptyFormation($team, 'Draft');
        $bench = [];
        for ($i = 1; $i <= 6; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $preview = $this->createService($team, $bench, null)->preview($team, $assigned);

        $this->assertSame('assigned', $preview['source']);
        $this->assertTrue($preview['will_forfeit']);
        $this->assertFalse($preview['will_autofill']);
        $this->assertTrue($preview['can_fill']);
        $this->assertCount(6, $preview['slots']);
        $this->assertSame('empty', $preview['slots'][0]['issue']);
    }

    public function testProposeRosterFillBuildsSixCombatReadySlots(): void
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

        $proposal = $this->createService($team, $bench, null)->proposeRosterFill($team, $assigned);

        $this->assertSame('Draft', $proposal['name']);
        $this->assertCount(6, $proposal['slots']);
        $ids = array_column($proposal['slots'], 'hero_id');
        sort($ids);
        $this->assertSame([1, 2, 3, 4, 5, 6], $ids);
        $this->assertSame('advance_to_melee', $proposal['slots'][0]['strategy']['movement_goal']);
    }

    public function testProposeRosterFillFailsWhenRosterCannotComplete(): void
    {
        $team = $this->createTeam(1);
        $bench = [];
        for ($i = 1; $i <= 5; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $this->expectException(\App\Exception\UserFacingException::class);
        $this->expectExceptionMessage('error.formation_fill_cannot_complete');

        $this->createService($team, $bench, null)->proposeRosterFill($team, null);
    }

    public function testProposeRosterFillRejectsMatchReadyFormation(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createMatchReadyFormation($team, 10);

        $this->expectException(\App\Exception\UserFacingException::class);
        $this->expectExceptionMessage('error.formation_fill_already_ready');

        $this->createService($team, [], null)->proposeRosterFill($team, $assigned);
    }

    public function testPreviewDoesNotAutofillAPlayerRoster(): void
    {
        $team = $this->createTeam(1);
        $assigned = $this->createEmptyFormation($team, 'Draft');
        $bench = [];
        for ($i = 1; $i <= 6; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $preview = $this->createService($team, $bench, null)->preview($team, $assigned);

        $this->assertSame('assigned', $preview['source']);
        $this->assertTrue($preview['will_forfeit']);
        $this->assertFalse($preview['will_autofill']);
    }

    public function testPreviewForfeitsWhenRosterCannotCompleteTheFormation(): void
    {
        $team = $this->createTeam(1);
        $bench = [];
        for ($i = 1; $i <= 5; ++$i) {
            $bench[] = $this->createHero($team, $i);
        }

        $preview = $this->createService($team, $bench, null)->preview($team, null);

        $this->assertTrue($preview['will_forfeit']);
        $this->assertFalse($preview['will_autofill']);
        $this->assertNull($preview['formation_name']);
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

    public function testNpcFillSkipsDuplicateAndUnreadyHeroesOnTheDefault(): void
    {
        $team = $this->createTeam(1);
        $team->setIsNpc(true);
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

        $service = $this->createService($team, $bench, $assigned, $em);
        $resolved = $service->resolveForKickoff($team, null);

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
