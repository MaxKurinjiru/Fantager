<?php

declare(strict_types=1);

namespace App\Tests\Service\Formation;

use App\Service\Formation\FormationTacticsNormalizer;
use PHPUnit\Framework\TestCase;

class FormationTacticsNormalizerTest extends TestCase
{
    public function testNormalizeStrategyKeepsLiveL1AndDropsNpcJunk(): void
    {
        $normalized = (new FormationTacticsNormalizer())->normalizeStrategy([
            'movement_goal' => 'flank_rear',
            'target' => 'weakest',
            'action' => 'attack',
            'aggression' => 'high',
            'target_order' => ['back_2', 'nope', 'back_2', 'front_1'],
            'fallback' => 'lowest_hp',
        ]);

        $this->assertSame([
            'movement_goal' => 'flank_rear',
            'target_order' => ['back_2', 'front_1'],
            'fallback' => 'lowest_hp',
        ], $normalized);
    }

    public function testNormalizeStrategyOmitsFallbackWhenTargetOrderEmpty(): void
    {
        $normalized = (new FormationTacticsNormalizer())->normalizeStrategy([
            'movement_goal' => 'advance_to_melee',
            'fallback' => 'lowest_hp',
            'target_order' => [],
        ]);

        $this->assertSame(['movement_goal' => 'advance_to_melee'], $normalized);
    }

    public function testNormalizeSpellPrioritiesKeepsLiveL2AndDropsUnmet(): void
    {
        $normalized = (new FormationTacticsNormalizer())->normalizeSpellPriorities([
            ['spell_id' => 12, 'when' => 'ally_hp_below', 'threshold' => 40, 'target' => 'lowest_hp_ally'],
            ['spell_id' => 8, 'when' => 'enemy_status', 'target' => 'priority'],
            ['heal_trigger_pct' => 30, 'prefer_magic' => false],
            ['spell_id' => 3, 'when' => 'always', 'target' => 'lowest_hp_enemy'],
            ['spell_id' => 12, 'when' => 'always', 'target' => 'priority'],
        ]);

        $this->assertSame([
            [
                'spell_id' => 12,
                'when' => 'ally_hp_below',
                'target' => 'lowest_hp_ally',
                'threshold' => 40,
            ],
            [
                'spell_id' => 3,
                'when' => 'always',
                'target' => 'priority',
            ],
        ], $normalized);
    }

    public function testNormalizeSpellPrioritiesRejectsAssociativeNpcShape(): void
    {
        $normalized = (new FormationTacticsNormalizer())->normalizeSpellPriorities([
            'heal_trigger_pct' => 50,
            'prefer_magic' => true,
        ]);

        $this->assertSame([], $normalized);
    }

    public function testEmptyUiStateHasAllSixSlots(): void
    {
        $state = (new FormationTacticsNormalizer())->emptyUiState();

        $this->assertSame(
            ['front_1', 'front_2', 'front_3', 'back_1', 'back_2', 'back_3'],
            array_keys($state),
        );
        $this->assertSame([], $state['front_1']['target_order']);
        $this->assertSame('approach', $state['front_1']['fallback']);
        $this->assertSame([], $state['front_1']['spell_priorities']);
    }
}
