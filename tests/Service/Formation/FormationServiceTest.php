<?php

declare(strict_types=1);

namespace App\Tests\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Repository\Formation\FormationRepository;
use App\Repository\Formation\FormationSlotRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Exception\UserFacingException;
use App\Service\Formation\FormationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FormationServiceTest extends TestCase
{
    public function testAssertCanCreateSavedFormationAllowsUpToLimit(): void
    {
        $team = new Team();
        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(FormationService::MAX_SAVED_FORMATIONS);

        $service = new FormationService(
            $repository,
            $this->createMock(FormationSlotRepository::class),
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.formation_max_saved');

        $service->assertCanCreateSavedFormation($team);
    }

    public function testSaveRejectsNewFormationWhenLimitReached(): void
    {
        $team = new Team();
        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(FormationService::MAX_SAVED_FORMATIONS);

        $service = new FormationService(
            $repository,
            $this->createMock(FormationSlotRepository::class),
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\DomainException::class);

        $service->save($team, null, 'New Formation', \App\Enum\FormationApproach::Balanced, [], false);
    }

    public function testPromoteTemporaryRejectsWhenLimitReached(): void
    {
        $team = new Team();
        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setName('Temp');
        $formation->setApproach(\App\Enum\FormationApproach::Balanced);
        $formation->setIsTemporary(true);

        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(FormationService::MAX_SAVED_FORMATIONS);

        $service = new FormationService(
            $repository,
            $this->createMock(FormationSlotRepository::class),
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\DomainException::class);

        $service->promoteTemporary($formation, $team, 'Saved', false);
    }

    public function testSaveRejectsDuplicateHeroIdBeforeRebuildingSlots(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $this->setId($hero, 5);

        $heroRepository = $this->createMock(HeroRepository::class);
        $heroRepository->method('findOneBy')->willReturn($hero);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(0);

        $service = new FormationService(
            $repository,
            $this->createMock(FormationSlotRepository::class),
            $heroRepository,
            $this->createMock(LeagueFixtureRepository::class),
            $em,
        );

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.formation_duplicate_hero');

        $service->save($team, null, 'Lineup', FormationApproach::Balanced, [
            ['position' => 'front_1', 'hero_id' => 5, 'strategy' => [], 'spell_priorities' => []],
            ['position' => 'front_2', 'hero_id' => 5, 'strategy' => [], 'spell_priorities' => []],
        ], false);
    }

    public function testSaveAllowsEmptySlotsWithUniqueHeroes(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $this->setId($hero, 7);

        $heroRepository = $this->createMock(HeroRepository::class);
        $heroRepository->method('findOneBy')->willReturn($hero);

        $slotRepository = $this->createMock(FormationSlotRepository::class);
        $slotRepository->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->atLeastOnce())->method('flush');

        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(0);

        $service = new FormationService(
            $repository,
            $slotRepository,
            $heroRepository,
            $this->createMock(LeagueFixtureRepository::class),
            $em,
        );

        $formation = $service->save($team, null, 'Draft', FormationApproach::Balanced, [
            ['position' => 'front_1', 'hero_id' => 7, 'strategy' => [], 'spell_priorities' => []],
            ['position' => 'front_2', 'hero_id' => null, 'strategy' => [], 'spell_priorities' => []],
        ], false);

        $this->assertSame('Draft', $formation->getName());
    }

    public function testSaveAllowsFullyEmptySlots(): void
    {
        $team = new Team();
        $slotRepository = $this->createMock(FormationSlotRepository::class);
        $slotRepository->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->atLeastOnce())->method('flush');

        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(0);

        $service = new FormationService(
            $repository,
            $slotRepository,
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $em,
        );

        $formation = $service->save($team, null, 'Empty draft', FormationApproach::Balanced, [
            ['position' => 'front_1', 'hero_id' => null, 'strategy' => [], 'spell_priorities' => []],
            ['position' => 'front_2', 'hero_id' => null, 'strategy' => [], 'spell_priorities' => []],
        ], false);

        $this->assertSame('Empty draft', $formation->getName());
        $this->assertSame('seek_cover_ranged', $formation->getTacticsDefaults()['movement_back']);
    }

    public function testSaveAppliesInheritedRowMovementToEmptyStrategy(): void
    {
        $team = new Team();
        $slotRepository = $this->createMock(FormationSlotRepository::class);
        $slotRepository->method('findBy')->willReturn([]);

        $persistedSlots = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persistedSlots): void {
            if ($entity instanceof FormationSlot) {
                $persistedSlots[] = $entity;
            }
        });
        $em->expects($this->atLeastOnce())->method('flush');

        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(0);

        $service = new FormationService(
            $repository,
            $slotRepository,
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $em,
        );

        $service->save($team, null, 'Rows', FormationApproach::Balanced, [
            ['position' => 'back_2', 'hero_id' => null, 'strategy' => [], 'spell_priorities' => []],
        ], false);

        $this->assertCount(1, $persistedSlots);
        $this->assertSame('seek_cover_ranged', $persistedSlots[0]->getStrategy()['movement_goal']);
        $this->assertTrue($persistedSlots[0]->getStrategy()['inherit_movement']);
    }

    public function testSaveSanitizesStrategyAndSpellPriorities(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $this->setId($hero, 7);

        $heroRepository = $this->createMock(HeroRepository::class);
        $heroRepository->method('findOneBy')->willReturn($hero);

        $slotRepository = $this->createMock(FormationSlotRepository::class);
        $slotRepository->method('findBy')->willReturn([]);

        $persistedSlots = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persistedSlots): void {
            if ($entity instanceof FormationSlot) {
                $persistedSlots[] = $entity;
            }
        });
        $em->expects($this->atLeastOnce())->method('flush');

        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(0);

        $service = new FormationService(
            $repository,
            $slotRepository,
            $heroRepository,
            $this->createMock(LeagueFixtureRepository::class),
            $em,
        );

        $service->save($team, null, 'Tactics', FormationApproach::Balanced, [
            [
                'position' => 'front_1',
                'hero_id' => 7,
                'strategy' => [
                    'movement_goal' => 'flank_rear',
                    'target' => 'weakest',
                    'target_order' => ['back_2', 'front_1'],
                    'fallback' => 'lowest_hp',
                ],
                'spell_priorities' => [
                    ['spell_id' => 12, 'when' => 'ally_hp_below', 'threshold' => 40, 'target' => 'lowest_hp_ally'],
                    ['spell_id' => 8, 'when' => 'enemy_status', 'target' => 'priority'],
                ],
            ],
        ], false);

        $this->assertCount(1, $persistedSlots);
        $this->assertSame([
            'inherit_movement' => false,
            'inherit_targeting' => false,
            'movement_goal' => 'flank_rear',
            'target_order' => ['back_2', 'front_1'],
            'fallback' => 'lowest_hp',
        ], $persistedSlots[0]->getStrategy());
        $this->assertSame([
            [
                'spell_id' => 12,
                'when' => 'ally_hp_below',
                'target' => 'lowest_hp_ally',
                'threshold' => 40,
            ],
        ], $persistedSlots[0]->getSpellPriorities());
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
