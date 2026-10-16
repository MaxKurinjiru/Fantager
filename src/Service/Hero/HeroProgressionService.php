<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Config\HeroProgressionConfig;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;

class HeroProgressionService
{
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
     * Live writer: raise stored level to match current XP + cap. Does not lower.
     */
    public function syncFromXp(Hero $hero, Team $team): void
    {
        $computed = $this->config->levelForXp($hero->getXp(), $team->getKingdom()->getLevelCap());
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
        $computed = $this->config->levelForXp($hero->getXp(), $team->getKingdom()->getLevelCap());
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
        return $this->config->xpBar(
            $hero->getLevel(),
            $hero->getXp(),
            $hero->getTeam()->getKingdom()->getLevelCap(),
        );
    }
}
