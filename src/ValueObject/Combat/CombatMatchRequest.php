<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

use App\Enum\MatchType;
use App\Service\Combat\CombatEngine;

/**
 * Pure input for {@see CombatEngine}.
 */
final class CombatMatchRequest
{
    public function __construct(
        private CombatSide $sideA,
        private CombatSide $sideB,
        private MatchType $matchType,
        private int $seed,
        private int $engineVersion = CombatEngine::ENGINE_VERSION,
    ) {
    }

    public function getSideA(): CombatSide
    {
        return $this->sideA;
    }

    public function getSideB(): CombatSide
    {
        return $this->sideB;
    }

    public function getMatchType(): MatchType
    {
        return $this->matchType;
    }

    public function getSeed(): int
    {
        return $this->seed;
    }

    public function getEngineVersion(): int
    {
        return $this->engineVersion;
    }
}
