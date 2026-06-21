<?php

declare(strict_types=1);

namespace App\Enum;

enum FacilityType: string
{
    case Training = 'training';
    case Medical = 'medical';
    case Library = 'library';
    case Treasury = 'treasury';
    case Barracks = 'barracks';
    case SummoningChamber = 'summoning_chamber';
    case Arena = 'arena';

    /**
     * Get the passive bonuses for this facility type at a given level.
     *
     * @return array<string, float>
     */
    public function getPassiveBonuses(int $level): array
    {
        $bonuses = match ($this) {
            self::Training => ['training_efficiency_pct' => 5.0],
            self::Medical => ['fatigue_reduction_pct' => 8.0, 'recovery_speed_pct' => 5.0],
            self::Library => ['xp_gain_pct' => 4.0],
            self::Treasury => ['gold_income_pct' => 4.0],
            self::Barracks => ['roster_capacity' => 2.0],
            self::SummoningChamber => ['summon_base_stat_bonus' => 0.4, 'summon_stat_random_bonus' => 1.0, 'summon_stat_total_cap' => 7.0],
            self::Arena => ['ticket_revenue_pct' => 6.0, 'arena_capacity' => 10.0],
        };

        $result = [];
        foreach ($bonuses as $key => $valuePerLevel) {
            $result[$key] = round($valuePerLevel * $level, 2);
        }

        return $result;
    }
}
