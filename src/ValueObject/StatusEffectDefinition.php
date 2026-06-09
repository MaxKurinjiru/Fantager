<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\School;
use App\Enum\StatusEffect;

class StatusEffectDefinition
{
    public function __construct(
        private StatusEffect $key,
        private string $name,
        private string $type,
        private ?School $school,
        private int $durationTurns,
        private bool $stackable,
        private int $maxStacks = 1,
        private int $tickDamagePercent = 0,
        private int $tickHealPercent = 0,
        private bool $skipTurn = false,
        private int $speedReductionPercent = 0,
        private int $speedBonusPercent = 0,
        private int $defenseReductionPercent = 0,
        private int $accuracyReductionPercent = 0,
        private int $healingReductionPercent = 0,
        private int $damageReductionPercent = 0,
        private int $allStatsBonusPercent = 0,
        private int $damageBonusPercent = 0,
        private int $dodgeBonusPercent = 0,
        private bool $forcesTarget = false,
        private bool $preventsSpells = false,
    ) {
    }

    public function getKey(): StatusEffect
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function getDurationTurns(): int
    {
        return $this->durationTurns;
    }

    public function isStackable(): bool
    {
        return $this->stackable;
    }

    public function getMaxStacks(): int
    {
        return $this->maxStacks;
    }

    public function getTickDamagePercent(): int
    {
        return $this->tickDamagePercent;
    }

    public function getTickHealPercent(): int
    {
        return $this->tickHealPercent;
    }

    public function isSkipTurn(): bool
    {
        return $this->skipTurn;
    }

    public function getSpeedReductionPercent(): int
    {
        return $this->speedReductionPercent;
    }

    public function getSpeedBonusPercent(): int
    {
        return $this->speedBonusPercent;
    }

    public function getDefenseReductionPercent(): int
    {
        return $this->defenseReductionPercent;
    }

    public function getAccuracyReductionPercent(): int
    {
        return $this->accuracyReductionPercent;
    }

    public function getHealingReductionPercent(): int
    {
        return $this->healingReductionPercent;
    }

    public function getDamageReductionPercent(): int
    {
        return $this->damageReductionPercent;
    }

    public function getAllStatsBonusPercent(): int
    {
        return $this->allStatsBonusPercent;
    }

    public function getDamageBonusPercent(): int
    {
        return $this->damageBonusPercent;
    }

    public function getDodgeBonusPercent(): int
    {
        return $this->dodgeBonusPercent;
    }

    public function isForcesTarget(): bool
    {
        return $this->forcesTarget;
    }

    public function isPreventsSpells(): bool
    {
        return $this->preventsSpells;
    }

    /**
     * Creates a definition from parsed YAML config data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $key, array $data): self
    {
        $effectKey = StatusEffect::from($key);
        $school = isset($data['school']) ? School::from(strtolower($data['school'])) : null;

        return new self(
            key: $effectKey,
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            school: $school,
            durationTurns: (int) ($data['duration_turns'] ?? 0),
            stackable: (bool) ($data['stackable'] ?? false),
            maxStacks: (int) ($data['max_stacks'] ?? 1),
            tickDamagePercent: (int) ($data['tick_damage_percent'] ?? 0),
            tickHealPercent: (int) ($data['tick_heal_percent'] ?? 0),
            skipTurn: (bool) ($data['skip_turn'] ?? false),
            speedReductionPercent: (int) ($data['speed_reduction_percent'] ?? 0),
            speedBonusPercent: (int) ($data['speed_bonus_percent'] ?? 0),
            defenseReductionPercent: (int) ($data['defense_reduction_percent'] ?? 0),
            accuracyReductionPercent: (int) ($data['accuracy_reduction_percent'] ?? 0),
            healingReductionPercent: (int) ($data['healing_reduction_percent'] ?? 0),
            damageReductionPercent: (int) ($data['damage_reduction_percent'] ?? 0),
            allStatsBonusPercent: (int) ($data['all_stats_bonus_percent'] ?? 0),
            damageBonusPercent: (int) ($data['damage_bonus_percent'] ?? 0),
            dodgeBonusPercent: (int) ($data['dodge_bonus_percent'] ?? 0),
            forcesTarget: (bool) ($data['forces_target'] ?? false),
            preventsSpells: (bool) ($data['prevents_spells'] ?? false)
        );
    }
}
