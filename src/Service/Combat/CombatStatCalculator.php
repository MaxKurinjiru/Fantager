<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Enum\CombatStatProfile;
use App\Enum\HeroTrait;
use App\Enum\ItemSubType;
use App\Enum\Race;
use App\Repository\Item\ItemRepository;
use App\Service\Hero\HeroMasteryService;
use App\ValueObject\Combat\DerivedCombatStats;

class CombatStatCalculator
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly HeroMasteryService $heroMasteryService,
    ) {
    }

    public function calculate(Hero $hero): DerivedCombatStats
    {
        return $this->calculateForProfile($hero, CombatStatProfile::Equipped);
    }

    public function calculateForProfile(Hero $hero, CombatStatProfile $profile): DerivedCombatStats
    {
        $applyItems = CombatStatProfile::Equipped === $profile;
        $applyTrait = CombatStatProfile::Equipped === $profile;
        $applyEquipMastery = CombatStatProfile::Equipped === $profile;

        $race = match ($profile) {
            CombatStatProfile::HumanNeutral => Race::Human,
            default => $hero->getRace(),
        };

        $formPercent = CombatStatProfile::Equipped === $profile ? $hero->getForm() : 100;
        $trait = $applyTrait ? $hero->getTrait() : null;

        $bonusStr = 0.0;
        $bonusDex = 0.0;
        $bonusKon = 0.0;
        $bonusSpd = 0.0;
        $bonusIntel = 0.0;
        $bonusWil = 0.0;
        $bonusLck = 0.0;

        $itemArmor = 0.0;
        $itemWeaponDmg = 0.0;
        $itemResistance = 0.0;
        $itemSpellAccelerator = 0.0;
        $isRangedWeapon = false;

        if ($applyItems) {
            /** @var list<Item> $equippedItems */
            $equippedItems = $this->itemRepository->findBy(['equippedHero' => $hero]);

            foreach ($equippedItems as $item) {
                $factor = $item->getDurability() / 100.0;
                if ($factor <= 0.0) {
                    continue;
                }

                $bonuses = $item->getBonuses();

                $bonusStr += ($bonuses['str'] ?? 0) * $factor;
                $bonusDex += ($bonuses['dex'] ?? 0) * $factor;
                $bonusKon += ($bonuses['kon'] ?? 0) * $factor;
                $bonusSpd += ($bonuses['spd'] ?? 0) * $factor;
                $bonusIntel += ($bonuses['intel'] ?? $bonuses['int'] ?? 0) * $factor;
                $bonusWil += ($bonuses['wil'] ?? 0) * $factor;
                $bonusLck += ($bonuses['lck'] ?? 0) * $factor;

                $itemArmor += ($bonuses['armor'] ?? 0) * $factor;
                $itemWeaponDmg += ($bonuses['damage'] ?? 0) * $factor;
                $itemResistance += ($bonuses['resistance'] ?? 0) * $factor;
                $itemSpellAccelerator += ($bonuses['spell_power'] ?? 0) * $factor;

                if (($bonuses['type'] ?? '') === 'ranged') {
                    $isRangedWeapon = true;
                }
            }
        }

        $physAttackMult = 1.0;
        $spellPowerMult = 1.0;
        $armorMult = 1.0;
        $initMult = 1.0;
        $magicResMult = 1.0;
        $critAdder = 0.0;
        $accAdder = 0.0;
        $dodgeAdder = 0.0;

        if ($applyEquipMastery) {
            $equippedSubTypes = $this->heroMasteryService->getEquippedSubTypes($hero);
            foreach ($equippedSubTypes as $subType) {
                $wm = null;
                foreach ($hero->getWeaponMasteries() as $mastery) {
                    if ($mastery->getStyle() === $subType) {
                        $wm = $mastery;
                        break;
                    }
                }
                if (null !== $wm && 100 === $wm->getAttunementProgress()) {
                    $tier = $wm->getMasteryTier();
                    if ($tier > 1) {
                        $tierFactor = $tier - 1;
                        $this->applyWeaponMasteryBonuses(
                            $subType,
                            $tierFactor,
                            $physAttackMult,
                            $spellPowerMult,
                            $armorMult,
                            $initMult,
                            $magicResMult,
                            $critAdder,
                            $accAdder,
                            $dodgeAdder,
                        );
                    }
                }
            }

            $equippedSchools = $this->heroMasteryService->getEquippedSpellSchools($hero);
            foreach ($equippedSchools as $school) {
                $sm = null;
                foreach ($hero->getSchoolMasteries() as $mastery) {
                    if ($mastery->getSchool() === $school) {
                        $sm = $mastery;
                        break;
                    }
                }
                if (null !== $sm) {
                    $tier = $sm->getMasteryTier();
                    if ($tier > 1) {
                        $spellPowerMult += ($tier - 1) * 0.05;
                    }
                }
            }
        }

        $str = max(1.0, $hero->getStr() + $bonusStr);
        $dex = max(1.0, $hero->getDex() + $bonusDex);
        $kon = max(1.0, $hero->getKon() + $bonusKon);
        $spd = max(1.0, $hero->getSpd() + $bonusSpd);
        $intel = max(1.0, $hero->getIntel() + $bonusIntel);
        $wil = max(1.0, $hero->getWil() + $bonusWil);
        $lck = max(1.0, $hero->getLck() + $bonusLck);

        return $this->computeDerivedStats(
            str: $str,
            dex: $dex,
            kon: $kon,
            spd: $spd,
            intel: $intel,
            wil: $wil,
            lck: $lck,
            level: $hero->getLevel(),
            formPercent: $formPercent,
            race: $race,
            trait: $trait,
            itemArmor: $itemArmor,
            itemWeaponDmg: $itemWeaponDmg,
            itemResistance: $itemResistance,
            itemSpellAccelerator: $itemSpellAccelerator,
            isRangedWeapon: $isRangedWeapon,
            physAttackMult: $physAttackMult,
            spellPowerMult: $spellPowerMult,
            armorMult: $armorMult,
            initMult: $initMult,
            magicResMult: $magicResMult,
            critAdder: $critAdder,
            accAdder: $accAdder,
            dodgeAdder: $dodgeAdder,
        );
    }

    private function computeDerivedStats(
        float $str,
        float $dex,
        float $kon,
        float $spd,
        float $intel,
        float $wil,
        float $lck,
        int $level,
        int $formPercent,
        Race $race,
        ?HeroTrait $trait,
        float $itemArmor,
        float $itemWeaponDmg,
        float $itemResistance,
        float $itemSpellAccelerator,
        bool $isRangedWeapon,
        float $physAttackMult,
        float $spellPowerMult,
        float $armorMult,
        float $initMult,
        float $magicResMult,
        float $critAdder,
        float $accAdder,
        float $dodgeAdder,
    ): DerivedCombatStats {
        $raceValue = $race->value;
        $isEnt = ('ent' === $raceValue);
        $isElf = ('elf' === $raceValue);
        $isDwarf = ('dwarf' === $raceValue);
        $isOrc = ('orc' === $raceValue);
        $isGiant = ('giant' === $raceValue);
        $isGenie = ('genie' === $raceValue);

        $konFactor = $isEnt ? 1.2 : 1.0;

        $maxHp = (int) round(($level * 30) + ($kon * 12 * $konFactor));
        $currentHp = (int) round($maxHp * ($formPercent / 100.0));

        if (0.0 === $itemWeaponDmg) {
            $physicalAttackVal = $str * 2.0;
        } else {
            $scalingStat = $isRangedWeapon ? $dex : $str;
            $scalingFactor = 1.0 + ($scalingStat / 15.0);
            $physicalAttackVal = $itemWeaponDmg * $scalingFactor;
        }
        if ($isGiant && $itemWeaponDmg > 0) {
            $physicalAttackVal *= 1.10;
        }
        $physicalAttackVal *= $physAttackMult;
        $physicalAttack = (int) round($physicalAttackVal);

        $spellPowerVal = $intel * 3.0 + $itemSpellAccelerator;
        if ($isGenie) {
            $spellPowerVal *= 1.15;
        }
        $spellPowerVal *= $spellPowerMult;
        $spellPower = (int) round($spellPowerVal);

        $armorFactor = $isDwarf ? 1.15 : 1.0;
        $armorValueVal = ($itemArmor + ($kon * 1.5)) * $armorFactor * $armorMult;
        $armorValue = (int) round($armorValueVal);
        $physDamageReduction = $armorValue / ($armorValue + 100.0);

        $magicResistanceVal = ($itemResistance + ($wil * 2.0)) * $magicResMult;
        $magicResistance = (int) round($magicResistanceVal);
        $magicDamageReduction = $magicResistance / ($magicResistance + 100.0);

        $initiativeVal = $spd * 2.0;
        if ($isEnt) {
            $initiativeVal *= 0.80;
        }
        $initiativeVal *= $initMult;
        $baseInitiative = (int) round($initiativeVal);

        $accBonus = $isElf ? 10.0 : 0.0;
        $accuracyPercent = 80.0 + $dex * 1.0 + $lck * 0.5 + $accBonus + $accAdder;

        $dodgeBonus = $isElf ? 10.0 : 0.0;
        $dodgePercent = (($dex + $spd) * 0.75) + ($lck * 0.25) + $dodgeBonus + $dodgeAdder;
        if ($dodgePercent > 50.0) {
            $dodgePercent = 50.0;
        }

        $critBonus = $isGenie ? 5.0 : 0.0;
        $critPercent = 5.0 + $lck * 1.0 + $dex * 0.25 + $critBonus + $critAdder;
        if ($critPercent > 50.0) {
            $critPercent = 50.0;
        }

        if (null !== $trait) {
            $maxHp = (int) round($maxHp * $trait->getHpMultiplier());
            $currentHp = (int) round($maxHp * ($formPercent / 100.0));
            $physicalAttack = (int) round($physicalAttack * $trait->getPhysAttackMultiplier());
            $spellPower = (int) round($spellPower * $trait->getSpellPowerMultiplier());
            $armorValue = (int) round($armorValue * $trait->getArmorMultiplier());

            $accuracyPercent += $trait->getAccuracyBonus();
            $critPercent = min(50.0, $critPercent + $trait->getCritBonus());
            $dodgePercent = min(50.0, $dodgePercent + $trait->getDodgeBonus());

            $physDamageReduction = $armorValue / ($armorValue + 100.0);
        }

        return new DerivedCombatStats(
            maxHp: $maxHp,
            currentHp: $currentHp,
            physicalAttack: $physicalAttack,
            spellPower: $spellPower,
            armorValue: $armorValue,
            physicalDamageReduction: $physDamageReduction,
            magicResistance: $magicResistance,
            magicDamageReduction: $magicDamageReduction,
            baseInitiative: $baseInitiative,
            accuracyPercent: $accuracyPercent,
            dodgePercent: $dodgePercent,
            critPercent: $critPercent,
            critDamageMultiplier: $trait?->getCritDamageMultiplier() ?? 1.5,
            moraleDecayMultiplier: $trait?->getMoraleDecayMultiplier() ?? 1.0,
            arenaRevenueBonus: $trait?->getArenaRevenueBonus() ?? 0.0,
            clutchHpThreshold: $trait?->getClutchHpThreshold(),
            clutchAccuracyBonus: $trait?->getClutchAccuracyBonus() ?? 0.0,
            clutchArmorMultiplier: $trait?->getClutchArmorMultiplier() ?? 1.0,
            glassJawHpThreshold: $trait?->getGlassJawHpThreshold(),
            incomingDamageMultiplier: $trait?->getIncomingDamageMultiplier() ?? 1.0,
            isConsistentDamage: $trait?->isConsistentDamage() ?? false,
            ignoresRaceSynergy: $trait?->ignoresRaceSynergy() ?? false,
        );
    }

    private function applyWeaponMasteryBonuses(
        ItemSubType $subType,
        int $tierFactor,
        float &$physAttackMult,
        float &$spellPowerMult,
        float &$armorMult,
        float &$initMult,
        float &$magicResMult,
        float &$critAdder,
        float &$accAdder,
        float &$dodgeAdder,
    ): void {
        switch ($subType) {
            case ItemSubType::OneHandedSword:
                $physAttackMult += $tierFactor * 0.05;
                break;
            case ItemSubType::TwoHandedSword:
                $physAttackMult += $tierFactor * 0.06;
                break;
            case ItemSubType::OneHandedAxe:
                $physAttackMult += $tierFactor * 0.05;
                $critAdder += $tierFactor * 0.5;
                break;
            case ItemSubType::TwoHandedAxe:
                $physAttackMult += $tierFactor * 0.06;
                $critAdder += $tierFactor * 1.0;
                break;
            case ItemSubType::OneHandedMace:
                $physAttackMult += $tierFactor * 0.05;
                $armorMult += $tierFactor * 0.01;
                break;
            case ItemSubType::TwoHandedMace:
                $physAttackMult += $tierFactor * 0.06;
                $armorMult += $tierFactor * 0.02;
                break;
            case ItemSubType::Dagger:
                $physAttackMult += $tierFactor * 0.04;
                $critAdder += $tierFactor * 2.0;
                break;
            case ItemSubType::Bow:
                $physAttackMult += $tierFactor * 0.05;
                $accAdder += $tierFactor * 2.0;
                break;
            case ItemSubType::Crossbow:
                $physAttackMult += $tierFactor * 0.06;
                $accAdder += $tierFactor * 1.0;
                break;
            case ItemSubType::Wand:
                $spellPowerMult += $tierFactor * 0.04;
                $initMult += $tierFactor * 0.01;
                break;
            case ItemSubType::Staff:
                $spellPowerMult += $tierFactor * 0.05;
                $initMult += $tierFactor * 0.02;
                break;
            case ItemSubType::Shield:
                $armorMult += $tierFactor * 0.05;
                $dodgeAdder += $tierFactor * 2.0;
                break;
            case ItemSubType::SpellAccelerator:
                $spellPowerMult += $tierFactor * 0.05;
                $initMult += $tierFactor * 0.01;
                break;
            case ItemSubType::LightArmor:
                $dodgeAdder += $tierFactor * 2.0;
                $initMult += $tierFactor * 0.01;
                break;
            case ItemSubType::MediumArmor:
                $armorMult += $tierFactor * 0.03;
                $dodgeAdder += $tierFactor * 1.0;
                break;
            case ItemSubType::HeavyArmor:
                $armorMult += $tierFactor * 0.05;
                $magicResMult += $tierFactor * 0.01;
                break;
        }
    }
}
