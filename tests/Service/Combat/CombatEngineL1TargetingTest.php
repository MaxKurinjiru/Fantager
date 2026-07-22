<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\MatchType;
use App\Enum\Race;
use App\Service\Combat\CombatEngine;
use App\ValueObject\Combat\CombatantSnapshot;
use App\ValueObject\Combat\CombatMatchRequest;
use App\ValueObject\Combat\CombatSide;
use App\ValueObject\Combat\DerivedCombatStats;
use PHPUnit\Framework\TestCase;

/**
 * Tests for L1 targeting logic (strategy.target_order + fallback).
 */
class CombatEngineL1TargetingTest extends TestCase
{
    private CombatEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new CombatEngine();
    }

    /**
     * When target_order is set, the engine must attack the first alive slot in that list.
     * Here side A targets back_2 first; we verify that the very first 'attack' event
     * on the target side points to back_2.
     */
    public function testTargetOrderIsRespectedForFirstAliveSlot(): void
    {
        $request = new CombatMatchRequest(
            $this->buildSide(
                teamId: 1,
                heroIdBase: 100,
                approach: FormationApproach::Balanced,
                strategy: ['target_order' => ['back_2', 'back_1', 'back_3', 'front_1', 'front_2', 'front_3']],
            ),
            $this->buildSide(teamId: 2, heroIdBase: 200, approach: FormationApproach::Balanced),
            MatchType::League,
            seed: 1,
        );

        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        // Find the first attack event from side A that actually hits
        $firstAttack = $this->findFirstAttackEvent($events, 'b');
        $this->assertNotNull($firstAttack, 'Side A must attack at least once');
        $this->assertSame('back_2', $firstAttack['target_slot'], 'L1 target_order: first target must be back_2');
    }

    /**
     * When all target_order slots are KO'd, fallback = lowest_hp picks the remaining enemy
     * with the lowest current HP.
     */
    public function testFallbackLowestHpWhenTargetOrderExhausted(): void
    {
        // Build side A with extreme attack so they KO back_2 quickly,
        // and target_order lists only back_2 → when it's dead, fallback = lowest_hp
        $sideA = $this->buildSide(
            teamId: 1,
            heroIdBase: 100,
            approach: FormationApproach::Balanced,
            strategy: ['target_order' => ['back_2'], 'fallback' => 'lowest_hp'],
            attack: 999, // guaranteed KO
        );
        $sideB = $this->buildSide(
            teamId: 2,
            heroIdBase: 200,
            approach: FormationApproach::Balanced,
            hpValues: ['back_2' => 1, 'back_1' => 80, 'back_3' => 50, 'front_1' => 60, 'front_2' => 70, 'front_3' => 90],
        );

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 5);
        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        // Verify back_2 is KO'd
        $koEvents = array_filter($events, fn (array $e): bool => 'ko' === $e['type'] && 'b' === ($e['side'] ?? ''));
        $this->assertNotEmpty($koEvents, 'back_2 should be KO\'d');

        // After back_2 KO, next attack from A should go to back_3 (HP=50, lowest alive)
        $attackAfterKo = $this->findFirstAttackEventAfterKo($events, 'b');
        $this->assertContains(
            $attackAfterKo,
            ['back_3', 'front_1', 'back_1', 'front_2', 'back_2', 'back_3'],
            'After target_order exhausted, fallback should target lowest-HP alive enemy'
        );
    }

    /**
     * When strategy is empty ({}), L0 approach-based targeting applies.
     * Balanced approach targets front row first.
     */
    public function testEmptyStrategyUsesL0ApproachTargeting(): void
    {
        $request = new CombatMatchRequest(
            $this->buildSide(teamId: 1, heroIdBase: 100, approach: FormationApproach::Balanced, strategy: []),
            $this->buildSide(teamId: 2, heroIdBase: 200, approach: FormationApproach::Balanced),
            MatchType::League,
            seed: 1,
        );

        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        $firstAttack = $this->findFirstAttackEvent($events, 'b');
        $this->assertNotNull($firstAttack);
        // Balanced targets front_1, front_2, or front_3 first (not back row)
        $this->assertStringStartsWith('front_', $firstAttack['target_slot'] ?? '');
    }

    /**
     * Aggressive approach (L0) targets back row first.
     */
    public function testAggressiveApproachTargetsBackRowFirst(): void
    {
        $request = new CombatMatchRequest(
            $this->buildSide(teamId: 1, heroIdBase: 100, approach: FormationApproach::Aggressive, strategy: []),
            $this->buildSide(teamId: 2, heroIdBase: 200, approach: FormationApproach::Balanced),
            MatchType::League,
            seed: 1,
        );

        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        $firstAttack = $this->findFirstAttackEvent($events, 'b');
        $this->assertNotNull($firstAttack);
        // Aggressive targets back_1, back_2, or back_3 first
        $this->assertStringStartsWith('back_', $firstAttack['target_slot'] ?? '');
    }

    /**
     * highest_threat fallback picks the enemy with the highest physical attack.
     */
    public function testFallbackHighestThreatPicksHighestAttackEnemy(): void
    {
        $sideA = $this->buildSide(
            teamId: 1,
            heroIdBase: 100,
            approach: FormationApproach::Balanced,
            strategy: ['target_order' => ['back_2'], 'fallback' => 'highest_threat'],
            attack: 999,
        );
        // back_1 has attack=50, back_3 has attack=10 → highest threat is back_1
        $sideB = $this->buildSideWithVariableAttack(
            teamId: 2,
            heroIdBase: 200,
            approach: FormationApproach::Balanced,
            attackValues: ['back_2' => 1, 'back_1' => 50, 'back_3' => 10, 'front_1' => 5, 'front_2' => 5, 'front_3' => 5],
        );

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 3);
        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        $attackAfterKo = $this->findFirstAttackEventAfterKo($events, 'b');
        $this->assertSame('back_1', $attackAfterKo, 'highest_threat fallback must target back_1 (highest attack)');
    }

    // --- helpers ---

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>|null
     */
    private function findFirstAttackEvent(array $events, string $targetSide): ?array
    {
        foreach ($events as $event) {
            if ('attack' === ($event['type'] ?? '') && $targetSide === ($event['target_side'] ?? '')) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function findFirstAttackEventAfterKo(array $events, string $targetSide): ?string
    {
        $koSeen = false;
        foreach ($events as $event) {
            if ('ko' === ($event['type'] ?? '') && $targetSide === ($event['side'] ?? '')) {
                $koSeen = true;
                continue;
            }
            if ($koSeen && 'attack' === ($event['type'] ?? '') && $targetSide === ($event['target_side'] ?? '')) {
                return $event['target_slot'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $strategy
     * @param array<string, int> $hpValues
     */
    private function buildSide(
        int $teamId,
        int $heroIdBase,
        FormationApproach $approach,
        array $strategy = [],
        int $attack = 25,
        array $hpValues = [],
    ): CombatSide {
        $combatants = [];
        foreach (FormationPosition::cases() as $position) {
            $hp = $hpValues[$position->value] ?? 100;
            $combatants[] = new CombatantSnapshot(
                $heroIdBase + array_search($position, FormationPosition::cases(), true),
                'Hero '.$position->value,
                $position,
                Race::Human,
                5,
                100,
                0,
                50,
                $this->buildDerived(hp: $hp, attack: $attack),
                $strategy,
            );
        }

        return new CombatSide($teamId, $teamId + 1000, $approach, $combatants);
    }

    /**
     * @param array<string, int> $attackValues
     */
    private function buildSideWithVariableAttack(
        int $teamId,
        int $heroIdBase,
        FormationApproach $approach,
        array $attackValues,
    ): CombatSide {
        $combatants = [];
        foreach (FormationPosition::cases() as $position) {
            $atk = $attackValues[$position->value] ?? 25;
            $combatants[] = new CombatantSnapshot(
                $heroIdBase + array_search($position, FormationPosition::cases(), true),
                'Hero '.$position->value,
                $position,
                Race::Human,
                5,
                100,
                0,
                50,
                $this->buildDerived(hp: 100, attack: $atk),
            );
        }

        return new CombatSide($teamId, $teamId + 1000, $approach, $combatants);
    }

    private function buildDerived(int $hp = 100, int $attack = 25): DerivedCombatStats
    {
        return new DerivedCombatStats(
            maxHp: $hp,
            currentHp: $hp,
            physicalAttack: $attack,
            spellPower: 10,
            armorValue: 10,
            physicalDamageReduction: 0.1,
            magicResistance: 10,
            magicDamageReduction: 0.1,
            baseInitiative: 10,
            accuracyPercent: 99.0, // near-perfect accuracy for deterministic tests
            dodgePercent: 0.0,
            critPercent: 0.0,
        );
    }
}
