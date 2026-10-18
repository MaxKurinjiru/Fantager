<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Enum\HeroTrait;

/**
 * Post-match knockout aging, mortality chance, and the short recovery bruise.
 * Permanent death still starts only at the race mortality threshold.
 */
final class KnockoutOutcome
{
    public const PARTICIPATION_FATIGUE = 15;
    public const PARTICIPATION_FORM_LOSS = 8;
    /** Extra fatigue on top of participation. About three daily recovery ticks at pace 1. */
    public const KNOCKOUT_EXTRA_FATIGUE = 30;
    /** Extra form loss on top of participation. About three daily recovery ticks at pace 1. */
    public const KNOCKOUT_EXTRA_FORM_LOSS = 15;

    public function yearsAdded(int $deathsInMatch, ?HeroTrait $trait): int
    {
        if ($deathsInMatch <= 0) {
            return 0;
        }

        $years = (int) ($deathsInMatch * ($deathsInMatch + 1) / 2);
        if (HeroTrait::BattleHardened === $trait) {
            return intdiv($years, 2);
        }

        return $years;
    }

    public function permanentDeathChance(int $yearsAboveThreshold, ?HeroTrait $trait): float
    {
        $chance = 0.05 + (0.04 * max(0, $yearsAboveThreshold));
        if (HeroTrait::Volatile === $trait) {
            $chance += 0.10;
        }

        return min(1.0, $chance);
    }
}
