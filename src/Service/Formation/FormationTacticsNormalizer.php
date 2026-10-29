<?php

declare(strict_types=1);

namespace App\Service\Formation;

use App\Entity\Formation\FormationSlot;
use App\Enum\FormationPosition;

/**
 * Keeps per-slot strategy / spell_priorities on the live L1/L2 schema.
 * Drops L3 fields, NPC-shaped objects, and engine-unmet conditions.
 */
class FormationTacticsNormalizer
{
    public const MOVEMENT_GOALS = [
        'advance_to_melee',
        'seek_cover_ranged',
        'protect_backline',
        'flank_rear',
    ];

    public const FALLBACKS = [
        'lowest_hp',
        'highest_threat',
        'approach',
    ];

    public const WHEN = [
        'always',
        'self_hp_below',
        'ally_hp_below',
    ];

    public const TARGETS = [
        'priority',
        'lowest_hp_ally',
        'self',
    ];

    public const DEFAULT_HP_THRESHOLD = 40;

    public const DEFAULT_MOVEMENT_FRONT = 'advance_to_melee';

    public const DEFAULT_MOVEMENT_BACK = 'seek_cover_ranged';

    /**
     * @return array{movement_front: string, movement_back: string, target_order: list<string>, fallback: string}
     */
    public function defaultTacticsDefaults(): array
    {
        return [
            'movement_front' => self::DEFAULT_MOVEMENT_FRONT,
            'movement_back' => self::DEFAULT_MOVEMENT_BACK,
            'target_order' => [],
            'fallback' => 'approach',
        ];
    }

    /**
     * @return array{movement_front: string, movement_back: string, target_order: list<string>, fallback: string}
     */
    public function normalizeTacticsDefaults(mixed $raw): array
    {
        $defaults = $this->defaultTacticsDefaults();
        if (!is_array($raw)) {
            return $defaults;
        }

        $front = $raw['movement_front'] ?? null;
        if (is_string($front) && in_array($front, self::MOVEMENT_GOALS, true)) {
            $defaults['movement_front'] = $front;
        }

        $back = $raw['movement_back'] ?? null;
        if (is_string($back) && in_array($back, self::MOVEMENT_GOALS, true)) {
            $defaults['movement_back'] = $back;
        }

        $order = $this->normalizeTargetOrder($raw['target_order'] ?? null);
        $defaults['target_order'] = $order;
        if ([] !== $order) {
            $fallback = $raw['fallback'] ?? 'approach';
            $defaults['fallback'] = is_string($fallback) && in_array($fallback, self::FALLBACKS, true)
                ? $fallback
                : 'approach';
        } else {
            $defaults['fallback'] = 'approach';
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $defaults
     */
    public function movementGoalForPosition(string $position, array $defaults): string
    {
        $defaults = $this->normalizeTacticsDefaults($defaults);
        $front = str_starts_with($position, 'front');

        return $front ? $defaults['movement_front'] : $defaults['movement_back'];
    }

    /**
     * Resolve a slot's stored strategy against formation defaults.
     *
     * Player save passes $followDefaultsWhenUnset = true so missing inherit flags
     * follow the template. Combat / NPC rows without the flags keep engine L0 melee.
     *
     * @param array<string, mixed> $defaults
     *
     * @return array<string, mixed>
     */
    public function resolveSlotStrategy(
        mixed $strategy,
        array $defaults,
        string $position,
        bool $followDefaultsWhenUnset = true,
    ): array {
        $defaults = $this->normalizeTacticsDefaults($defaults);
        $raw = is_array($strategy) ? $strategy : [];
        $normalized = $this->normalizeStrategy($raw);
        $hasMovement = isset($normalized['movement_goal']);
        $hasOrder = isset($normalized['target_order']);

        $inheritMovement = $this->inheritFlag(
            $raw['inherit_movement'] ?? null,
            $followDefaultsWhenUnset && !$hasMovement,
        );
        $inheritTargeting = $this->inheritFlag(
            $raw['inherit_targeting'] ?? null,
            $followDefaultsWhenUnset && !$hasOrder,
        );

        $out = [
            'inherit_movement' => $inheritMovement,
            'inherit_targeting' => $inheritTargeting,
        ];

        if ($inheritMovement) {
            $out['movement_goal'] = $this->movementGoalForPosition($position, $defaults);
        } elseif ($hasMovement) {
            $out['movement_goal'] = $normalized['movement_goal'];
        } elseif ($followDefaultsWhenUnset) {
            $out['movement_goal'] = $this->movementGoalForPosition($position, $defaults);
        }

        if ($inheritTargeting) {
            if ([] !== $defaults['target_order']) {
                $out['target_order'] = $defaults['target_order'];
                $out['fallback'] = $defaults['fallback'];
            }
        } elseif ($hasOrder) {
            $out['target_order'] = $normalized['target_order'];
            $out['fallback'] = $normalized['fallback'] ?? 'approach';
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $defaults
     *
     * @return array<string, array{target_order: list<string>, fallback: string, spell_priorities: list<array<string, mixed>>, movement_goal: string, inherit_movement: bool, inherit_targeting: bool}>
     */
    public function emptyUiState(?array $defaults = null): array
    {
        $defaults = $this->normalizeTacticsDefaults($defaults);
        $state = [];
        foreach (FormationPosition::cases() as $position) {
            $state[$position->value] = [
                'target_order' => [],
                'fallback' => $defaults['fallback'],
                'spell_priorities' => [],
                'movement_goal' => $this->movementGoalForPosition($position->value, $defaults),
                'inherit_movement' => true,
                'inherit_targeting' => true,
            ];
        }

        return $state;
    }

    /**
     * @param iterable<mixed>           $slots
     * @param array<string, mixed>|null $defaults
     *
     * @return array<string, array{target_order: list<string>, fallback: string, spell_priorities: list<array<string, mixed>>, movement_goal: string, inherit_movement: bool, inherit_targeting: bool}>
     */
    public function initialUiStateFromSlots(iterable $slots, ?array $defaults = null): array
    {
        $defaults = $this->normalizeTacticsDefaults($defaults);
        $state = $this->emptyUiState($defaults);

        foreach ($slots as $slot) {
            $position = null;
            $strategy = [];
            $priorities = [];

            if ($slot instanceof FormationSlot) {
                $position = $slot->getPosition()->value;
                $strategy = $slot->getStrategy();
                $priorities = $slot->getSpellPriorities();
            } elseif (is_array($slot) && isset($slot['position'])) {
                $position = is_string($slot['position']) ? $slot['position'] : (string) $slot['position'];
                $strategy = is_array($slot['strategy'] ?? null) ? $slot['strategy'] : [];
                $priorities = $slot['spell_priorities'] ?? [];
            }

            if (null === $position || !isset($state[$position])) {
                continue;
            }

            $state[$position] = $this->tacticsForUi($strategy, $priorities, $defaults, $position);
        }

        return $state;
    }

    /**
     * @param array<string, mixed>      $strategy
     * @param array<string, mixed>|null $defaults
     *
     * @return array{target_order: list<string>, fallback: string, spell_priorities: list<array<string, mixed>>, movement_goal: string, inherit_movement: bool, inherit_targeting: bool}
     */
    public function tacticsForUi(array $strategy, mixed $spellPriorities, ?array $defaults = null, string $position = 'front_1'): array
    {
        $resolved = $this->resolveSlotStrategy($strategy, $defaults ?? [], $position, true);

        return [
            'target_order' => $resolved['inherit_targeting'] ? [] : ($resolved['target_order'] ?? []),
            'fallback' => $resolved['inherit_targeting']
                ? $this->normalizeTacticsDefaults($defaults)['fallback']
                : ($resolved['fallback'] ?? 'approach'),
            'spell_priorities' => $this->normalizeSpellPriorities($spellPriorities),
            'movement_goal' => $resolved['movement_goal'],
            'inherit_movement' => $resolved['inherit_movement'],
            'inherit_targeting' => $resolved['inherit_targeting'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeStrategy(mixed $strategy): array
    {
        if (!is_array($strategy)) {
            return [];
        }

        $out = [];
        $goal = $strategy['movement_goal'] ?? null;
        if (is_string($goal) && in_array($goal, self::MOVEMENT_GOALS, true)) {
            $out['movement_goal'] = $goal;
        }

        $order = $this->normalizeTargetOrder($strategy['target_order'] ?? null);
        if ([] !== $order) {
            $out['target_order'] = $order;
            $fallback = $strategy['fallback'] ?? 'approach';
            if (is_string($fallback) && in_array($fallback, self::FALLBACKS, true)) {
                $out['fallback'] = $fallback;
            } else {
                $out['fallback'] = 'approach';
            }
        }

        return $out;
    }

    /**
     * @return list<array{spell_id: int, when: string, target: string, threshold?: int}>
     */
    public function normalizeSpellPriorities(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            return [];
        }

        $out = [];
        $seenIds = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $spellId = $row['spell_id'] ?? null;
            if (!is_numeric($spellId)) {
                continue;
            }
            $spellId = (int) $spellId;
            if ($spellId <= 0 || isset($seenIds[$spellId])) {
                continue;
            }

            $when = $row['when'] ?? 'always';
            if (!is_string($when) || !in_array($when, self::WHEN, true)) {
                continue;
            }

            $target = $row['target'] ?? 'priority';
            if (!is_string($target) || !in_array($target, self::TARGETS, true)) {
                $target = 'priority';
            }

            $entry = [
                'spell_id' => $spellId,
                'when' => $when,
                'target' => $target,
            ];

            if ('always' !== $when) {
                $threshold = $row['threshold'] ?? self::DEFAULT_HP_THRESHOLD;
                if (!is_numeric($threshold)) {
                    $threshold = self::DEFAULT_HP_THRESHOLD;
                }
                $entry['threshold'] = max(1, min(99, (int) $threshold));
            }

            $out[] = $entry;
            $seenIds[$spellId] = true;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function normalizeTargetOrder(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            return [];
        }

        $valid = [];
        foreach (FormationPosition::cases() as $position) {
            $valid[$position->value] = true;
        }

        $order = [];
        $seen = [];
        foreach ($raw as $slot) {
            if (!is_string($slot) || !isset($valid[$slot]) || isset($seen[$slot])) {
                continue;
            }
            $order[] = $slot;
            $seen[$slot] = true;
        }

        return $order;
    }

    private function inheritFlag(mixed $value, bool $default): bool
    {
        if (null === $value) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
