<?php

declare(strict_types=1);

namespace App\Service\Config;

use App\Enum\FormationApproach;
use Symfony\Component\Yaml\Yaml;

/**
 * L0 approach thresholds from config/game/combat_ai_l0.yaml.
 */
final class CombatAiL0Config
{
    private int $aggressiveHealPercent;
    private int $balancedHealPercent;
    private int $defensiveHealPercent;

    public function __construct(string $projectDir)
    {
        /** @var array{heal_hp_percent: array<string, int>} $parsed */
        $parsed = Yaml::parseFile($projectDir.'/config/game/combat_ai_l0.yaml');
        $thresholds = $parsed['heal_hp_percent'];

        $this->aggressiveHealPercent = (int) $thresholds['aggressive'];
        $this->balancedHealPercent = (int) $thresholds['balanced'];
        $this->defensiveHealPercent = (int) $thresholds['defensive'];
    }

    public function healThresholdPercent(FormationApproach $approach): int
    {
        return match ($approach) {
            FormationApproach::Aggressive => $this->aggressiveHealPercent,
            FormationApproach::Balanced => $this->balancedHealPercent,
            FormationApproach::Defensive => $this->defensiveHealPercent,
        };
    }
}
