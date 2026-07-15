<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

use App\Enum\FormationPosition;
use App\Enum\Race;

/**
 * Immutable combatant view for the simulation engine (no Doctrine entities).
 */
final class CombatantSnapshot
{
    /**
     * @param list<array{id: int, name: string, school: string, type: string, mana_cost: int, cooldown: int}> $spells
     * @param array<string, mixed>                                                                            $strategy
     * @param list<mixed>                                                                                     $spellPriorities
     */
    public function __construct(
        private int $heroId,
        private string $name,
        private FormationPosition $slot,
        private Race $race,
        private int $level,
        private int $form,
        private int $fatigue,
        private int $morale,
        private DerivedCombatStats $derived,
        private array $strategy = [],
        private array $spellPriorities = [],
        private array $spells = [],
    ) {
    }

    public function getHeroId(): int
    {
        return $this->heroId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlot(): FormationPosition
    {
        return $this->slot;
    }

    public function getRace(): Race
    {
        return $this->race;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getForm(): int
    {
        return $this->form;
    }

    public function getFatigue(): int
    {
        return $this->fatigue;
    }

    public function getMorale(): int
    {
        return $this->morale;
    }

    public function getDerived(): DerivedCombatStats
    {
        return $this->derived;
    }

    /** @return array<string, mixed> */
    public function getStrategy(): array
    {
        return $this->strategy;
    }

    /** @return list<mixed> */
    public function getSpellPriorities(): array
    {
        return $this->spellPriorities;
    }

    /**
     * @return list<array{id: int, name: string, school: string, type: string, mana_cost: int, cooldown: int}>
     */
    public function getSpells(): array
    {
        return $this->spells;
    }
}
