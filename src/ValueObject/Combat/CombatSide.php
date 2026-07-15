<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

use App\Enum\FormationApproach;

/**
 * One side of a match (exactly six combatants).
 */
final class CombatSide
{
    /**
     * @param list<CombatantSnapshot> $combatants
     */
    public function __construct(
        private int $teamId,
        private ?int $formationId,
        private FormationApproach $approach,
        private array $combatants,
    ) {
        if (6 !== \count($combatants)) {
            throw new \InvalidArgumentException('CombatSide requires exactly 6 combatants.');
        }
    }

    public function getTeamId(): int
    {
        return $this->teamId;
    }

    public function getFormationId(): ?int
    {
        return $this->formationId;
    }

    public function getApproach(): FormationApproach
    {
        return $this->approach;
    }

    /** @return list<CombatantSnapshot> */
    public function getCombatants(): array
    {
        return $this->combatants;
    }
}
