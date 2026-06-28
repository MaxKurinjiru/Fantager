<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\League\LeagueFixture;
use App\ValueObject\Combat\MatchOutcome;

/**
 * Placeholder combat engine: random kill scores (0–6) until the real simulator ships.
 *
 * TODO [Combat Engine — Trait Hooks]:
 * When the real engine ships, apply the following DerivedCombatStats values per hero turn:
 *
 *  - getCritDamageMultiplier()     — multiply crit damage by this value instead of fixed 1.5×
 *                                    (Berserker = 2.0× crit damage)
 *  - getMoraleDecayMultiplier()    — apply on ally-death morale change event
 *                                    (BattleHardened = 0.5×, Volatile = 2.0×)
 *  - getClutchHpThreshold()        — if currentHp / maxHp <= threshold, activate clutch bonuses:
 *                                      accuracyPercent += getClutchAccuracyBonus()
 *                                      armorValue      *= getClutchArmorMultiplier()
 *                                    (Clutch trait: threshold 0.30, +15 % acc, +10 % armor)
 *  - getGlassJawHpThreshold()      — if currentHp / maxHp <= threshold, apply incoming penalty:
 *                                      incomingPhysicalDamage *= getIncomingDamageMultiplier()
 *                                    (GlassJaw trait: threshold 0.50, ×1.10 incoming damage)
 *  - isConsistentDamage()          — collapse damage range to mid-point instead of random roll
 *                                    (Perfectionist trait: no RNG variance on damage)
 *  - ignoresRaceSynergy()          — skip race relationship matrix for this hero entirely
 *                                    (Loner trait: no positive or negative synergy bonuses)
 *  - getArenaRevenueBonus()        — apply in ArenaRevenueService, not in the combat engine
 *                                    (AudienceFavorite trait: +5 % ticket revenue when fielded)
 */
class StubRandomMatchSimulator implements MatchSimulatorInterface
{
    public function simulate(LeagueFixture $fixture): MatchOutcome
    {
        return new MatchOutcome(
            random_int(0, 6),
            random_int(0, 6),
        );
    }
}
