<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Enum\ItemSubType;
use App\Service\Item\ItemService;
use Doctrine\ORM\EntityManagerInterface;

class NpcTacticsSimulator
{
    use NpcSimulationHelperTrait;

    private const SUBTYPE_TO_BASIC_ITEM = [
        'one_handed_sword' => 'short_sword',
        'two_handed_sword' => 'greatsword',
        'one_handed_axe' => 'hand_axe',
        'two_handed_axe' => 'battle_axe',
        'one_handed_mace' => 'flanged_mace',
        'two_handed_mace' => 'warhammer',
        'dagger' => 'iron_dagger',
        'bow' => 'short_bow',
        'crossbow' => 'light_crossbow',
        'wand' => 'wooden_wand',
        'staff' => 'wooden_staff',
        'shield' => 'wooden_shield',
        'spell_accelerator' => 'apprentice_wand',
    ];

    private const ARMOR_TO_BASIC_ITEM = [
        'light_armor' => [
            'head' => 'leather_helmet',
            'body' => 'leather_jerkin',
            'hands' => 'leather_gloves',
            'feet' => 'leather_boots',
        ],
        'medium_armor' => [
            'head' => 'chain_coif',
            'body' => 'chain_hauberk',
            'hands' => 'chain_gloves',
            'feet' => 'chain_boots',
        ],
        'heavy_armor' => [
            'head' => 'iron_helmet',
            'body' => 'plate_armor',
            'hands' => 'iron_gauntlets',
            'feet' => 'iron_greaves',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ItemService $itemService,
    ) {
    }

    /**
     * Run tactical simulation: optimize formation slotting (3-3), rotate tired heroes, and auto-equip gear.
     */
    public function simulateTactics(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            $teams = [$team];
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy([
                'kingdom' => $kingdom,
                'isNpc' => true,
            ]);
        }

        foreach ($teams as $team) {
            // 1. Get or create default formation
            $formation = $this->em->getRepository(Formation::class)->findOneBy([
                'team' => $team,
                'isDefault' => true,
                'isTemporary' => false,
            ]);

            if (null === $formation) {
                $formation = new Formation();
                $formation->setTeam($team);
                $formation->setName('Default NPC Formation');
                $formation->setIsDefault(true);
                $formation->setApproach(FormationApproach::Balanced);
                $this->em->persist($formation);

                foreach (FormationPosition::cases() as $pos) {
                    $slot = new FormationSlot();
                    $slot->setFormation($formation);
                    $slot->setPosition($pos);
                    $slot->setStrategy([]);
                    $slot->setSpellPriorities([]);
                    $this->em->persist($slot);
                    $formation->addSlot($slot);
                }
                $this->em->flush();
            }

            // 2. Get available non-trainer heroes
            $heroes = $this->em->getRepository(Hero::class)->findBy([
                'team' => $team,
                'status' => HeroStatus::Available,
            ]);

            if (\count($heroes) < 6) {
                continue;
            }

            $frontCandidates = [];
            $backCandidates = [];

            foreach ($heroes as $hero) {
                if ($hero->isTrainer()) {
                    continue;
                }
                $heroId = $hero->getId();
                if (null === $heroId) {
                    continue;
                }

                $fatigue = $hero->getFatigue();
                $form = $hero->getForm();

                $readinessMultiplier = 1.0;
                if ($fatigue > 50) {
                    $readinessMultiplier *= 0.5;
                }
                if ($form < 40) {
                    $readinessMultiplier *= 0.6;
                }

                $frontScore = ($hero->getKon() + $hero->getStr()) * $readinessMultiplier;
                $backScore = ($hero->getIntel() + $hero->getSpd() + $hero->getDex()) * $readinessMultiplier;

                // Trait-aware score adjustments
                $trait = $hero->getTrait();
                if (null !== $trait) {
                    // Traits boosting physical front-row performance
                    if (\in_array($trait, [HeroTrait::Berserker, HeroTrait::Overconfident, HeroTrait::BattleHardened], true)) {
                        $frontScore *= 1.20;
                    }
                    // Traits boosting back-row / ranged / magic performance
                    if (\in_array($trait, [HeroTrait::Glasscannon, HeroTrait::Reckless], true)) {
                        $backScore *= 1.20;
                        $frontScore *= 0.85; // Penalty for fragile back-row heroes in front
                    }
                    // Clutch: better front-row (thrives under pressure)
                    if (HeroTrait::Clutch === $trait) {
                        $frontScore *= 1.10;
                    }
                    // Fragile / GlassJaw: penalize front-row placement
                    if (\in_array($trait, [HeroTrait::Fragile, HeroTrait::GlassJaw], true)) {
                        $frontScore *= 0.80;
                    }
                    // Purely negative trait: heavily penalize so they prefer bench
                    if ($this->isPurelyNegativeTrait($trait)) {
                        $frontScore *= 0.10;
                        $backScore *= 0.10;
                    }
                }

                $frontCandidates[] = ['hero' => $hero, 'id' => $heroId, 'score' => $frontScore];
                $backCandidates[] = ['hero' => $hero, 'id' => $heroId, 'score' => $backScore];
            }

            usort($frontCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);
            usort($backCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);

            $selectedFront = [];
            $selectedBack = [];
            $usedHeroIds = [];

            foreach ($frontCandidates as $item) {
                if (\count($selectedFront) >= 3) {
                    break;
                }
                $hero = $item['hero'];
                $heroId = $item['id'];
                $selectedFront[] = $hero;
                $usedHeroIds[$heroId] = true;
            }

            foreach ($backCandidates as $item) {
                if (\count($selectedBack) >= 3) {
                    break;
                }
                $hero = $item['hero'];
                $heroId = $item['id'];
                if (!isset($usedHeroIds[$heroId])) {
                    $selectedBack[] = $hero;
                    $usedHeroIds[$heroId] = true;
                }
            }

            // Fallback if not enough unique heroes selected
            if (\count($selectedFront) + \count($selectedBack) < 6) {
                foreach ($heroes as $hero) {
                    if ($hero->isTrainer()) {
                        continue;
                    }
                    $heroId = $hero->getId();
                    if (null === $heroId) {
                        continue;
                    }
                    if (\count($selectedFront) + \count($selectedBack) >= 6) {
                        break;
                    }
                    if (!isset($usedHeroIds[$heroId])) {
                        if (\count($selectedFront) < 3) {
                            $selectedFront[] = $hero;
                        } else {
                            $selectedBack[] = $hero;
                        }
                        $usedHeroIds[$heroId] = true;
                    }
                }
            }

            // 3. Slot heroes & set strategies
            $frontIndex = 0;
            $backIndex = 0;
            $role = $this->getHelperEconomicRole($team);

            foreach ($formation->getSlots() as $slot) {
                $position = $slot->getPosition();
                if (\in_array($position, [FormationPosition::Front1, FormationPosition::Front2, FormationPosition::Front3], true)) {
                    $hero = $selectedFront[$frontIndex] ?? null;
                    $slot->setHero($hero);
                    ++$frontIndex;
                } else {
                    $hero = $selectedBack[$backIndex] ?? null;
                    $slot->setHero($hero);
                    ++$backIndex;
                }

                $slot->setStrategy($this->getStrategyForSlot($role, $position));
                $slot->setSpellPriorities($this->getSpellPrioritiesForSlot($role, $position));
            }

            $formation->setApproach($this->getApproachForRole($role));

            // 4. Auto-equip items to active lineup
            $activeLineup = array_merge($selectedFront, $selectedBack);
            $this->autoEquipItems($team, $activeLineup);
        }

        $this->em->flush();
    }

    /**
     * Auto-equip best available items from inventory to active heroes, considering masteries
     * and buying basic items as a fallback.
     *
     * @param array<Hero> $heroes
     */
    private function autoEquipItems(Team $team, array $heroes): void
    {
        $unequippedItems = $this->em->getRepository(Item::class)->findBy([
            'ownerTeam' => $team,
            'status' => ItemStatus::Available,
            'equippedHero' => null,
        ]);

        // Sort unequipped items by rarity descending to prioritize equipping better quality gear first
        $rarityValues = [
            'mythic' => 6,
            'legendary' => 5,
            'epic' => 4,
            'rare' => 3,
            'uncommon' => 2,
            'common' => 1,
        ];
        usort($unequippedItems, fn ($a, $b) => $rarityValues[$b->getRarity()->value] <=> $rarityValues[$a->getRarity()->value]);

        // Define a list of slots that we manage with masteries/basic items
        $managedSlots = [
            ItemSlotType::MainHand,
            ItemSlotType::OffHand,
            ItemSlotType::Head,
            ItemSlotType::Body,
            ItemSlotType::Hands,
            ItemSlotType::Feet,
        ];

        // 1. Optimal Mastery Pass
        foreach ($heroes as $hero) {
            $prefWeapon = $this->getPreferredWeaponSubType($hero);
            $prefArmor = $this->getPreferredArmorSubType($hero);
            $prefOffHand = $this->getPreferredOffHandSubType($hero, $prefWeapon);

            foreach ($managedSlots as $slot) {
                // Get the preferred sub-type for this slot
                $prefSubType = match ($slot) {
                    ItemSlotType::MainHand => $prefWeapon,
                    ItemSlotType::OffHand => $prefOffHand,
                    default => $prefArmor,
                };

                // Find currently equipped item in this slot
                $equipped = $this->em->getRepository(Item::class)->findOneBy([
                    'equippedHero' => $hero,
                    'equippedSlot' => $slot,
                ]);

                if (null === $prefSubType) {
                    // Two-handed weapon equipped, offhand must be empty
                    if (null !== $equipped) {
                        try {
                            $this->itemService->unequip($equipped);
                        } catch (\Throwable) {
                        }
                    }
                    continue;
                }

                // If currently equipped item matches preferred subtype, keep it
                if (null !== $equipped && $equipped->getSubType() === $prefSubType) {
                    continue;
                }

                // Try to find a matching item in the inventory
                $foundInInventory = false;
                foreach ($unequippedItems as $idx => $item) {
                    if ($item->getSlotType() === $slot && $item->getSubType() === $prefSubType) {
                        try {
                            $this->itemService->equip($item, $hero, $slot);
                            unset($unequippedItems[$idx]);
                            $foundInInventory = true;
                            break;
                        } catch (\Throwable) {
                        }
                    }
                }

                if ($foundInInventory) {
                    continue;
                }

                // Fallback: If no matching item in inventory, check if we should purchase the basic item.
                // Only purchase if nothing is equipped or the equipped item is common (and doesn't match).
                if (null === $equipped || ItemRarity::Common === $equipped->getRarity()) {
                    // Determine the basic item key
                    $itemKey = null;
                    if (\in_array($slot, [ItemSlotType::Head, ItemSlotType::Body, ItemSlotType::Hands, ItemSlotType::Feet], true)) {
                        $itemKey = self::ARMOR_TO_BASIC_ITEM[$prefArmor->value][$slot->value] ?? null;
                    } else {
                        $itemKey = self::SUBTYPE_TO_BASIC_ITEM[$prefSubType->value] ?? null;
                    }

                    if (null !== $itemKey) {
                        $cost = ItemService::BASIC_EQUIPMENT[$itemKey]['cost'];
                        // Keep a minimum reserve of 150 gold so NPC teams don't fully go broke on basic gear
                        if ($team->getGold() - $cost >= 150) {
                            try {
                                $newItem = $this->itemService->purchaseBasicItem($team, $itemKey);
                                $this->itemService->equip($newItem, $hero, $slot);
                            } catch (\Throwable) {
                            }
                        }
                    }
                }
            }
        }

        // Re-filter remaining unequipped items for fallback slots (Rings, Amulets, and leftovers)
        $unequippedItems = array_values($unequippedItems);

        // 2. Fallback Empty-Slot Pass
        foreach ($heroes as $hero) {
            foreach (ItemSlotType::cases() as $slot) {
                // If a two-handed weapon is equipped, we must skip OffHand
                if (ItemSlotType::OffHand === $slot) {
                    $mainHand = $this->em->getRepository(Item::class)->findOneBy([
                        'equippedHero' => $hero,
                        'equippedSlot' => ItemSlotType::MainHand,
                    ]);
                    if (null !== $mainHand && null !== $mainHand->getSubType()) {
                        $twoHandedWeapons = [
                            ItemSubType::TwoHandedSword,
                            ItemSubType::TwoHandedAxe,
                            ItemSubType::TwoHandedMace,
                            ItemSubType::Bow,
                            ItemSubType::Crossbow,
                            ItemSubType::Staff,
                        ];
                        if (\in_array($mainHand->getSubType(), $twoHandedWeapons, true)) {
                            continue;
                        }
                    }
                }

                $equipped = $this->em->getRepository(Item::class)->findOneBy([
                    'equippedHero' => $hero,
                    'equippedSlot' => $slot,
                ]);

                if (null !== $equipped) {
                    continue;
                }

                // Equip the first available item of correct slot type
                foreach ($unequippedItems as $idx => $item) {
                    if ($item->getSlotType() === $slot) {
                        try {
                            $this->itemService->equip($item, $hero, $slot);
                            unset($unequippedItems[$idx]);
                            $unequippedItems = array_values($unequippedItems);
                            break;
                        } catch (\Throwable) {
                        }
                    }
                }
            }
        }
    }

    /**
     * Get preferred weapon sub-type for a hero based on masteries or attributes.
     */
    private function getPreferredWeaponSubType(Hero $hero): ItemSubType
    {
        $weaponSubTypes = [
            ItemSubType::OneHandedSword,
            ItemSubType::TwoHandedSword,
            ItemSubType::OneHandedAxe,
            ItemSubType::TwoHandedAxe,
            ItemSubType::OneHandedMace,
            ItemSubType::TwoHandedMace,
            ItemSubType::Dagger,
            ItemSubType::Bow,
            ItemSubType::Crossbow,
            ItemSubType::Wand,
            ItemSubType::Staff,
        ];

        // Find highest weapon mastery
        $bestMastery = null;
        foreach ($hero->getWeaponMasteries() as $wm) {
            if (\in_array($wm->getStyle(), $weaponSubTypes, true)) {
                if (null === $bestMastery) {
                    $bestMastery = $wm;
                } else {
                    $bestCompare = $bestMastery->getMasteryTier() <=> $wm->getMasteryTier();
                    if (0 === $bestCompare) {
                        $bestCompare = $bestMastery->getXp() <=> $wm->getXp();
                    }
                    if ($bestCompare < 0) {
                        $bestMastery = $wm;
                    }
                }
            }
        }

        if (null !== $bestMastery && ($bestMastery->getMasteryTier() > 1 || $bestMastery->getXp() > 0)) {
            return $bestMastery->getStyle();
        }

        // Default based on stats
        $str = $hero->getStr();
        $dex = $hero->getDex();
        $intel = $hero->getIntel();

        if ($intel > $str && $intel > $dex) {
            return ItemSubType::Staff;
        } elseif ($dex > $str) {
            return ItemSubType::Bow;
        }

        return ItemSubType::OneHandedSword;
    }

    /**
     * Get preferred armor sub-type for a hero based on masteries or attributes.
     */
    private function getPreferredArmorSubType(Hero $hero): ItemSubType
    {
        $armorSubTypes = [
            ItemSubType::LightArmor,
            ItemSubType::MediumArmor,
            ItemSubType::HeavyArmor,
        ];

        // Find highest armor mastery
        $bestMastery = null;
        foreach ($hero->getWeaponMasteries() as $wm) {
            if (\in_array($wm->getStyle(), $armorSubTypes, true)) {
                if (null === $bestMastery) {
                    $bestMastery = $wm;
                } else {
                    $bestCompare = $bestMastery->getMasteryTier() <=> $wm->getMasteryTier();
                    if (0 === $bestCompare) {
                        $bestCompare = $bestMastery->getXp() <=> $wm->getXp();
                    }
                    if ($bestCompare < 0) {
                        $bestMastery = $wm;
                    }
                }
            }
        }

        if (null !== $bestMastery && ($bestMastery->getMasteryTier() > 1 || $bestMastery->getXp() > 0)) {
            return $bestMastery->getStyle();
        }

        // Default based on stats
        $str = $hero->getStr();
        $dex = $hero->getDex();
        $kon = $hero->getKon();
        $intel = $hero->getIntel();

        if ($str + $kon > $intel + $dex) {
            return ItemSubType::HeavyArmor;
        } elseif ($dex > $intel) {
            return ItemSubType::MediumArmor;
        }

        return ItemSubType::LightArmor;
    }

    /**
     * Get preferred off-hand sub-type for a hero.
     */
    private function getPreferredOffHandSubType(Hero $hero, ItemSubType $preferredWeapon): ?ItemSubType
    {
        $twoHandedWeapons = [
            ItemSubType::TwoHandedSword,
            ItemSubType::TwoHandedAxe,
            ItemSubType::TwoHandedMace,
            ItemSubType::Bow,
            ItemSubType::Crossbow,
            ItemSubType::Staff,
        ];

        if (\in_array($preferredWeapon, $twoHandedWeapons, true)) {
            return null;
        }

        if (ItemSubType::Wand === $preferredWeapon || ($hero->getIntel() > $hero->getStr() && $hero->getIntel() > $hero->getDex())) {
            return ItemSubType::SpellAccelerator;
        }

        return ItemSubType::Shield;
    }

    /**
     * @return array<string, string>
     */
    private function getStrategyForSlot(string $role, FormationPosition $position): array
    {
        // Simple heuristic strategy based on role
        if (NpcSimulationService::ROLE_MERCENARY_ACADEMY === $role) {
            return ['target' => 'weakest', 'action' => 'attack', 'aggression' => 'high'];
        } elseif (NpcSimulationService::ROLE_VETERAN_GUILD === $role) {
            return ['target' => 'frontline', 'action' => 'defend', 'aggression' => 'low'];
        }

        return ['target' => 'balanced', 'action' => 'balanced', 'aggression' => 'medium'];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpellPrioritiesForSlot(string $role, FormationPosition $position): array
    {
        if (NpcSimulationService::ROLE_ROYAL_COLLECTOR === $role) {
            return ['heal_trigger_pct' => 50, 'prefer_magic' => true];
        }

        return ['heal_trigger_pct' => 30, 'prefer_magic' => false];
    }

    private function getApproachForRole(string $role): FormationApproach
    {
        return match ($role) {
            NpcSimulationService::ROLE_MERCENARY_ACADEMY => FormationApproach::Aggressive,
            NpcSimulationService::ROLE_VETERAN_GUILD => FormationApproach::Defensive,
            default => FormationApproach::Balanced,
        };
    }

    private function getRarityValue(ItemRarity $rarity): int
    {
        return match ($rarity) {
            ItemRarity::Common => 1,
            ItemRarity::Uncommon => 2,
            ItemRarity::Rare => 3,
            ItemRarity::Epic => 4,
            ItemRarity::Legendary => 5,
            ItemRarity::Mythic => 6,
        };
    }

    /**
     * @param array<Hero>                    $activeLineup
     * @param array<int, array<string, int>> $lineupGear
     */
    public function isItemUsefulForLineup(array $activeLineup, Item $item, array &$lineupGear): ?Hero
    {
        $slot = $item->getSlotType();
        $subType = $item->getSubType();
        $rarityVal = $this->getRarityValue($item->getRarity());

        foreach ($activeLineup as $hero) {
            $heroId = $hero->getId();
            if (null === $heroId) {
                continue;
            }

            if (\in_array($slot, [ItemSlotType::MainHand, ItemSlotType::OffHand, ItemSlotType::Head, ItemSlotType::Body, ItemSlotType::Hands, ItemSlotType::Feet], true)) {
                $prefWeapon = $this->getPreferredWeaponSubType($hero);
                $prefArmor = $this->getPreferredArmorSubType($hero);
                $prefOffHand = $this->getPreferredOffHandSubType($hero, $prefWeapon);

                if (ItemSlotType::OffHand === $slot && null === $prefOffHand) {
                    continue;
                }

                $prefSubType = match ($slot) {
                    ItemSlotType::MainHand => $prefWeapon,
                    ItemSlotType::OffHand => $prefOffHand,
                    default => $prefArmor,
                };

                if (null !== $prefSubType && $subType !== $prefSubType) {
                    continue;
                }
            }

            $currentRarityVal = $lineupGear[$heroId][$slot->value] ?? 0;

            if ($currentRarityVal < $rarityVal) {
                return $hero;
            }
        }

        return null;
    }
}
