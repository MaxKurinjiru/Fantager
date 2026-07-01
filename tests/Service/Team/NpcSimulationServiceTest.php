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
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Training\TrainingService;
use App\Service\Hero\HeroDismissalService;
use App\Service\Config\RaceConfig;
use App\Service\Hero\HeroRatingCalculator;
use App\Entity\Item\Item;
use App\Entity\Marketplace\MarketplaceListing;
use App\Enum\ItemSubType;
use App\Enum\ListingType;
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
    /** @var HeroRatingCalculator&MockObject */
    private $heroRatingCalculator;
    /** @var RaceConfig&MockObject */
    private $raceConfig;
    /** @var TeamChronicleService&MockObject */
    private $teamChronicleService;
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
        $this->heroRatingCalculator = $this->createMock(HeroRatingCalculator::class);
        $this->raceConfig = $this->createMock(RaceConfig::class);
        $this->teamChronicleService = $this->createMock(TeamChronicleService::class);

        $this->service = new NpcSimulationService(
            $this->em,
            $this->summoningService,
            $this->marketplaceService,
            $this->hqService,
            $this->trainingService,
            $this->itemService,
            $this->dismissalService,
            $this->heroRatingCalculator,
            $this->raceConfig,
            $this->teamChronicleService,
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('id');
        $prop->setValue($entity, $id);
    }

    /**
     * @param array<Hero> $heroes
     */
    private function createHeroRepositoryMock(array $heroes = []): HeroRepository
    {
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn($heroes);

        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('createQueryBuilder')->willReturn($qbMock);

        return $heroRepo;
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

    public function testAutoEquipItemsWithMasteryAndPurchase(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0);
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(1000);

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

        // Create 6 heroes
        $heroes = [];
        for ($i = 1; $i <= 6; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($team);
            $hero->setRole(HeroRole::Combatant);
            $hero->setStatus(HeroStatus::Available);
            
            // Default stats: Strength/Melee
            $hero->setStr(10);
            $hero->setKon(10);
            $hero->setDex(10);
            $hero->setSpd(10);
            $hero->setIntel(10);
            
            $heroes[] = $hero;
        }

        // Give Hero 1 a weapon mastery in TwoHandedSword and armor mastery in MediumArmor
        $hero1 = $heroes[0];
        $wmWeapon = new \App\Entity\Hero\WeaponMastery();
        $wmWeapon->setHero($hero1);
        $wmWeapon->setStyle(\App\Enum\ItemSubType::TwoHandedSword);
        $wmWeapon->setMasteryTier(3);
        $wmWeapon->setXp(300);
        $hero1->getWeaponMasteries()->add($wmWeapon);

        $wmArmor = new \App\Entity\Hero\WeaponMastery();
        $wmArmor->setHero($hero1);
        $wmArmor->setStyle(\App\Enum\ItemSubType::MediumArmor);
        $wmArmor->setMasteryTier(2);
        $wmArmor->setXp(100);
        $hero1->getWeaponMasteries()->add($wmArmor);

        // Repositories mocking
        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        $formationRepo = $this->createMock(EntityRepository::class);
        $formationRepo->method('findOneBy')->willReturn($formation);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findBy')->willReturn($heroes);

        /** @var array<string, Item> $equippedItems */
        $equippedItems = [];
        $itemRepo = $this->createMock(EntityRepository::class);
        $itemRepo->method('findBy')->willReturn([]); // empty inventory
        $itemRepo->method('findOneBy')->willReturnCallback(function (array $criteria) use (&$equippedItems) {
            /** @var array<string, mixed> $criteria */
            $hero = $criteria['equippedHero'] ?? null;
            $slot = $criteria['equippedSlot'] ?? null;
            if ($hero && $slot) {
                return $equippedItems[$hero->getId() . '_' . $slot->value] ?? null;
            }
            return null;
        });

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $formationRepo, $heroRepo, $itemRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Formation::class === $class) return $formationRepo;
            if (Hero::class === $class) return $heroRepo;
            if (Item::class === $class) return $itemRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Mock ItemService purchaseBasicItem
        $purchasedKeys = [];
        $this->itemService->method('purchaseBasicItem')
            ->willReturnCallback(function (Team $t, string $key) use (&$purchasedKeys) {
                $purchasedKeys[] = $key;
                $item = new Item();
                $item->setOwnerTeam($t);
                $item->setName($key);
                $template = ItemService::BASIC_EQUIPMENT[$key];
                $item->setSlotType($template['slot']);
                $item->setCategory($template['category']);
                if (isset($template['sub_type'])) {
                    $item->setSubType(\App\Enum\ItemSubType::tryFrom($template['sub_type']));
                }
                $item->setRarity(ItemRarity::Common);
                return $item;
            });

        // Mock ItemService equip
        $this->itemService->method('equip')->willReturnCallback(function (Item $item, Hero $h, ItemSlotType $slot) use (&$equippedItems) {
            $item->setEquippedHero($h);
            $item->setEquippedSlot($slot);
            $equippedItems[$h->getId() . '_' . $slot->value] = $item;
        });

        // Run
        $this->service->simulateTactics($kingdom, new \DateTimeImmutable());

        // Assertions for Hero 1:
        // MainHand weapon should be TwoHandedSword (greatsword)
        $this->assertContains('greatsword', $purchasedKeys);
        // Medium armor items should be purchased (chain_coif, chain_hauberk, chain_gloves, chain_boots)
        $this->assertContains('chain_coif', $purchasedKeys);
        $this->assertContains('chain_hauberk', $purchasedKeys);
        $this->assertContains('chain_gloves', $purchasedKeys);
        $this->assertContains('chain_boots', $purchasedKeys);

        // OffHand should be empty because TwoHandedSword is two-handed
        $hero1OffHand = $equippedItems[$hero1->getId() . '_' . ItemSlotType::OffHand->value] ?? null;
        $this->assertNull($hero1OffHand);
    }

    public function testProactiveDismissNegativeTrait(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // ROLE_MERCENARY_ACADEMY
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(1000);

        // Create 8 heroes. One has a purely negative trait (Fragile), one has a mixed trait (Berserker), rest have null.
        $heroes = [];
        for ($i = 1; $i <= 8; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($team);
            $hero->setRole(HeroRole::Combatant);
            $hero->setStatus(HeroStatus::Available);
            $hero->setLevel(1);
            $heroes[] = $hero;
        }

        // Set traits
        $heroes[0]->setTrait(\App\Enum\HeroTrait::Fragile); // Purely negative
        $heroes[1]->setTrait(\App\Enum\HeroTrait::Berserker); // Mixed
        $heroes[2]->setTrait(null);

        // Setup mock repos
        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        // Mock createQueryBuilder to return our list of heroes
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn($heroes);

        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('createQueryBuilder')->willReturn($qbMock);
        // Also mock standard findBy / findOneBy just in case
        $heroRepo->method('findBy')->willReturn($heroes);

        $formationRepo = $this->createMock(EntityRepository::class);
        // Empty formations
        $formationRepo->method('findBy')->willReturn([]);

        $itemRepo = $this->createMock(EntityRepository::class);
        $itemRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $formationRepo, $heroRepo, $itemRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Formation::class === $class) return $formationRepo;
            if (Hero::class === $class) return $heroRepo;
            if (Item::class === $class) return $itemRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Mock HQ service
        $hq = new Headquarters();
        $this->hqService->method('getForTeam')->willReturn($hq);
        $this->hqService->method('getRosterLimit')->willReturn(15);
        $this->hqService->method('calculateWeeklyMaintenanceFee')->willReturn(50);
        $this->hqService->method('calculateUpgradeCost')->willReturn(500);

        $this->summoningService->method('getStatus')->willReturn([
            'available' => false,
            'reason' => 'test',
            'gold_cost' => 100,
            'summons_used' => 0,
            'summons_max' => 5,
        ]);

        // Expect dismiss to be called on the Fragile hero (heroes[0])
        $calledDismissParams = null;
        $this->dismissalService->expects($this->once())
            ->method('dismiss')
            ->willReturnCallback(function (Team $t, Hero $h) use (&$calledDismissParams) {
                $calledDismissParams = [$t, $h];
                return 0;
            });

        // Run
        $this->service->simulateDailyManagementAndEconomy($kingdom, new \DateTimeImmutable());

        $this->assertNotNull($calledDismissParams);
        $this->assertSame($team, $calledDismissParams[0]);
        $this->assertSame(\App\Enum\HeroTrait::Fragile, $calledDismissParams[1]->getTrait());
    }

    public function testCalculateHeroMarketPriceWithTraits(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // ROLE_MERCENARY_ACADEMY
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(1000);

        // We need 10 heroes to trigger the selling candidate flow in simulateManagementAndEconomy
        $heroes = [];
        for ($i = 1; $i <= 10; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($team);
            $hero->setRole(HeroRole::Combatant);
            $hero->setStatus(HeroStatus::Available);
            $hero->setLevel(2); // level 2 baseline = 500 gold
            $heroes[] = $hero;
        }

        // Test rating-based market price from HeroRatingCalculator
        $heroes[0]->setTrait(\App\Enum\HeroTrait::Clutch);

        $this->heroRatingCalculator->method('estimateMarketPrice')
            ->willReturnCallback(fn (Hero $hero) => 650);

        // Setup mock repos
        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn($heroes);

        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('createQueryBuilder')->willReturn($qbMock);
        $heroRepo->method('findBy')->willReturn($heroes);

        $formationRepo = $this->createMock(EntityRepository::class);
        $formationRepo->method('findBy')->willReturn([]);

        $itemRepo = $this->createMock(EntityRepository::class);
        $itemRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $formationRepo, $heroRepo, $itemRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Formation::class === $class) return $formationRepo;
            if (Hero::class === $class) return $heroRepo;
            if (Item::class === $class) return $itemRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Mock HQ service
        $hq = new Headquarters();
        $this->hqService->method('getForTeam')->willReturn($hq);
        $this->hqService->method('getRosterLimit')->willReturn(15);
        $this->hqService->method('calculateWeeklyMaintenanceFee')->willReturn(50);

        // We expect createListing to use the rating calculator market price
        $calledListingParams = null;
        $this->marketplaceService->expects($this->any())
            ->method('createListing')
            ->willReturnCallback(function ($t, $type, $id, $price, $price2, $mode, $duration, $extra) use (&$calledListingParams) {
                if (null === $calledListingParams) {
                    $calledListingParams = [$t, $type, $id, $price, $price2, $mode, $duration];
                }
                return new \App\Entity\Marketplace\MarketplaceListing();
            });

        // Run
        $this->service->simulateMarketplaceActions($kingdom, new \DateTimeImmutable());

        $this->assertNotNull($calledListingParams);
        $this->assertSame($team, $calledListingParams[0]);
        $this->assertSame('hero', $calledListingParams[1]);
        $this->assertSame($heroes[0]->getId(), $calledListingParams[2]);
        $this->assertSame(650, $calledListingParams[3]);
        $this->assertSame(650, $calledListingParams[4]);
        $this->assertSame('buy_now', $calledListingParams[5]);
        $this->assertSame(7, $calledListingParams[6]);
    }

    public function testSimulateTrainingExcludesNegativeTraitsFromPromotion(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 2); // Royal Collector
        $team->setKingdom($kingdom);

        // Create 3 heroes. Candidate 1 is oldest but has a negative trait (Slacker).
        // Candidate 2 is younger but has null trait.
        // Candidate 3 is youngest and has null trait.
        $heroes = [];
        
        $hero1 = new Hero();
        $this->setEntityId($hero1, 1);
        $hero1->setTeam($team);
        $hero1->setAgeRaw(50);
        $hero1->setLevel(3);
        $hero1->setTrait(\App\Enum\HeroTrait::Slacker); // purely negative
        $heroes[] = $hero1;

        $hero2 = new Hero();
        $this->setEntityId($hero2, 2);
        $hero2->setTeam($team);
        $hero2->setAgeRaw(40);
        $hero2->setLevel(2);
        $hero2->setTrait(null);
        $heroes[] = $hero2;

        $hero3 = new Hero();
        $this->setEntityId($hero3, 3);
        $hero3->setTeam($team);
        $hero3->setAgeRaw(30);
        $hero3->setLevel(1);
        $hero3->setTrait(null);
        $heroes[] = $hero3;

        // Mock repos
        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findBy')->willReturn($heroes);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $heroRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Hero::class === $class) return $heroRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Limit trainers to 1
        $this->trainingService->method('getTrainerLimit')->willReturn(1);
        $this->trainingService->method('getTrainerSlotsLimit')->willReturn(2);

        // Run
        $this->service->simulateTraining($kingdom, new \DateTimeImmutable());

        // Hero 2 should be promoted instead of Hero 1, because Hero 1 has a purely negative trait.
        $this->assertEquals(HeroRole::Trainer, $hero2->getRole());
        $this->assertEquals(HeroRole::Combatant, $hero1->getRole());
    }

    public function testSimulateTrainingUnequipsItemsFromPromotedTrainer(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // ROLE_MERCENARY_ACADEMY
        $team->setKingdom($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setAgeRaw(300);
        $hero->setLevel(10);
        $hero->setStatus(HeroStatus::Available);
        $this->setEntityId($hero, 123);

        $hero2 = new Hero();
        $hero2->setRole(HeroRole::Combatant);
        $hero2->setTeam($team);
        $hero2->setAgeRaw(100);
        $hero2->setLevel(1);
        $hero2->setStatus(HeroStatus::Available);
        $this->setEntityId($hero2, 124);

        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setEquippedHero($hero);
        $item->setEquippedSlot(ItemSlotType::MainHand);

        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findBy')->willReturn([$hero, $hero2]);

        $itemRepo = $this->createMock(EntityRepository::class);
        $itemRepo->expects($this->once())->method('findBy')
            ->with(['equippedHero' => $hero])
            ->willReturn([$item]);

        $formationRepo = $this->createMock(EntityRepository::class);
        $formationRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $heroRepo, $itemRepo, $formationRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Hero::class === $class) return $heroRepo;
            if (Item::class === $class) return $itemRepo;
            if (Formation::class === $class) return $formationRepo;
            return $this->createMock(EntityRepository::class);
        });

        $this->trainingService->method('getTrainerLimit')->willReturn(1);
        $this->trainingService->method('getTrainerSlotsLimit')->willReturn(2);

        $this->service->simulateTraining($kingdom, new \DateTimeImmutable());

        $this->assertEquals(HeroRole::Trainer, $hero->getRole());
        $this->assertNull($item->getEquippedHero());
        $this->assertNull($item->getEquippedSlot());
    }

    public function testSimulateWeeklyManagementAndEconomyHqUpgrades(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // ROLE_MERCENARY_ACADEMY: Training (0), SummoningChamber (1), Barracks (2)
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(10000);

        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);
        $heroRepo = $this->createHeroRepositoryMock();

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $heroRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Hero::class === $class) return $heroRepo;
            return $this->createMock(EntityRepository::class);
        });

        $hq = new Headquarters();
        $hq->setTeam($team);

        $training = new Facility();
        $training->setType(FacilityType::Training);
        $training->setLevel(3);
        $hq->addFacility($training);

        $summoning = new Facility();
        $summoning->setType(FacilityType::SummoningChamber);
        $summoning->setLevel(1);
        $hq->addFacility($summoning);

        $barracks = new Facility();
        $barracks->setType(FacilityType::Barracks);
        $barracks->setLevel(1);
        $hq->addFacility($barracks);

        $treasury = new Facility();
        $treasury->setType(FacilityType::Treasury);
        $treasury->setLevel(1);
        $hq->addFacility($treasury);

        $this->hqService->method('getForTeam')->willReturn($hq);
        $this->hqService->method('calculateWeeklyMaintenanceFee')->willReturn(50);
        $this->hqService->method('calculateUpgradeCost')->willReturn(100);

        $this->hqService->expects($this->once())
            ->method('upgradeFacility')
            ->with($team, FacilityType::Training);

        $this->service->simulateWeeklyManagementAndEconomy($kingdom, new \DateTimeImmutable(), $team);
    }

    public function testSimulateWeeklyManagementAndEconomyHqUpgradesExceedingLimitFallsBack(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // ROLE_MERCENARY_ACADEMY: Training (0), SummoningChamber (1), Barracks (2)
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(10000);

        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);
        $heroRepo = $this->createHeroRepositoryMock();

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $heroRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Hero::class === $class) return $heroRepo;
            return $this->createMock(EntityRepository::class);
        });

        $hq = new Headquarters();
        $hq->setTeam($team);

        $training = new Facility();
        $training->setType(FacilityType::Training);
        $training->setLevel(4);
        $hq->addFacility($training);

        $summoning = new Facility();
        $summoning->setType(FacilityType::SummoningChamber);
        $summoning->setLevel(1);
        $hq->addFacility($summoning);

        $barracks = new Facility();
        $barracks->setType(FacilityType::Barracks);
        $barracks->setLevel(1);
        $hq->addFacility($barracks);

        $treasury = new Facility();
        $treasury->setType(FacilityType::Treasury);
        $treasury->setLevel(1);
        $hq->addFacility($treasury);

        $this->hqService->method('getForTeam')->willReturn($hq);
        $this->hqService->method('calculateWeeklyMaintenanceFee')->willReturn(50);
        $this->hqService->method('calculateUpgradeCost')->willReturn(100);

        $this->hqService->expects($this->once())
            ->method('upgradeFacility')
            ->with($team, FacilityType::SummoningChamber);

        $this->service->simulateWeeklyManagementAndEconomy($kingdom, new \DateTimeImmutable(), $team);
    }

    public function testSimulateMarketplaceActionsListsItemsWithReserveAndBasicDiscount(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // ROLE_MERCENARY_ACADEMY
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(1000);

        // Create 4 MainHand items
        $items = [];
        $item1 = new Item();
        $this->setEntityId($item1, 1);
        $item1->setOwnerTeam($team);
        $item1->setRarity(ItemRarity::Common);
        $item1->setSlotType(ItemSlotType::MainHand);
        $item1->setStatus(ItemStatus::Available);
        $items[] = $item1;

        $item2 = new Item();
        $this->setEntityId($item2, 2);
        $item2->setOwnerTeam($team);
        $item2->setRarity(ItemRarity::Common);
        $item2->setSlotType(ItemSlotType::MainHand);
        $item2->setStatus(ItemStatus::Available);
        $items[] = $item2;

        $item3 = new Item();
        $this->setEntityId($item3, 3);
        $item3->setOwnerTeam($team);
        $item3->setRarity(ItemRarity::Common);
        $item3->setSlotType(ItemSlotType::MainHand);
        $item3->setStatus(ItemStatus::Available);
        $items[] = $item3;

        $item4 = new Item();
        $this->setEntityId($item4, 4);
        $item4->setOwnerTeam($team);
        $item4->setRarity(ItemRarity::Common);
        $item4->setSlotType(ItemSlotType::MainHand);
        $item4->setStatus(ItemStatus::Available);
        $items[] = $item4;

        // Create 2 Body items
        $item5 = new Item();
        $this->setEntityId($item5, 5);
        $item5->setOwnerTeam($team);
        $item5->setRarity(ItemRarity::Common);
        $item5->setSlotType(ItemSlotType::Body);
        $item5->setStatus(ItemStatus::Available);
        $items[] = $item5;

        $item6 = new Item();
        $this->setEntityId($item6, 6);
        $item6->setOwnerTeam($team);
        $item6->setRarity(ItemRarity::Common);
        $item6->setSlotType(ItemSlotType::Body);
        $item6->setStatus(ItemStatus::Available);
        $items[] = $item6;

        // Mock ItemService::getBasicItemMerchantPrice
        // Item 1 and 2 are basic (price 50), others are not (null)
        $this->itemService->method('getBasicItemMerchantPrice')
            ->willReturnCallback(function (Item $item) {
                if (in_array($item->getId(), [1, 2], true)) {
                    return 50;
                }
                return null;
            });

        // Setup mock repos
        $teamRepo = $this->createMock(EntityRepository::class);
        $teamRepo->method('findBy')->willReturn([$team]);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('findBy')->willReturn([]); // no heroes listed
        // Return empty query result for simulateMarketplaceActions heroes query
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn([]);
        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);
        $heroRepo->method('createQueryBuilder')->willReturn($qbMock);

        $formationRepo = $this->createMock(EntityRepository::class);
        $formationRepo->method('findBy')->willReturn([]);

        $itemRepo = $this->createMock(EntityRepository::class);
        $itemRepo->method('findBy')->willReturn($items);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($teamRepo, $formationRepo, $heroRepo, $itemRepo) {
            if (Team::class === $class) return $teamRepo;
            if (Formation::class === $class) return $formationRepo;
            if (Hero::class === $class) return $heroRepo;
            if (Item::class === $class) return $itemRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Mock HQ service
        $hq = new Headquarters();
        $this->hqService->method('getForTeam')->willReturn($hq);
        $this->hqService->method('calculateWeeklyMaintenanceFee')->willReturn(50);

        // Capture listings created
        $createdListings = [];
        $this->marketplaceService->method('createListing')
            ->willReturnCallback(function ($t, $type, $id, $price, $price2, $mode, $duration, $extra) use (&$createdListings) {
                $createdListings[] = [
                    'type' => $type,
                    'id' => $id,
                    'price' => $price,
                ];
                return new \App\Entity\Marketplace\MarketplaceListing();
            });

        // Run
        $this->service->simulateMarketplaceActions($kingdom, new \DateTimeImmutable());

        // Assertions:
        // 1. Number of listings is between 0 and 3
        $this->assertLessThanOrEqual(3, count($createdListings));

        foreach ($createdListings as $listing) {
            $this->assertEquals('item', $listing['type']);
            // Item 4 and Item 6 should never be listed because they are part of the slot reserves
            $this->assertNotEquals(4, $listing['id']);
            $this->assertNotEquals(6, $listing['id']);

            // Verify prices
            if (in_array($listing['id'], [1, 2], true)) {
                // Basic items discounted: round(50 * 0.7) = 35
                $this->assertEquals(35, $listing['price']);
            } else {
                // Non-basic common items: 75
                $this->assertEquals(75, $listing['price']);
            }
        }
    }

    public function testSimulateArenaOptimization(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 0); // mercenary_academy
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $hq->setRaceOptimization(Race::Elf->value); // initial opt
        $hq->setRaceOptimizationLockCycle(false);

        $this->hqService->method('getForTeam')->willReturn($hq);

        $this->teamChronicleService->expects($this->once())
            ->method('recordRaceOptimizationChanged')
            ->with($team, Race::Dwarf->value);

        // Mock hero list query builder in determineOptimalRace
        $heroes = [];
        // Add 2 Dwarf combatants, 1 Orc, 1 Elf
        $h1 = new Hero();
        $h1->setRace(Race::Dwarf);
        $h1->setStatus(HeroStatus::Available);
        $heroes[] = $h1;

        $h2 = new Hero();
        $h2->setRace(Race::Dwarf);
        $h2->setStatus(HeroStatus::Available);
        $heroes[] = $h2;

        $h3 = new Hero();
        $h3->setRace(Race::Orc);
        $h3->setStatus(HeroStatus::Available);
        $heroes[] = $h3;

        $h4 = new Hero();
        $h4->setRace(Race::Elf);
        $h4->setStatus(HeroStatus::Available);
        $heroes[] = $h4;

        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn($heroes);
        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $heroRepo = $this->createMock(HeroRepository::class);
        $heroRepo->method('createQueryBuilder')->willReturn($qbMock);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($heroRepo) {
            if (Hero::class === $class) return $heroRepo;
            return $this->createMock(EntityRepository::class);
        });

        // Run weekly simulation which triggers arena optimization
        $this->service->simulateWeeklyManagementAndEconomy($kingdom, new \DateTimeImmutable(), $team);

        // Since role is mercenary_academy and we have 2 Dwarfs, 1 Orc, 1 Elf, optimal should be Dwarf
        $this->assertEquals(Race::Dwarf->value, $hq->getRaceOptimization());
        $this->assertTrue($hq->isRaceOptimizationLockCycle());
    }

    public function testSimulateMarketplaceSeparateHeroTrainerSelling(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 1); // veteran_guild: prefers trainers (sell limit 1-3 trainers, 1 hero)
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(1000);

        // Create 10 heroes to trigger selling ($heroCount >= 10)
        $heroes = [];
        for ($i = 1; $i <= 10; $i++) {
            $h = new Hero();
            $this->setEntityId($h, $i);
            $h->setStatus(HeroStatus::Available);
            $h->setLevel(1);
            if ($i <= 5) {
                $h->setRole(HeroRole::Trainer);
            } else {
                $h->setRole(HeroRole::Combatant);
            }
            $heroes[] = $h;
        }

        $heroRepo = $this->createMock(HeroRepository::class);
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn($heroes);
        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);
        $heroRepo->method('createQueryBuilder')->willReturn($qbMock);

        $formationRepo = $this->createMock(EntityRepository::class);
        $formationRepo->method('findBy')->willReturn([]);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($formationRepo, $heroRepo) {
            if (Formation::class === $class) return $formationRepo;
            if (Hero::class === $class) return $heroRepo;
            return $this->createMock(EntityRepository::class);
        });

        $this->heroRatingCalculator->method('estimateMarketPrice')->willReturn(100);

        $hq = new Headquarters();
        $this->hqService->method('getForTeam')->willReturn($hq);
        $this->hqService->method('calculateWeeklyMaintenanceFee')->willReturn(50);

        // Track listings created
        $createdTypes = [];
        $this->marketplaceService->method('createListing')
            ->willReturnCallback(function ($t, $type, $id, $price, $price2, $mode, $duration, $extra) use (&$createdTypes) {
                $createdTypes[] = $type;
                return new \App\Entity\Marketplace\MarketplaceListing();
            });

        $this->service->simulateMarketplaceActions($kingdom, new \DateTimeImmutable(), $team);

        // Verify that we listed both combatants and trainers (separately)
        $this->assertContains('hero', $createdTypes);
        $this->assertContains('trainer', $createdTypes);
    }

    public function testSimulateMarketplaceNeedBasedItemBuying(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $this->setEntityId($team, 2); // royal_collector
        $team->setKingdom($kingdom);
        $team->setIsNpc(true);
        $team->setGold(1000);

        // Active lineup has 1 hero
        $hero = new Hero();
        $this->setEntityId($hero, 1);
        $hero->setStr(10);
        $hero->setKon(10);
        $hero->setDex(5);
        $hero->setIntel(5);

        // Mock formation with 1 slot containing the hero
        $slot = new FormationSlot();
        $slot->setHero($hero);
        $slot->setPosition(FormationPosition::Front1);

        $formation = new Formation();
        $formation->addSlot($slot);

        $formationRepo = $this->createMock(EntityRepository::class);
        $formationRepo->method('findOneBy')->willReturn($formation);

        // Hero currently has nothing equipped in MainHand
        $itemRepo = $this->createMock(EntityRepository::class);
        $itemRepo->method('findBy')->willReturnCallback(function (array $criteria) use ($hero) {
            // If fetching equipped items for the hero
            if (isset($criteria['equippedHero']) && $criteria['equippedHero'] === $hero) {
                return []; // none equipped
            }
            return [];
        });

        $heroRepo = $this->createMock(HeroRepository::class);
        // Empty queries for other parts
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn([]);
        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);
        $heroRepo->method('createQueryBuilder')->willReturn($qbMock);

        // Set up active listings in kingdom
        $sellerTeam = new Team();
        $this->setEntityId($sellerTeam, 99);

        // Listing 1: Common Bow (not preferred weapon, default is OneHandedSword since STR/KON are higher)
        $item1 = new Item();
        $item1->setSlotType(ItemSlotType::MainHand);
        $item1->setSubType(ItemSubType::Bow);
        $item1->setRarity(ItemRarity::Common);

        $listing1 = new MarketplaceListing();
        $this->setEntityId($listing1, 101);
        $listing1->setSellerTeam($sellerTeam);
        $listing1->setListingType(ListingType::Item);
        $listing1->setItem($item1);
        $listing1->setPriceGold(50);
        $listing1->setBuyoutPriceGold(50);

        // Listing 2: Epic OneHandedSword (preferred weapon, highly rated upgrade)
        $item2 = new Item();
        $item2->setSlotType(ItemSlotType::MainHand);
        $item2->setSubType(ItemSubType::OneHandedSword);
        $item2->setRarity(ItemRarity::Epic);

        $listing2 = new MarketplaceListing();
        $this->setEntityId($listing2, 102);
        $listing2->setSellerTeam($sellerTeam);
        $listing2->setListingType(ListingType::Item);
        $listing2->setItem($item2);
        $listing2->setPriceGold(200);
        $listing2->setBuyoutPriceGold(200);

        $listingRepo = $this->createMock(EntityRepository::class);
        $listingRepo->method('findBy')->willReturn([$listing1, $listing2]);

        $this->em->method('getRepository')->willReturnCallback(function (string $class) use ($formationRepo, $itemRepo, $heroRepo, $listingRepo) {
            if (Formation::class === $class) return $formationRepo;
            if (Item::class === $class) return $itemRepo;
            if (Hero::class === $class) return $heroRepo;
            if (MarketplaceListing::class === $class) return $listingRepo;
            return $this->createMock(EntityRepository::class);
        });

        $hq = new Headquarters();
        $this->hqService->method('getForTeam')->willReturn($hq);
        $this->hqService->method('calculateWeeklyMaintenanceFee')->willReturn(50);

        // Capture bought listings
        $boughtListingIds = [];
        $this->marketplaceService->method('buyListing')
            ->willReturnCallback(function ($team, $listingId, $now) use (&$boughtListingIds) {
                $boughtListingIds[] = $listingId;
            });

        $this->service->simulateMarketplaceActions($kingdom, new \DateTimeImmutable(), $team);

        // Should buy listing 2 (Epic OneHandedSword) because it matches preferred weapon and is epic
        // Should NOT buy listing 1 because it's a Bow (not preferred subtype)
        $this->assertContains(102, $boughtListingIds);
        $this->assertNotContains(101, $boughtListingIds);
    }
}


