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
     * Computed on Summoning Chamber but unread by HeroGenerator (A6.5).
     * Live total cap is 60% of per-stat ceilings. Do not show as an HQ bonus.
     */
    public const UNREAD_PASSIVE_BONUS_KEYS = ['summon_stat_total_cap'];

    /**
     * Get the passive bonuses for this facility type at a given level.
     *
     * @return array<string, float>
     */
    public function getPassiveBonuses(int $level): array
    {
        $bonuses = match ($this) {
            self::Training => ['training_efficiency_pct' => 3.0],
            self::Medical => ['fatigue_reduction_pct' => 4.0, 'recovery_speed_pct' => 3.0],
            self::Library => ['xp_gain_pct' => 3.0],
            self::Treasury => ['gold_income_pct' => 3.0],
            self::Barracks => ['roster_capacity' => 2.0],
            self::SummoningChamber => ['summon_base_stat_bonus' => 0.4, 'summon_stat_random_bonus' => 1.0, 'summon_stat_total_cap' => 7.0],
            self::Arena => ['ticket_revenue_pct' => 3.0, 'arena_capacity' => 10.0],
        };

        $result = [];
        foreach ($bonuses as $key => $valuePerLevel) {
            $result[$key] = round($valuePerLevel * $level, 2);
        }

        return $result;
    }

    public static function isUnreadPassiveBonus(string $key): bool
    {
        return in_array($key, self::UNREAD_PASSIVE_BONUS_KEYS, true);
    }

    /**
     * Strip reserved keys so HQ cards and API `passive_bonuses` only show live effects.
     *
     * @param array<string, float|int> $bonuses
     *
     * @return array<string, float|int>
     */
    public static function withoutUnreadBonuses(array $bonuses): array
    {
        foreach (self::UNREAD_PASSIVE_BONUS_KEYS as $key) {
            unset($bonuses[$key]);
        }

        return $bonuses;
    }
}
