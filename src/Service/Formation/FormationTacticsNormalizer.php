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

    /**
     * @return array<string, array{target_order: list<string>, fallback: string, spell_priorities: list<array<string, mixed>>}>
     */
    public function emptyUiState(): array
    {
        $state = [];
        foreach (FormationPosition::cases() as $position) {
            $state[$position->value] = [
                'target_order' => [],
                'fallback' => 'approach',
                'spell_priorities' => [],
            ];
        }

        return $state;
    }

    /**
     * @param iterable<mixed> $slots
     *
     * @return array<string, array{target_order: list<string>, fallback: string, spell_priorities: list<array<string, mixed>>}>
     */
    public function initialUiStateFromSlots(iterable $slots): array
    {
        $state = $this->emptyUiState();

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

            $state[$position] = $this->tacticsForUi($strategy, $priorities);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $strategy
     *
     * @return array{target_order: list<string>, fallback: string, spell_priorities: list<array<string, mixed>>}
     */
    public function tacticsForUi(array $strategy, mixed $spellPriorities): array
    {
        $normalized = $this->normalizeStrategy($strategy);

        return [
            'target_order' => $normalized['target_order'] ?? [],
            'fallback' => $normalized['fallback'] ?? 'approach',
            'spell_priorities' => $this->normalizeSpellPriorities($spellPriorities),
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
}
