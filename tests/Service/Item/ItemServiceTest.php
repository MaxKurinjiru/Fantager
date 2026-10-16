<?php

declare(strict_types=1);

namespace App\Tests\Service\Item;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Marketplace\MarketplaceTransaction;
use App\Entity\Team\Team;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Enum\ItemSubType;
use App\Enum\Race;
use App\Enum\ItemCategory;
use App\Service\Config\RaceConfig;
use App\Repository\Item\ItemRepository;
use App\Exception\UserFacingException;
use App\Service\Item\ItemService;
use App\Service\Economy\EconomyService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ItemServiceTest extends TestCase
{
    private ItemRepository&MockObject $itemRepositoryMock;
    private EntityManagerInterface&MockObject $entityManagerMock;
    private TranslatorInterface&MockObject $symfonyTranslatorMock;
    private RequestStack&MockObject $requestStackMock;
    private EconomyService&MockObject $economyServiceMock;
    private TeamChronicleService&MockObject $teamChronicleServiceMock;
    private RaceConfig&MockObject $raceConfigMock;
    private ItemService $itemService;

    protected function setUp(): void
    {
        $this->itemRepositoryMock = $this->createMock(ItemRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->symfonyTranslatorMock = $this->createMock(TranslatorInterface::class);
        $this->requestStackMock = $this->createMock(RequestStack::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->economyServiceMock
            ->method('deductGold')
            ->willReturnCallback(static function (Team $team, int $amount): void {
                $team->setGold($team->getGold() - $amount);
            });
        $this->teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);
        $this->raceConfigMock = $this->createMock(RaceConfig::class);
        
        $translator = new UserMessageTranslator($this->symfonyTranslatorMock, $this->requestStackMock);
        
        $this->itemService = new ItemService(
            $this->itemRepositoryMock,
            $this->entityManagerMock,
            $translator,
            $this->economyServiceMock,
            $this->teamChronicleServiceMock,
            $this->raceConfigMock
        );
    }

    public function testDismantleRejectsEquippedItem(): void
    {
        $team = new Team();
        $hero = new Hero();
        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setStatus(ItemStatus::Available);
        $item->setEquippedHero($hero);
        $item->setRarity(ItemRarity::Common);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_cannot_dismantle_equipped');

        $this->itemService->dismantle($item, $team);
    }

    public function testCalculateRepairCostScalesWithMissingDurability(): void
    {
        $item = new Item();
        $item->setRarity(ItemRarity::Rare);
        $item->setDurability(80);

        $this->assertSame(200, $this->itemService->calculateRepairCost($item));
    }

    public function testEquipRejectsWrongSlotType(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);

        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setStatus(ItemStatus::Available);
        $item->setSlotType(ItemSlotType::Head);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_slot_mismatch');

        $this->itemService->equip($item, $hero, ItemSlotType::Body);
    }

    public function testPurchaseBasicItemSuccess(): void
    {
        $team = new Team();
        $team->setGold(100);

        $this->symfonyTranslatorMock
            ->method('trans')
            ->willReturnCallback(static function (
                string $id,
                array $params = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                self::assertSame('item.short_sword', $id);
                self::assertSame([], $params);
                self::assertSame('messages', $domain);
                self::assertSame('cs', $locale);

                return 'Short Sword';
            });

        $this->entityManagerMock
            ->method('persist')
            ->willReturnCallback(static function (object $entity): void {
                self::assertTrue($entity instanceof Item || $entity instanceof MarketplaceTransaction || $entity instanceof \App\Entity\Team\TeamChronicle);
            });
        $this->entityManagerMock->expects($this->exactly(2))
            ->method('flush');

        $item = $this->itemService->purchaseBasicItem($team, 'short_sword');

        $this->assertSame(50, $team->getGold());
        $this->assertSame($team, $item->getOwnerTeam());
        $this->assertSame('Short Sword', $item->getName());
        $this->assertSame(ItemSlotType::MainHand, $item->getSlotType());
        $this->assertSame(100, $item->getDurability());
        $this->assertSame(['damage' => 10], $item->getBonuses());
    }

    public function testPurchaseBasicItemInsufficientGold(): void
    {
        $team = new Team();
        $team->setGold(10);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_purchase_insufficient_gold');

        $this->itemService->purchaseBasicItem($team, 'short_sword');
    }

    public function testEquipRejectsRestrictedRaceCategory(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Ent);

        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setStatus(ItemStatus::Available);
        $item->setSlotType(ItemSlotType::MainHand);
        $item->setCategory(ItemCategory::Weapon);

        $this->raceConfigMock
            ->expects($this->exactly(2))
            ->method('getEquipmentRestrictions')
            ->with(Race::Ent)
            ->willReturn(['weapon', 'shield', 'armor']);

        $this->symfonyTranslatorMock
            ->method('trans')
            ->willReturnCallback(static function (string $id): string {
                if ($id === 'heroes.race_ent') {
                    return 'Ent';
                }
                if ($id === 'inventory.cat_weapon') {
                    return 'Weapon';
                }
                return $id;
            });

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_race_restricted');

        $this->itemService->equip($item, $hero, ItemSlotType::MainHand);
    }

    public function testEquipRejectsRestrictedRaceSlotType(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Giant);

        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setStatus(ItemStatus::Available);
        $item->setSlotType(ItemSlotType::OffHand);
        $item->setCategory(ItemCategory::Shield);

        $this->raceConfigMock
            ->expects($this->exactly(2))
            ->method('getEquipmentRestrictions')
            ->with(Race::Giant)
            ->willReturn(['off_hand']);

        $this->symfonyTranslatorMock
            ->method('trans')
            ->willReturnCallback(static function (string $id): string {
                if ($id === 'heroes.race_giant') {
                    return 'Giant';
                }
                if ($id === 'inventory.slot_off_hand') {
                    return 'Off-Hand';
                }
                return $id;
            });

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_race_restricted');

        $this->itemService->equip($item, $hero, ItemSlotType::OffHand);
    }

    public function testCompatibleSlotsForRingAndDualWieldAndTwoHand(): void
    {
        $ring = new Item();
        $ring->setSlotType(ItemSlotType::Ring);
        $this->assertSame(
            [ItemSlotType::Ring, ItemSlotType::Ring2],
            ItemService::compatibleSlots($ring),
        );

        $sword = new Item();
        $sword->setSlotType(ItemSlotType::MainHand);
        $sword->setSubType(\App\Enum\ItemSubType::OneHandedSword);
        $this->assertSame(
            [ItemSlotType::MainHand, ItemSlotType::OffHand],
            ItemService::compatibleSlots($sword),
        );

        $greatsword = new Item();
        $greatsword->setSlotType(ItemSlotType::MainHand);
        $greatsword->setSubType(\App\Enum\ItemSubType::TwoHandedSword);
        $this->assertSame(
            [ItemSlotType::MainHand],
            ItemService::compatibleSlots($greatsword),
        );
    }

    public function testEquipTwoHandedClearsOffHand(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Human);

        $shield = new Item();
        $shield->setOwnerTeam($team);
        $shield->setStatus(ItemStatus::Available);
        $shield->setSlotType(ItemSlotType::OffHand);
        $shield->setCategory(ItemCategory::Shield);
        $shield->setEquippedHero($hero);
        $shield->setEquippedSlot(ItemSlotType::OffHand);

        $greatsword = new Item();
        $greatsword->setOwnerTeam($team);
        $greatsword->setStatus(ItemStatus::Available);
        $greatsword->setSlotType(ItemSlotType::MainHand);
        $greatsword->setCategory(ItemCategory::Weapon);
        $greatsword->setSubType(\App\Enum\ItemSubType::TwoHandedSword);

        $this->raceConfigMock->method('getEquipmentRestrictions')->willReturn([]);
        $this->stubEquippedLookup([
            ItemSlotType::OffHand->value => $shield,
        ]);
        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->itemService->equip($greatsword, $hero, ItemSlotType::MainHand);

        $this->assertSame($hero, $greatsword->getEquippedHero());
        $this->assertSame(ItemSlotType::MainHand, $greatsword->getEquippedSlot());
        $this->assertNull($shield->getEquippedHero());
        $this->assertNull($shield->getEquippedSlot());
    }

    public function testEquipOffHandClearsTwoHandedMain(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Human);

        $bow = new Item();
        $bow->setOwnerTeam($team);
        $bow->setStatus(ItemStatus::Available);
        $bow->setSlotType(ItemSlotType::MainHand);
        $bow->setCategory(ItemCategory::Weapon);
        $bow->setSubType(\App\Enum\ItemSubType::Bow);
        $bow->setEquippedHero($hero);
        $bow->setEquippedSlot(ItemSlotType::MainHand);

        $shield = new Item();
        $shield->setOwnerTeam($team);
        $shield->setStatus(ItemStatus::Available);
        $shield->setSlotType(ItemSlotType::OffHand);
        $shield->setCategory(ItemCategory::Shield);

        $this->raceConfigMock->method('getEquipmentRestrictions')->willReturn([]);
        $this->stubEquippedLookup([
            ItemSlotType::MainHand->value => $bow,
        ]);
        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->itemService->equip($shield, $hero, ItemSlotType::OffHand);

        $this->assertSame($hero, $shield->getEquippedHero());
        $this->assertNull($bow->getEquippedHero());
    }

    public function testEquipDualWieldIntoOffHand(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Human);

        $dagger = new Item();
        $dagger->setOwnerTeam($team);
        $dagger->setStatus(ItemStatus::Available);
        $dagger->setSlotType(ItemSlotType::MainHand);
        $dagger->setCategory(ItemCategory::Weapon);
        $dagger->setSubType(\App\Enum\ItemSubType::Dagger);

        $this->raceConfigMock->method('getEquipmentRestrictions')->willReturn([]);
        $this->stubEquippedLookup([]);
        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->itemService->equip($dagger, $hero, ItemSlotType::OffHand);

        $this->assertSame(ItemSlotType::OffHand, $dagger->getEquippedSlot());
    }

    public function testEquipSecondRingIntoRing2(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Human);

        $first = new Item();
        $first->setOwnerTeam($team);
        $first->setStatus(ItemStatus::Available);
        $first->setSlotType(ItemSlotType::Ring);
        $first->setCategory(ItemCategory::Accessory);
        $first->setEquippedHero($hero);
        $first->setEquippedSlot(ItemSlotType::Ring);

        $second = new Item();
        $second->setOwnerTeam($team);
        $second->setStatus(ItemStatus::Available);
        $second->setSlotType(ItemSlotType::Ring);
        $second->setCategory(ItemCategory::Accessory);

        $this->raceConfigMock->method('getEquipmentRestrictions')->willReturn([]);
        $this->stubEquippedLookup([
            ItemSlotType::Ring->value => $first,
        ]);
        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->itemService->equip($second, $hero, ItemSlotType::Ring2);

        $this->assertSame(ItemSlotType::Ring2, $second->getEquippedSlot());
        $this->assertSame($hero, $first->getEquippedHero());
    }

    public function testEquipRejectsTwoHandedIntoOffHand(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);

        $greatsword = new Item();
        $greatsword->setOwnerTeam($team);
        $greatsword->setStatus(ItemStatus::Available);
        $greatsword->setSlotType(ItemSlotType::MainHand);
        $greatsword->setCategory(ItemCategory::Weapon);
        $greatsword->setSubType(\App\Enum\ItemSubType::TwoHandedSword);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_slot_mismatch');

        $this->itemService->equip($greatsword, $hero, ItemSlotType::OffHand);
    }

    public function testGiantCannotDualWieldOffHand(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Giant);

        $sword = new Item();
        $sword->setOwnerTeam($team);
        $sword->setStatus(ItemStatus::Available);
        $sword->setSlotType(ItemSlotType::MainHand);
        $sword->setCategory(ItemCategory::Weapon);
        $sword->setSubType(\App\Enum\ItemSubType::OneHandedSword);

        $this->raceConfigMock
            ->expects($this->exactly(2))
            ->method('getEquipmentRestrictions')
            ->with(Race::Giant)
            ->willReturn(['off_hand']);

        $this->symfonyTranslatorMock
            ->method('trans')
            ->willReturnCallback(static function (string $id): string {
                return $id;
            });

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.item_race_restricted');

        $this->itemService->equip($sword, $hero, ItemSlotType::OffHand);
    }

    public function testClearIllegalTwoHandOccupancyUnequipsOffHand(): void
    {
        $hero = new Hero();
        $greatsword = new Item();
        $greatsword->setSlotType(ItemSlotType::MainHand);
        $greatsword->setSubType(\App\Enum\ItemSubType::TwoHandedSword);
        $greatsword->setEquippedHero($hero);
        $greatsword->setEquippedSlot(ItemSlotType::MainHand);

        $shield = new Item();
        $shield->setSlotType(ItemSlotType::OffHand);
        $shield->setEquippedHero($hero);
        $shield->setEquippedSlot(ItemSlotType::OffHand);

        $this->itemRepositoryMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['equippedSlot' => ItemSlotType::MainHand])
            ->willReturn([$greatsword]);
        $this->itemRepositoryMock
            ->method('findOneBy')
            ->willReturn($shield);
        $this->entityManagerMock->expects($this->once())->method('flush');

        $cleared = $this->itemService->clearIllegalTwoHandOccupancy();

        $this->assertSame(1, $cleared);
        $this->assertNull($shield->getEquippedHero());
        $this->assertNull($shield->getEquippedSlot());
    }

    public function testEstimateMarketPriceUsesMerchantRatioForCatalogCommon(): void
    {
        $item = new Item();
        $item->setRarity(ItemRarity::Common);
        $item->setSlotType(ItemSlotType::MainHand);
        $item->setSubType(ItemSubType::OneHandedSword);

        $this->assertSame(35, $this->itemService->estimateMarketPrice($item));
    }

    public function testEstimateMarketPriceUsesRarityTableWhenNotMerchantCatalog(): void
    {
        $item = new Item();
        $item->setRarity(ItemRarity::Uncommon);
        $item->setSlotType(ItemSlotType::MainHand);
        $item->setSubType(ItemSubType::OneHandedSword);

        $this->assertSame(180, $this->itemService->estimateMarketPrice($item));
    }

    public function testSerializeOmitsUnusedSpecialEffects(): void
    {
        $item = new Item();
        $item->setName('Test Sword');
        $item->setSlotType(ItemSlotType::MainHand);
        $item->setCategory(ItemCategory::Weapon);
        $item->setRarity(ItemRarity::Common);
        $item->setDurability(100);
        $item->setSpecialEffects(['unused' => true]);

        $payload = $this->itemService->serialize($item);

        $this->assertArrayNotHasKey('special_effects', $payload);
        $this->assertArrayHasKey('bonuses', $payload);
        $this->assertSame(['unused' => true], $item->getSpecialEffects());
    }

    /**
     * @param array<string, Item> $equippedBySlot
     */
    private function stubEquippedLookup(array $equippedBySlot): void
    {
        $this->itemRepositoryMock
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($equippedBySlot): ?Item {
                $slot = $criteria['equippedSlot'] ?? null;
                if ($slot instanceof ItemSlotType) {
                    return $equippedBySlot[$slot->value] ?? null;
                }

                return null;
            });
    }
}
