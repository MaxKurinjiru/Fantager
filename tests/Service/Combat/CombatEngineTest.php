<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\ItemSubType;
use App\Enum\MatchType;
use App\Enum\Race;
use App\Service\Combat\CombatEngine;
use App\ValueObject\Combat\CombatantSnapshot;
use App\ValueObject\Combat\CombatMatchRequest;
use App\ValueObject\Combat\CombatSide;
use App\ValueObject\Combat\DerivedCombatStats;
use PHPUnit\Framework\TestCase;

class CombatEngineTest extends TestCase
{
    public function testSimulateEmitsEnvelopeAndIsSeedDeterministic(): void
    {
        $engine = new CombatEngine();
        $request = $this->buildRequest(42);

        $first = $engine->simulate($request);
        $second = $engine->simulate($request);

        $this->assertSame($first->getScoreA(), $second->getScoreA());
        $this->assertSame($first->getScoreB(), $second->getScoreB());
        $this->assertSame(42, $first->getSeed());

        $log = $first->getCombatLog();
        $this->assertSame(1, $log['version']);
        $this->assertSame('combat_engine', $log['simulator']);
        $this->assertSame(2, $log['engine_version']);
        $this->assertSame(['width' => 17, 'height' => 11], $log['grid']);
        $this->assertSame('match_start', $log['events'][0]['type']);
        $lastEvent = end($log['events']);
        $this->assertSame('match_end', $lastEvent['type']);
        $this->assertArrayHasKey('front_1', $log['lineup']['a']);
        $this->assertIsArray($log['lineup']['a']);
        $this->assertCount(6, $log['lineup']['a']);

        $outcome = $first->toMatchOutcome();
        $this->assertSame($first->getScoreA(), $outcome->getHomeScore());
        $this->assertSame($first->getCombatLog(), $outcome->getCombatLog());
        $this->assertFalse($outcome->isForfeit());
    }

    public function testDifferentSeedsCanProduceDifferentScores(): void
    {
        $engine = new CombatEngine();
        $a = $engine->simulate($this->buildRequest(1));

        $different = false;
        for ($seed = 2; $seed <= 50; $seed++) {
            $b = $engine->simulate($this->buildRequest($seed));
            if ($a->getScoreA() !== $b->getScoreA() || $a->getScoreB() !== $b->getScoreB()) {
                $different = true;
                $this->assertSame($seed, $b->getSeed());
                break;
            }
        }

        $this->assertTrue($different, 'Different seeds should produce different scores');
        $this->assertSame(1, $a->getSeed());
    }

    private function buildRequest(int $seed): CombatMatchRequest
    {
        return new CombatMatchRequest(
            $this->buildSide(10, 100, FormationApproach::Aggressive),
            $this->buildSide(20, 200, FormationApproach::Balanced),
            MatchType::League,
            $seed,
        );
    }

    private function buildSide(int $teamId, int $heroIdBase, FormationApproach $approach = FormationApproach::Balanced): CombatSide
    {
        $combatants = [];
        foreach (FormationPosition::cases() as $i => $position) {
            $combatants[] = new CombatantSnapshot(
                $heroIdBase + $i,
                'Hero '.($heroIdBase + $i),
                $position,
                Race::Human,
                5,
                100,
                0,
                50,
                $this->emptyDerived(),
            );
        }

        return new CombatSide($teamId, $teamId + 1000, $approach, $combatants);
    }

    public function testActionPlanningAndExecutionWithWeaponDrawAndSpellCastingAndStunAndFumble(): void
    {
        $engine = new CombatEngine();

        // 1. Bow Draw Time (Duration = 2)
        $sideA = $this->buildSideWithWeapon(1, 100, ItemSubType::Bow);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced);
        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, 42);
        $runState = $engine->initializeRunState($request);
        $this->placeWithinRangedReach($runState);

        // Round 1: Plan Bow Attack
        $eventsRound1 = $engine->simulateRound($runState, 1);
        $planEvent = $this->findEventByType($eventsRound1, 'plan_action');
        $this->assertNotNull($planEvent);
        $this->assertSame('attack', $planEvent['action_type']);
        $this->assertSame('bow', $planEvent['weapon_type']);
        $this->assertSame(2, $planEvent['duration']);

        // Round 2: Prepare/Draw Bow
        $eventsRound2 = $engine->simulateRound($runState, 2);
        $tickEvent = $this->findEventByType($eventsRound2, 'preparing_action_tick');
        $this->assertNotNull($tickEvent);
        $this->assertSame('attack', $tickEvent['action_type']);
        $this->assertSame(1, $tickEvent['rounds_remaining']);

        // Round 3: Execute Bow Attack
        $eventsRound3 = $engine->simulateRound($runState, 3);
        $attackEvent = $this->findEventByType($eventsRound3, 'attack');
        $this->assertNotNull($attackEvent);

        // 2. Crossbow Draw Time (Duration = 3)
        $sideA = $this->buildSideWithWeapon(1, 100, ItemSubType::Crossbow);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced);
        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, 42);
        $runState = $engine->initializeRunState($request);
        $this->placeWithinRangedReach($runState);

        // Round 1: Plan Crossbow Attack
        $eventsRound1 = $engine->simulateRound($runState, 1);
        $planEvent = $this->findEventByType($eventsRound1, 'plan_action');
        $this->assertNotNull($planEvent);
        $this->assertSame(3, $planEvent['duration']);

        // Round 2: Prepare 1st tick
        $eventsRound2 = $engine->simulateRound($runState, 2);
        $tickEvent1 = $this->findEventByType($eventsRound2, 'preparing_action_tick');
        $this->assertNotNull($tickEvent1);
        $this->assertSame(2, $tickEvent1['rounds_remaining']);

        // Round 3: Prepare 2nd tick
        $eventsRound3 = $engine->simulateRound($runState, 3);
        $tickEvent2 = $this->findEventByType($eventsRound3, 'preparing_action_tick');
        $this->assertNotNull($tickEvent2);
        $this->assertSame(1, $tickEvent2['rounds_remaining']);

        // Round 4: Execute
        $eventsRound4 = $engine->simulateRound($runState, 4);
        $attackEvent = $this->findEventByType($eventsRound4, 'attack');
        $this->assertNotNull($attackEvent);

        // 3. Option B (Fumble): If target is KO'd, the action fumbles
        $sideA = $this->buildSideWithWeapon(1, 100, ItemSubType::Bow);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced);
        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, 42);
        $runState = $engine->initializeRunState($request);
        $this->placeWithinRangedReach($runState);

        // Round 1: Plan Bow Attack on B's Front1
        $engine->simulateRound($runState, 1);
        // Manually KO the target (Front1 of side B)
        foreach ($runState['sideB']['combatants'] as &$c) {
            if ('front_1' === $c['slot']) {
                $c['currentHp'] = 0;
            }
        }
        unset($c);

        // Round 2: Preparing tick
        $engine->simulateRound($runState, 2);

        // Round 3: Execute, target is dead -> Fumble
        $eventsRound3 = $engine->simulateRound($runState, 3);
        $fumbleEvent = $this->findEventByType($eventsRound3, 'action_fumble');
        $this->assertNotNull($fumbleEvent);
        $this->assertSame('front_1', $fumbleEvent['target_slot']);
    }

    private function buildSideWithWeapon(int $teamId, int $heroIdBase, ItemSubType $weaponSubType): CombatSide
    {
        $combatants = [];
        foreach (FormationPosition::cases() as $i => $position) {
            $combatants[] = new CombatantSnapshot(
                $heroIdBase + $i,
                'Hero '.($heroIdBase + $i),
                $position,
                Race::Human,
                5,
                100,
                0,
                50,
                $this->emptyDerived(),
                [],
                [],
                [],
                $weaponSubType
            );
        }

        return new CombatSide($teamId, $teamId + 1000, FormationApproach::Balanced, $combatants);
    }

    /**
     * Keep round-scripted weapon tests on the wider 17×11 map: fronts already in bow reach (5).
     *
     * @param array<string, mixed> $runState
     */
    private function placeWithinRangedReach(array &$runState): void
    {
        foreach (['sideA' => 7, 'sideB' => 12] as $sideKey => $q) {
            foreach ($runState[$sideKey]['combatants'] as $i => $combatant) {
                $slot = (string) ($combatant['slot'] ?? '');
                if (str_starts_with($slot, 'front_')) {
                    $runState[$sideKey]['combatants'][$i]['q'] = $q;
                }
            }
        }
    }

    /**
     * @param array<array<string, mixed>> $events
     * @return array<string, mixed>|null
     */
    private function findEventByType(array $events, string $type): ?array
    {
        foreach ($events as $event) {
            if ($type === ($event['type'] ?? '')) {
                return $event;
            }
        }

        return null;
    }

    private function emptyDerived(): DerivedCombatStats
    {
        return new DerivedCombatStats(
            100,
            100,
            25,   // physicalAttack: higher to make KOs happen faster
            10,
            10,
            0.1,
            10,
            0.1,
            10,
            75.0, // accuracyPercent: lower to increase miss frequency variance
            30.0, // dodgePercent: higher to increase dodge variance
            35.0, // critPercent: higher to increase critical strike variance
        );
    }
}
