<?php

declare(strict_types=1);

namespace App\Service\Item;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Marketplace\MarketplaceTransaction;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\ItemCategory;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Enum\ItemSubType;
use App\Enum\TransactionType;
use App\Exception\UserFacingException;
use App\Repository\Item\ItemRepository;
use App\Service\Config\RaceConfig;
use App\Service\Economy\EconomyService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;

class ItemService
{
    /**
     * Gold scrap granted per rarity tier on dismantle.
     * Keys match ItemRarity values.
     */
    private const DISMANTLE_GOLD = [
        'common' => 25,
        'uncommon' => 50,
        'rare' => 100,
        'epic' => 250,
        'legendary' => 500,
        'mythic' => 1000,
    ];

    /** Gold cost per missing durability point by rarity. */
    private const REPAIR_COST_PER_POINT = [
        'common' => 2,
        'uncommon' => 5,
        'rare' => 10,
        'epic' => 20,
        'legendary' => 40,
        'mythic' => 80,
    ];

    /** Player/NPC listing suggested price as a fraction of merchant cost for catalog commons. */
    public const MARKET_MERCHANT_RATIO = 0.7;

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly EntityManagerInterface $em,
        private readonly UserMessageTranslator $translator,
        private readonly EconomyService $economyService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly RaceConfig $raceConfig,
    ) {
    }

    /** @return array<int, Item> */
    public function listByTeam(Team $team, ?Hero $hero = null): array
    {
        $criteria = ['ownerTeam' => $team];
        if (null !== $hero) {
            $criteria['equippedHero'] = $hero;
        }

        return $this->itemRepository->findBy($criteria, ['id' => 'ASC']);
    }

    public function findForTeam(int $id, Team $team): ?Item
    {
        return $this->itemRepository->findOneBy(['id' => $id, 'ownerTeam' => $team]);
    }

    /**
     * Slots an item may occupy. Ring items fit either ring slot; dual-wieldable
     * one-handers fit main_hand or off_hand. Everything else matches slot_type.
     *
     * @return list<ItemSlotType>
     */
    public static function compatibleSlots(Item $item): array
    {
        if (ItemSlotType::Ring === $item->getSlotType()) {
            return [ItemSlotType::Ring, ItemSlotType::Ring2];
        }

        if ($item->getSubType()?->isDualWieldable() ?? false) {
            return [ItemSlotType::MainHand, ItemSlotType::OffHand];
        }

        return [$item->getSlotType()];
    }

    public static function occupiesOffHand(Item $item): bool
    {
        return $item->getSubType()?->occupiesBothHands() ?? false;
    }

    /**
     * Compatible slots that this hero's race may actually use.
     *
     * @return list<ItemSlotType>
     */
    public function allowedEquipSlots(Item $item, Hero $hero): array
    {
        $allowed = [];
        foreach (self::compatibleSlots($item) as $slot) {
            if ($this->isSlotAllowedForHero($hero, $item, $slot)) {
                $allowed[] = $slot;
            }
        }

        return $allowed;
    }

    public function isSlotAllowedForHero(Hero $hero, Item $item, ItemSlotType $slot): bool
    {
        foreach ($this->raceConfig->getEquipmentRestrictions($hero->getRace()) as $restriction) {
            if ($item->getSlotType()->value === $restriction
                || $item->getCategory()->value === $restriction
                || $slot->value === $restriction) {
                return false;
            }
        }

        return true;
    }

    /**
     * Equip an item to a hero's slot. Clears the target slot, and for two-handed
     * occupancy also clears the other hand.
     *
     * @throws \DomainException
     */
    public function equip(Item $item, Hero $hero, ItemSlotType $slot): void
    {
        if (ItemStatus::Available !== $item->getStatus()) {
            throw new UserFacingException('error.item_cannot_equip');
        }

        if ($item->getOwnerTeam()->getId() !== $hero->getTeam()->getId()) {
            throw new UserFacingException('error.item_wrong_team');
        }

        if (!\in_array($slot, self::compatibleSlots($item), true)) {
            throw new UserFacingException('error.item_slot_mismatch', ['%item_slot%' => $item->getSlotType()->value, '%slot%' => $slot->value]);
        }

        if (!$this->isSlotAllowedForHero($hero, $item, $slot)) {
            $this->throwRaceRestricted($hero, $item, $slot);
        }

        $existing = $this->itemRepository->findOneBy([
            'equippedHero' => $hero,
            'equippedSlot' => $slot,
        ]);
        if (null !== $existing && $existing !== $item) {
            $this->clearEquipState($existing);
        }

        if (ItemSlotType::MainHand === $slot && self::occupiesOffHand($item)) {
            $this->clearHeroSlot($hero, ItemSlotType::OffHand, $item);
        }

        if (ItemSlotType::OffHand === $slot) {
            $mainHand = $this->itemRepository->findOneBy([
                'equippedHero' => $hero,
                'equippedSlot' => ItemSlotType::MainHand,
            ]);
            if (null !== $mainHand && $mainHand !== $item && self::occupiesOffHand($mainHand)) {
                $this->clearEquipState($mainHand);
            }
        }

        if (null !== $item->getEquippedHero()) {
            $this->clearEquipState($item);
        }

        $item->setEquippedHero($hero);
        $item->setEquippedSlot($slot);

        $this->em->flush();
    }

    /**
     * Unequip off-hand items that sit next to a two-handed main-hand weapon.
     * Silent repair for rows created before occupancy was enforced.
     */
    public function clearIllegalTwoHandOccupancy(): int
    {
        $cleared = 0;
        $mainHands = $this->itemRepository->findBy(['equippedSlot' => ItemSlotType::MainHand]);
        foreach ($mainHands as $main) {
            $hero = $main->getEquippedHero();
            if (null === $hero || !self::occupiesOffHand($main)) {
                continue;
            }

            $offHand = $this->itemRepository->findOneBy([
                'equippedHero' => $hero,
                'equippedSlot' => ItemSlotType::OffHand,
            ]);
            if (null === $offHand) {
                continue;
            }

            $this->clearEquipState($offHand);
            ++$cleared;
        }

        if ($cleared > 0) {
            $this->em->flush();
        }

        return $cleared;
    }

    /**
     * Unequip an item from its current hero.
     *
     * @throws \DomainException
     */
    public function unequip(Item $item): void
    {
        if (null === $item->getEquippedHero()) {
            throw new UserFacingException('error.item_not_equipped');
        }

        $this->clearEquipState($item);
        $this->em->flush();
    }

    private function clearHeroSlot(Hero $hero, ItemSlotType $slot, Item $except): void
    {
        $occupant = $this->itemRepository->findOneBy([
            'equippedHero' => $hero,
            'equippedSlot' => $slot,
        ]);
        if (null !== $occupant && $occupant !== $except) {
            $this->clearEquipState($occupant);
        }
    }

    private function clearEquipState(Item $item): void
    {
        $item->setEquippedHero(null);
        $item->setEquippedSlot(null);
    }

    private function throwRaceRestricted(Hero $hero, Item $item, ItemSlotType $slot): never
    {
        $raceKey = 'heroes.race_'.$hero->getRace()->value;
        $translatedRace = $this->translator->trans($raceKey);

        $matched = $slot->value;
        foreach ($this->raceConfig->getEquipmentRestrictions($hero->getRace()) as $restriction) {
            if ($item->getSlotType()->value === $restriction
                || $item->getCategory()->value === $restriction
                || $slot->value === $restriction) {
                $matched = $restriction;
                break;
            }
        }

        $restrictionKey = $matched;
        if (null !== ItemSlotType::tryFrom($matched)) {
            $restrictionKey = 'inventory.slot_'.$matched;
        } elseif (null !== ItemCategory::tryFrom($matched)) {
            $restrictionKey = 'inventory.cat_'.$matched;
        }

        throw new UserFacingException('error.item_race_restricted', [
            '%race%' => $translatedRace,
            '%restriction%' => $this->translator->trans($restrictionKey),
        ]);
    }

    /**
     * Dismantle an item: delete it and return gold scrap to the team.
     *
     * @return array{rarity: string, gold_gained: int}
     *
     * @throws \DomainException when item is currently equipped
     */
    public function dismantle(Item $item, Team $team): array
    {
        if (ItemStatus::Available !== $item->getStatus()) {
            throw new UserFacingException('error.item_cannot_dismantle');
        }

        if ($item->getOwnerTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.item_not_on_team');
        }

        if (null !== $item->getEquippedHero()) {
            throw new UserFacingException('error.item_cannot_dismantle_equipped');
        }

        $rarity = $item->getRarity();
        $amount = self::DISMANTLE_GOLD[$rarity->value];
        $this->economyService->addGold(
            $team,
            $amount,
            FinancialRecordType::DismantleGain,
            FinancialRecordActor::Active,
            ['item_id' => $item->getId(), 'rarity' => $rarity->value]
        );

        $this->teamChronicleService->recordItemDismantled($team, $item, $amount, $rarity->value);
        $this->em->remove($item);
        $this->em->flush();

        return ['rarity' => $rarity->value, 'gold_gained' => $amount];
    }

    /**
     * Repair durability to 100. Gold cost scales with rarity and missing durability.
     *
     * @throws \DomainException on insufficient gold
     */
    public function repair(Item $item, Team $team): int
    {
        if (ItemStatus::Available !== $item->getStatus()) {
            throw new UserFacingException('error.item_cannot_repair');
        }

        if ($item->getOwnerTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.item_not_on_team');
        }

        $missing = 100 - $item->getDurability();
        if (0 === $missing) {
            return 0;
        }

        $ratePerPoint = self::REPAIR_COST_PER_POINT[$item->getRarity()->value];
        $cost = $missing * $ratePerPoint;

        if ($team->getGold() < $cost) {
            throw new UserFacingException('error.item_repair_insufficient_gold', ['%cost%' => $cost, '%available%' => $team->getGold()]);
        }

        $this->economyService->deductGold(
            $team,
            $cost,
            FinancialRecordType::ItemRepair,
            FinancialRecordActor::Active,
            ['item_id' => $item->getId(), 'durability_repaired' => $missing]
        );
        $item->setDurability(100);

        $this->em->flush();

        return $cost;
    }

    public function calculateRepairCost(Item $item): int
    {
        $missing = 100 - $item->getDurability();
        $ratePerPoint = self::REPAIR_COST_PER_POINT[$item->getRarity()->value];

        return $missing * $ratePerPoint;
    }

    /**
     * Player-facing item payload. Omits unused `special_effects` (A7.4 / #2).
     *
     * @return array<string, mixed>
     */
    public function serialize(Item $item): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'slot_type' => $item->getSlotType()->value,
            'category' => $item->getCategory()->value,
            'rarity' => $item->getRarity()->value,
            'durability' => $item->getDurability(),
            'repair_cost' => $this->calculateRepairCost($item),
            'bonuses' => $item->getBonuses(),
            'equipped_hero_id' => $item->getEquippedHero()?->getId(),
            'equipped_slot' => $item->getEquippedSlot()?->value,
            'sub_type' => $item->getSubType()?->value,
            'compatible_slots' => array_map(
                static fn (ItemSlotType $slot): string => $slot->value,
                self::compatibleSlots($item),
            ),
        ];
    }

    /**
     * @var array<string, array{
     *     name_key: string,
     *     slot: ItemSlotType,
     *     category: ItemCategory,
     *     cost: int,
     *     bonuses: array<string, int|string>,
     *     sub_type?: string
     * }>
     */
    public const BASIC_EQUIPMENT = [
        'short_sword' => [
            'name_key' => 'item.short_sword',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'one_handed_sword',
            'cost' => 50,
            'bonuses' => ['damage' => 10],
        ],
        'short_bow' => [
            'name_key' => 'item.short_bow',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'bow',
            'cost' => 60,
            'bonuses' => ['damage' => 8, 'type' => 'ranged'],
        ],
        'wooden_shield' => [
            'name_key' => 'item.wooden_shield',
            'slot' => ItemSlotType::OffHand,
            'category' => ItemCategory::Shield,
            'sub_type' => 'shield',
            'cost' => 40,
            'bonuses' => ['armor' => 10],
        ],
        'apprentice_wand' => [
            'name_key' => 'item.apprentice_wand',
            'slot' => ItemSlotType::OffHand,
            'category' => ItemCategory::SpellAccelerator,
            'sub_type' => 'spell_accelerator',
            'cost' => 60,
            'bonuses' => ['spell_power' => 10],
        ],
        'leather_helmet' => [
            'name_key' => 'item.leather_helmet',
            'slot' => ItemSlotType::Head,
            'category' => ItemCategory::Armor,
            'sub_type' => 'light_armor',
            'cost' => 30,
            'bonuses' => ['armor' => 5],
        ],
        'leather_jerkin' => [
            'name_key' => 'item.leather_jerkin',
            'slot' => ItemSlotType::Body,
            'category' => ItemCategory::Armor,
            'sub_type' => 'light_armor',
            'cost' => 50,
            'bonuses' => ['armor' => 12],
        ],
        'leather_gloves' => [
            'name_key' => 'item.leather_gloves',
            'slot' => ItemSlotType::Hands,
            'category' => ItemCategory::Armor,
            'sub_type' => 'light_armor',
            'cost' => 25,
            'bonuses' => ['armor' => 4],
        ],
        'leather_boots' => [
            'name_key' => 'item.leather_boots',
            'slot' => ItemSlotType::Feet,
            'category' => ItemCategory::Armor,
            'sub_type' => 'light_armor',
            'cost' => 30,
            'bonuses' => ['armor' => 5],
        ],
        'chain_coif' => [
            'name_key' => 'item.chain_coif',
            'slot' => ItemSlotType::Head,
            'category' => ItemCategory::Armor,
            'sub_type' => 'medium_armor',
            'cost' => 45,
            'bonuses' => ['armor' => 8],
        ],
        'chain_hauberk' => [
            'name_key' => 'item.chain_hauberk',
            'slot' => ItemSlotType::Body,
            'category' => ItemCategory::Armor,
            'sub_type' => 'medium_armor',
            'cost' => 75,
            'bonuses' => ['armor' => 18],
        ],
        'chain_gloves' => [
            'name_key' => 'item.chain_gloves',
            'slot' => ItemSlotType::Hands,
            'category' => ItemCategory::Armor,
            'sub_type' => 'medium_armor',
            'cost' => 35,
            'bonuses' => ['armor' => 6],
        ],
        'chain_boots' => [
            'name_key' => 'item.chain_boots',
            'slot' => ItemSlotType::Feet,
            'category' => ItemCategory::Armor,
            'sub_type' => 'medium_armor',
            'cost' => 45,
            'bonuses' => ['armor' => 8],
        ],
        'iron_helmet' => [
            'name_key' => 'item.iron_helmet',
            'slot' => ItemSlotType::Head,
            'category' => ItemCategory::Armor,
            'sub_type' => 'heavy_armor',
            'cost' => 60,
            'bonuses' => ['armor' => 12],
        ],
        'plate_armor' => [
            'name_key' => 'item.plate_armor',
            'slot' => ItemSlotType::Body,
            'category' => ItemCategory::Armor,
            'sub_type' => 'heavy_armor',
            'cost' => 100,
            'bonuses' => ['armor' => 28],
        ],
        'iron_gauntlets' => [
            'name_key' => 'item.iron_gauntlets',
            'slot' => ItemSlotType::Hands,
            'category' => ItemCategory::Armor,
            'sub_type' => 'heavy_armor',
            'cost' => 50,
            'bonuses' => ['armor' => 9],
        ],
        'iron_greaves' => [
            'name_key' => 'item.iron_greaves',
            'slot' => ItemSlotType::Feet,
            'category' => ItemCategory::Armor,
            'sub_type' => 'heavy_armor',
            'cost' => 60,
            'bonuses' => ['armor' => 12],
        ],
        'greatsword' => [
            'name_key' => 'item.greatsword',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'two_handed_sword',
            'cost' => 75,
            'bonuses' => ['damage' => 16],
        ],
        'hand_axe' => [
            'name_key' => 'item.hand_axe',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'one_handed_axe',
            'cost' => 45,
            'bonuses' => ['damage' => 9],
        ],
        'battle_axe' => [
            'name_key' => 'item.battle_axe',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'two_handed_axe',
            'cost' => 70,
            'bonuses' => ['damage' => 15],
        ],
        'flanged_mace' => [
            'name_key' => 'item.flanged_mace',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'one_handed_mace',
            'cost' => 50,
            'bonuses' => ['damage' => 11],
        ],
        'warhammer' => [
            'name_key' => 'item.warhammer',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'two_handed_mace',
            'cost' => 75,
            'bonuses' => ['damage' => 17],
        ],
        'iron_dagger' => [
            'name_key' => 'item.iron_dagger',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'dagger',
            'cost' => 35,
            'bonuses' => ['damage' => 6],
        ],
        'light_crossbow' => [
            'name_key' => 'item.light_crossbow',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::Weapon,
            'sub_type' => 'crossbow',
            'cost' => 65,
            'bonuses' => ['damage' => 10, 'type' => 'ranged'],
        ],
        'wooden_wand' => [
            'name_key' => 'item.wooden_wand',
            'slot' => ItemSlotType::OffHand,
            'category' => ItemCategory::SpellAccelerator,
            'sub_type' => 'wand',
            'cost' => 55,
            'bonuses' => ['spell_power' => 8],
        ],
        'wooden_staff' => [
            'name_key' => 'item.wooden_staff',
            'slot' => ItemSlotType::MainHand,
            'category' => ItemCategory::SpellAccelerator,
            'sub_type' => 'staff',
            'cost' => 70,
            'bonuses' => ['spell_power' => 12],
        ],
        'copper_ring' => [
            'name_key' => 'item.copper_ring',
            'slot' => ItemSlotType::Ring,
            'category' => ItemCategory::Accessory,
            'cost' => 40,
            'bonuses' => ['str' => 1],
        ],
        'silver_amulet' => [
            'name_key' => 'item.silver_amulet',
            'slot' => ItemSlotType::Amulet,
            'category' => ItemCategory::Accessory,
            'cost' => 50,
            'bonuses' => ['resistance' => 5],
        ],
    ];

    public function purchaseBasicItem(Team $team, string $itemKey): Item
    {
        if (!isset(self::BASIC_EQUIPMENT[$itemKey])) {
            throw new UserFacingException('error.basic_item_invalid');
        }

        $template = self::BASIC_EQUIPMENT[$itemKey];
        $cost = $template['cost'];

        if ($team->getGold() < $cost) {
            throw new UserFacingException('error.item_purchase_insufficient_gold', ['%cost%' => $cost, '%available%' => $team->getGold()]);
        }

        // Deduct gold
        $this->economyService->deductGold(
            $team,
            $cost,
            FinancialRecordType::MarketplacePurchase,
            FinancialRecordActor::Active
        );

        // Create Item
        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setName($this->translator->trans($template['name_key']));
        $item->setSlotType($template['slot']);
        $item->setCategory($template['category']);
        $item->setRarity(ItemRarity::Common);
        $item->setDurability(100);
        $item->setBonuses($template['bonuses']);
        $item->setSpecialEffects([]);
        $item->setStatus(ItemStatus::Available);
        $item->setSubType(isset($template['sub_type']) ? ItemSubType::tryFrom($template['sub_type']) : null);

        $this->em->persist($item);
        $this->em->flush();

        // Record chronicle entry
        $this->teamChronicleService->recordItemPurchased($team, $item, null, $cost);

        // Record marketplace transaction
        $transaction = new MarketplaceTransaction();
        $transaction->setBuyerTeam($team);
        $transaction->setSellerTeam(null);
        $transaction->setListing(null);
        $transaction->setAmount($cost);
        $transaction->setFeeAmount(0);
        $transaction->setType(TransactionType::BuyNow);
        $transaction->setEntityName($item->getName());

        $this->em->persist($transaction);
        $this->em->flush();

        return $item;
    }

    /**
     * Suggested marketplace gold for an item (NPC listings and player price guidance).
     * Catalog commons: 70% of merchant cost. Otherwise rarity table.
     */
    public function estimateMarketPrice(Item $item): int
    {
        $merchantPrice = $this->getBasicItemMerchantPrice($item);
        if (null !== $merchantPrice) {
            return max(1, (int) round($merchantPrice * self::MARKET_MERCHANT_RATIO));
        }

        return max(1, match ($item->getRarity()) {
            ItemRarity::Common => 75,
            ItemRarity::Uncommon => 180,
            ItemRarity::Rare => 400,
            ItemRarity::Epic => 1000,
            ItemRarity::Legendary => 2500,
            ItemRarity::Mythic => 6000,
        });
    }

    /**
     * If the item is a common basic item matching the merchant catalog, return its merchant cost.
     */
    public function getBasicItemMerchantPrice(Item $item): ?int
    {
        if (ItemRarity::Common !== $item->getRarity()) {
            return null;
        }

        foreach (self::BASIC_EQUIPMENT as $template) {
            if ($template['slot'] === $item->getSlotType()) {
                $subTypeVal = $item->getSubType()?->value;
                if (isset($template['sub_type']) && $subTypeVal === $template['sub_type']) {
                    return $template['cost'];
                }
                if (!isset($template['sub_type']) && null === $subTypeVal) {
                    return $template['cost'];
                }
            }
        }

        return null;
    }
}
