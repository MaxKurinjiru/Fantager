<?php

declare(strict_types=1);

namespace App\Tests\Service\Team;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Enum\Race;
use App\Enum\TrainingType;
use App\Repository\Hero\HeroRepository;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Item\ItemService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Summoning\SummoningService;
use App\Service\Team\NpcSimulationService;
use App\Service\Training\TrainingService;
use App\Service\Hero\HeroDismissalService;
use App\Entity\Item\Item;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class NpcSimulationServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private $em;
    /** @var SummoningService&MockObject */
    private $summoningService;
    /** @var MarketplaceService&MockObject */
    private $marketplaceService;
    /** @var HeadquartersService&MockObject */
    private $hqService;
    /** @var TrainingService&MockObject */
    private $trainingService;
    /** @var ItemService&MockObject */
    private $itemService;
    /** @var HeroDismissalService&MockObject */
    private $dismissalService;
    private NpcSimulationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->summoningService = $this->createMock(SummoningService::class);
        $this->marketplaceService = $this->createMock(MarketplaceService::class);
        $this->hqService = $this->createMock(HeadquartersService::class);
        $this->trainingService = $this->createMock(TrainingService::class);
        $this->itemService = $this->createMock(ItemService::class);
        $this->dismissalService = $this->createMock(HeroDismissalService::class);

        $this->service = new NpcSimulationService(
            $this->em,
            $this->summoningService,
            $this->marketplaceService,
            $this->hqService,
            $this->trainingService,
            $this->itemService,
            $this->dismissalService
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('id');
        $prop->setValue($entity, $id);
    }

    public function testGetEconomicRole(): void
    {
        $team = new Team();
        $this->setEntityId($team, 0); // ROLE_MERCENARY_ACADEMY
        $this->assertEquals(NpcSimulationService::ROLE_MERCENARY_ACADEMY, $this->service->getEconomicRole($team));

        $this->setEntityId($team, 1); // ROLE_VETERAN_GUILD
        $this->assertEquals(NpcSimulationService::ROLE_VETERAN_GUILD, $this->service->getEconomicRole($team));

        $this->setEntityId($team, 2); // ROLE_ROYAL_COLLECTOR
        $this->assertEquals(NpcSimulationService::ROLE_ROYAL_COLLECTOR, $this->service->getEconomicRole($team));

        $this->setEntityId($team, 3); // ROLE_SCAVENGER_CLAN
        $this->assertEquals(NpcSimulationService::ROLE_SCAVENGER_CLAN, $this->service->getEconomicRole($team));
    }

    public function testSimulateTactics(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // Mercenary Academy
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);

        // Create a default formation
        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setIsDefault(true);
        $this->setEntityId($formation, 100);

        foreach (FormationPosition::cases() as $pos) {
            $slot = new FormationSlot();
            $slot->setFormation($formation);
            $slot->setPosition($pos);
            $formation->addSlot($slot);
        }

        // Create 10 heroes
        $heroes = [];
        for ($i = 1; $i <= 10; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($team);
            $hero->setRole(HeroRole::Combatant);
            $hero->setStatus(HeroStatus::Available);
            
            // Set stats
            $hero->setStr(10);
            $hero->setKon(10);
            $hero->setDex(10);
            $hero->setSpd(10);
            $hero->setIntel(10);
            
            $heroes[] = $hero;
        }

        // Configure one trainer (should be skipped in tactics)
        $heroes[9]->setRole(HeroRole::Trainer);

        // Make some heroes explicitly tankier or more magical
        // Frontline candidates
        $heroes[0]->setStr(20); $heroes[0]->setKon(20);
        $heroes[1]->setStr(18); $heroes[1]->setKon(18);
        $heroes[2]->setStr(15); $heroes[2]->setKon(15);
        
        // Backline candidates
        $heroes[3]->setIntel(20); $heroes[3]->setSpd(20);
        $heroes[4]->setIntel(18); $heroes[4]->setSpd(18);
        $heroes[5]->setIntel(15); $heroes[5]->setSpd(15);

        // Repositories mocking
        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        $formationRepo = $this->createMock(EntityRepository::class);
        $formationRepo->method('findOneBy')->willReturn($formation);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findBy')->willReturn($heroes);

        $itemRepo = $this->createMock(EntityRepository::class);
        $itemRepo->method('findBy')->willReturn([]); // no items to auto-equip

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $formationRepo, $heroRepo, $itemRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Formation::class === $class) return $formationRepo;
            if (Hero::class === $class) return $heroRepo;
            if (Item::class === $class) return $itemRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Run
        $this->service->simulateTactics($kingdom, new \DateTimeImmutable());

        // Verification
        // Formation approach should be Aggressive (Mercenary Academy)
        $this->assertEquals(FormationApproach::Aggressive, $formation->getApproach());

        // Verify slot assignments
        $assignedHeroIds = [];
        foreach ($formation->getSlots() as $slot) {
            $h = $slot->getHero();
            $this->assertNotNull($h);
            $this->assertNotEquals(10, $h->getId()); // Trainer should not be slotted
            $assignedHeroIds[] = $h->getId();
        }

        $this->assertCount(6, array_unique($assignedHeroIds));
    }

    public function testSimulateTraining(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 2); // Royal Collector (magic training focus)
        $team->setKingdom($kingdom);

        // Create 10 heroes
        $heroes = [];
        for ($i = 1; $i <= 10; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($team);
            $hero->setAgeRaw($i * 5); // 5, 10, 15... 50
            $hero->setLevel(1);
            $heroes[] = $hero;
        }

        // Mock repositories
        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findBy')->willReturn($heroes);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $heroRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Hero::class === $class) return $heroRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Mock training limits
        $this->trainingService->method('getTrainerLimit')->willReturn(2);
        $this->trainingService->method('getTrainerSlotsLimit')->willReturn(4);

        // Run
        $this->service->simulateTraining($kingdom, new \DateTimeImmutable());

        // Verification
        // The 2 oldest heroes (IDs 9 and 10) should be trainers
        $this->assertEquals(HeroRole::Trainer, $heroes[9]->getRole());
        $this->assertEquals(HeroRole::Trainer, $heroes[8]->getRole()); // Note: sorted desc, oldest are $heroes[9] and $heroes[8] (ages 50 and 45)

        // Trainer configs for Royal Collector: int and magic
        $this->assertEquals(TrainingType::Attribute, $heroes[9]->getTrainingType());
        $this->assertEquals('int', $heroes[9]->getTargetAttribute());
        $this->assertEquals(TrainingType::Magic, $heroes[8]->getTrainingType());

        // Trainees should be assigned
        $assignedTrainees = 0;
        foreach ($heroes as $h) {
            if ($h->getTrainer() !== null) {
                $assignedTrainees++;
            }
        }
        $this->assertEquals(8, $assignedTrainees);
    }
}
