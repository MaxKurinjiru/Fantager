<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Config\HeroRatingConfig;
use App\Entity\Hero\Hero;
use App\Enum\CombatStatProfile;
use App\Service\Combat\CombatStatCalculator;
use App\Service\Config\RaceConfig;
use App\ValueObject\Combat\DerivedCombatStats;
use App\ValueObject\Hero\HeroRating;

class HeroRatingCalculator
{
    public function __construct(
        private readonly CombatStatCalculator $combatStatCalculator,
        private readonly HeroRatingConfig $heroRatingConfig,
        private readonly RaceConfig $raceConfig,
    ) {
    }

    public function calculate(Hero $hero): HeroRating
    {
        $humanNeutralStats = $this->combatStatCalculator->calculateForProfile(
            $hero,
            CombatStatProfile::HumanNeutral,
        );
        $hybridForOvr = $this->computeHybridScore($humanNeutralStats, $hero->getCha(), $hero->getWil());
        $baseOvr = $this->scaleToRange($hybridForOvr, $this->heroRatingConfig->getBaseOvrMax());

        $intrinsicStats = $this->combatStatCalculator->calculateForProfile(
            $hero,
            CombatStatProfile::FullIntrinsic,
        );
        $hybridForComplex = $this->computeHybridScore($intrinsicStats, $hero->getCha(), $hero->getWil());
        $complexBase = $this->scaleToRange($hybridForComplex, $this->heroRatingConfig->getComplexRatingMax());

        $traitBonus = $this->heroRatingConfig->getTraitBonus($hero->getTrait());
        $masteryBonus = $hero->isTrainer() ? 0 : $this->computeMasteryBonus($hero);

        $agePhase = $this->raceConfig->resolveAgePhase($hero->getRace(), $hero->getAge());
        $ageMultiplier = $this->heroRatingConfig->getAgeMultiplier($agePhase);

        $complexRating = (int) round(
            ($complexBase + $traitBonus + $masteryBonus) * $ageMultiplier
        );
        $complexRating = max(0, min($this->heroRatingConfig->getComplexRatingMax(), $complexRating));

        return new HeroRating(
            baseOvr: max(0, min($this->heroRatingConfig->getBaseOvrMax(), $baseOvr)),
            complexRating: $complexRating,
        );
    }

    public function estimateGoldValue(Hero $hero): int
    {
        $rating = $this->calculate($hero);

        return (int) round($rating->getComplexRating() * $this->heroRatingConfig->getGoldPerComplexPoint());
    }

    public function estimateMarketPrice(Hero $hero): int
    {
        $goldValue = $this->estimateGoldValue($hero);

        if ($hero->isTrainer()) {
            return (int) round($goldValue * $this->heroRatingConfig->getTrainerMarketMultiplier());
        }

        return $goldValue;
    }

    private function computeHybridScore(DerivedCombatStats $stats, int $cha, int $wil): float
    {
        $ceilings = $this->heroRatingConfig->getReferenceCeilings();
        $physicalWeights = $this->heroRatingConfig->getPhysicalWeights();
        $magicWeights = $this->heroRatingConfig->getMagicWeights();

        $physicalScore = 0.0;
        $physicalScore += $this->weightedContribution('max_hp', $stats->getMaxHp(), $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution('physical_attack', $stats->getPhysicalAttack(), $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution('armor_value', $stats->getArmorValue(), $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution('base_initiative', $stats->getBaseInitiative(), $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution('accuracy_percent', $stats->getAccuracyPercent(), $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution('dodge_percent', $stats->getDodgePercent(), $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution('crit_percent', $stats->getCritPercent(), $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution('cha', $cha, $ceilings, $physicalWeights);
        $physicalScore += $this->weightedContribution(
            'physical_damage_reduction',
            $stats->getPhysicalDamageReduction(),
            $ceilings,
            $physicalWeights,
        );

        $magicScore = 0.0;
        $magicScore += $this->weightedContribution('spell_power', $stats->getSpellPower(), $ceilings, $magicWeights);
        $magicScore += $this->weightedContribution('magic_resistance', $stats->getMagicResistance(), $ceilings, $magicWeights);
        $magicScore += $this->weightedContribution('cha', $cha, $ceilings, $magicWeights);
        $magicScore += $this->weightedContribution('wil', $wil, $ceilings, $magicWeights);
        $magicScore += $this->weightedContribution(
            'magic_damage_reduction',
            $stats->getMagicDamageReduction(),
            $ceilings,
            $magicWeights,
        );

        return ($physicalScore + $magicScore) / 2.0;
    }

    /**
     * @param array<string, float> $ceilings
     * @param array<string, float> $weights
     */
    private function weightedContribution(
        string $key,
        float|int $value,
        array $ceilings,
        array $weights,
    ): float {
        $weight = $weights[$key] ?? 0.0;
        if ($weight <= 0.0) {
            return 0.0;
        }

        $ceiling = $ceilings[$key] ?? 1.0;
        if ($ceiling <= 0.0) {
            return 0.0;
        }

        $normalized = min(1.0, max(0.0, (float) $value / $ceiling));

        return $normalized * $weight;
    }

    private function scaleToRange(float $hybridScore, int $max): int
    {
        return (int) round(min(1.0, max(0.0, $hybridScore)) * $max);
    }

    private function computeMasteryBonus(Hero $hero): int
    {
        $bonus = 0;
        $pointsPerTier = $this->heroRatingConfig->getMasteryPointsPerTier();

        foreach ($hero->getWeaponMasteries() as $mastery) {
            $tier = $mastery->getMasteryTier();
            if ($tier > 1) {
                $bonus += ($tier - 1) * $pointsPerTier;
            }
        }

        foreach ($hero->getSchoolMasteries() as $mastery) {
            $tier = $mastery->getMasteryTier();
            if ($tier > 1) {
                $bonus += ($tier - 1) * $pointsPerTier;
            }
        }

        return $bonus;
    }
}
