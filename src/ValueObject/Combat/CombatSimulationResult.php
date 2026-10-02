<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

/**
 * Result of {@see \App\Service\Combat\CombatEngine::simulate()}.
 */
final class CombatSimulationResult
{
    /**
     * @param array<string, mixed> $combatLog
     */
    public function __construct(
        private int $scoreA,
        private int $scoreB,
        private int $seed,
        private array $combatLog,
    ) {
    }

    public function getScoreA(): int
    {
        return $this->scoreA;
    }

    public function getScoreB(): int
    {
        return $this->scoreB;
    }

    public function getSeed(): int
    {
        return $this->seed;
    }

    /** @return array<string, mixed> */
    public function getCombatLog(): array
    {
        return $this->combatLog;
    }

    public function toMatchOutcome(): MatchOutcome
    {
        return new MatchOutcome(
            $this->scoreA,
            $this->scoreB,
            false,
            $this->combatLog,
            $this->seed,
        );
    }
}
