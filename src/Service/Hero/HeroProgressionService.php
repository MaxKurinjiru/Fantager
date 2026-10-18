<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Config\HeroProgressionConfig;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;

class HeroProgressionService
{
    public const PARTICIPATION_XP = 20;
    /** Weekly training participation. 40% of a match appearance, before library and kingdom modifiers. */
    public const TRAINING_PARTICIPATION_XP = 8;
    public const WIN_XP = 15;
    public const KO_XP = 10;
    /** One XP for each HP_PER_XP of damage, healing, or credited status. */
    public const XP_PER_HP_STEP = 1;
    public const HP_PER_XP = 10;
    public const UTILITY_CAST_XP = 2;
    /** Legacy logs stored thousandths of a health bar before XP scaled with raw HP. */
    public const BAR_MILLIS = 1000;

    public function __construct(
        private readonly HeroProgressionConfig $config,
        private readonly HeroService $heroService,
    ) {
    }

    public function getConfig(): HeroProgressionConfig
    {
        return $this->config;
    }

    /**
     * Base match XP before library and kingdom multipliers.
     *
     * Damage and healing are HP actually removed or restored. Each 10 HP is 1 XP,
     * so a larger health bar pays more. Status HP is half the
     * effect's listed full duration, credited on every successful cast including
     * a refresh. A KO pays 10 XP times the victim's level (`ko_levels`). A
     * non-tick status pays 2 XP times the target's level (`status_levels`).
     * A missing contribution map awards nothing extra. Logs that only have a
     * kill count or `status_applies` treat each one as level 1. Logs that only
     * have bar millis keep the older per-bar rate.
     *
     * @param array<string, mixed> $contribution
     */
    public function matchBaseXp(bool $won, int $kills, array $contribution = []): int
    {
        $utilityCasts = max(0, (int) ($contribution['utility_casts'] ?? 0));
        $statusLevels = array_key_exists('status_levels', $contribution)
            ? max(0, (int) $contribution['status_levels'])
            : max(0, (int) ($contribution['status_applies'] ?? 0));
        $koLevels = array_key_exists('ko_levels', $contribution)
            ? max(0, (int) $contribution['ko_levels'])
            : max(0, $kills);
        $usesHp = array_key_exists('damage_hp', $contribution)
            || array_key_exists('heal_hp', $contribution)
            || array_key_exists('status_hp', $contribution);

        if ($usesHp) {
            $fromActions = $this->hpXp(max(0, (int) ($contribution['damage_hp'] ?? 0)))
                + $this->hpXp(max(0, (int) ($contribution['heal_hp'] ?? 0)))
                + $this->hpXp(max(0, (int) ($contribution['status_hp'] ?? 0)));
        } else {
            $fromActions = $this->barXp(max(0, (int) ($contribution['damage_millis'] ?? 0)))
                + $this->barXp(max(0, (int) ($contribution['heal_millis'] ?? 0)));
        }

        return self::PARTICIPATION_XP
            + ($won ? self::WIN_XP : 0)
            + (self::KO_XP * $koLevels)
            + $fromActions
            + (self::UTILITY_CAST_XP * $utilityCasts)
            + (self::UTILITY_CAST_XP * $statusLevels);
    }

    /**
     * Award match XP (library multiplier already computed) and raise stored level if the current curve says so.
     * Never subtracts XP. Never lowers stored level (formula/cap drops are the refresh job).
     */
    public function applyMatchXp(Hero $hero, Team $team, int $baseXp, float $libraryMultiplier): int
    {
        $modifier = (float) $team->getKingdom()->getXpModifier();
        $xp = (int) round($baseXp * $libraryMultiplier * $modifier);
        if ($xp > 0) {
            $hero->setXp($hero->getXp() + $xp);
            $this->syncFromXp($hero, $team);
        }

        return max(0, $xp);
    }

    /**
     * Bench progression for a completed weekly training. Same curve, cap, and multipliers as match XP.
     */
    public function applyTrainingParticipation(Hero $hero, Team $team, float $libraryMultiplier): int
    {
        return $this->applyMatchXp($hero, $team, self::TRAINING_PARTICIPATION_XP, $libraryMultiplier);
    }

    /**
     * Live writer: raise stored level to match current XP + cap. Does not lower.
     */
    public function syncFromXp(Hero $hero, Team $team): void
    {
        $computed = $this->config->levelForXp($hero->getXp());
        $current = $hero->getLevel();
        if ($computed > $current) {
            $this->heroService->levelUp($hero, $computed - $current);
        }
    }

    /**
     * Job / migration writer: set stored level from XP (may go up or down). No chronicle.
     */
    public function recalcStoredLevel(Hero $hero, Team $team): bool
    {
        $computed = $this->config->levelForXp($hero->getXp());
        if ($computed === $hero->getLevel()) {
            return false;
        }

        $hero->setLevel($computed);

        return true;
    }

    /**
     * XP bar for the *stored* level. Does not recompute level from XP.
     *
     * @return array{xp_for_level: int, xp_for_next_level: int, at_cap: bool, progress_percent: float}
     */
    public function xpBar(Hero $hero): array
    {
        return $this->config->xpBar($hero->getLevel(), $hero->getXp());
    }

    private function barXp(int $millis): int
    {
        return (int) round($millis * 10 / self::BAR_MILLIS);
    }

    private function hpXp(int $hp): int
    {
        return (int) round($hp * self::XP_PER_HP_STEP / self::HP_PER_XP);
    }
}
