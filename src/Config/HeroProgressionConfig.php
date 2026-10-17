<?php

declare(strict_types=1);

namespace App\Config;

use Symfony\Component\Yaml\Yaml;

class HeroProgressionConfig
{
    /** Highest stored hero level. Not a kingdom setting. */
    public const LEVEL_CAP = 20;

    private int $coefficient;

    public function __construct(string $projectDir)
    {
        /** @var array<string, mixed> $parsed */
        $parsed = Yaml::parseFile($projectDir.'/config/game/hero-progression.yaml');
        $curve = $parsed['curve'] ?? [];
        $this->coefficient = max(1, (int) ($curve['coefficient'] ?? 400));
    }

    public function getCoefficient(): int
    {
        return $this->coefficient;
    }

    public function getLevelCap(): int
    {
        return self::LEVEL_CAP;
    }

    /**
     * Minimum lifetime XP required to *be* this stored level.
     */
    public function cumulativeXpForLevel(int $level): int
    {
        $level = max(1, $level);
        if (1 === $level) {
            return 0;
        }

        return $this->coefficient * intdiv($level * ($level + 1), 2) - $this->coefficient;
    }

    public function levelForXp(int $xp): int
    {
        $cap = self::LEVEL_CAP;
        $xp = max(0, $xp);
        $level = 1;
        while ($level < $cap && $xp >= $this->cumulativeXpForLevel($level + 1)) {
            ++$level;
        }

        return $level;
    }

    /**
     * Progress inside the stored level. Does not derive level from XP.
     *
     * @return array{xp_for_level: int, xp_for_next_level: int, at_cap: bool, progress_percent: float}
     */
    public function xpBar(int $storedLevel, int $xp): array
    {
        $level = max(1, $storedLevel);
        $cap = self::LEVEL_CAP;
        $atCap = $level >= $cap;
        $xpForLevel = $this->cumulativeXpForLevel($level);
        $xpForNext = $atCap ? $xpForLevel : $this->cumulativeXpForLevel($level + 1);
        $span = $xpForNext - $xpForLevel;
        if ($atCap || $span <= 0) {
            $percent = 100.0;
        } else {
            $into = $xp - $xpForLevel;
            $percent = max(0.0, min(100.0, ($into / $span) * 100.0));
        }

        return [
            'xp_for_level' => $xpForLevel,
            'xp_for_next_level' => $atCap ? $xp : $xpForNext,
            'at_cap' => $atCap,
            'progress_percent' => $percent,
        ];
    }
}
