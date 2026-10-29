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

class CombatEngineSpellTest extends TestCase
{
    private CombatEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new CombatEngine();
    }

    public function testOffensiveSpellDamageAndStatusApplied(): void
    {
        // Spells configuration
        $spell = [
            'id' => 10,
            'name' => 'Fireball',
            'school' => 'fire',
            'type' => 'harm',
            'mana_cost' => 0,
            'cooldown' => 3,
            'tier' => 1,
            'effects' => ['instant_hp' => 'damage', 'apply_effect' => 'burn'],
        ];

        $priorities = [
            [
                'spell_id' => 10,
                'when' => 'always',
                'threshold' => 100,
                'target' => 'enemy',
            ],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], spellPower: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], hp: 200);

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1);
        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        // Should contain spell fireball
        $spellCastEvent = $this->findEvent($events, 'spell');
        $this->assertNotNull($spellCastEvent);
        $this->assertSame(10, $spellCastEvent['spell_id']);

        // Damage event should be magical and have correct target
        $damageEvent = null;
        foreach ($events as $event) {
            if ('damage' === ($event['type'] ?? '') && 'magical' === ($event['source'] ?? '')) {
                $damageEvent = $event;
                break;
            }
        }
        $this->assertNotNull($damageEvent);
        $this->assertSame('magical', $damageEvent['source']);

        // Status effect applied should be burn
        $statusAppliedEvent = $this->findEvent($events, 'status_applied');
        $this->assertNotNull($statusAppliedEvent);
        $this->assertSame('burn', $statusAppliedEvent['effect']);
        $this->assertSame(100, $statusAppliedEvent['source_hero_id']);
    }

    public function testHarmSpellWithoutStatusDealsDamageOnly(): void
    {
        $spell = [
            'id' => 11,
            'name' => 'Spark',
            'school' => 'fire',
            'type' => 'harm',
            'mana_cost' => 0,
            'cooldown' => 1,
            'tier' => 1,
            'effects' => ['instant_hp' => 'damage'],
        ];
        $priorities = [
            ['spell_id' => 11, 'when' => 'always', 'threshold' => 100, 'target' => 'enemy'],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], spellPower: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], hp: 200);
        $events = $this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events'];

        $this->assertNotNull($this->findEvent($events, 'damage'));
        $this->assertNull($this->findEvent($events, 'status_applied'));
    }

    public function testDefensiveSpellHealAndUndeadBlocked(): void
    {
        $spell = [
            'id' => 20,
            'name' => 'Holy Light',
            'school' => 'light',
            'type' => 'restore',
            'mana_cost' => 0,
            'cooldown' => 2,
            'tier' => 1,
            'effects' => ['instant_hp' => 'heal', 'apply_effect' => 'bless'],
        ];

        $priorities = [
            [
                'spell_id' => 20,
                'when' => 'ally_hp_below',
                'threshold' => 90,
                'target' => 'lowest_hp_ally',
            ],
        ];

        // Create an ally with low HP
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], hpValues: [
            'front_1' => 100, // Caster
            'front_2' => 20,  // Low HP human ally
            'front_3' => 20,  // Low HP undead ally
        ], races: [
            'front_3' => Race::Undead,
        ]);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], hp: 1000); // high HP so they don't die instantly

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 42);
        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        // Should heal the low HP ally
        $spellCastEvent = $this->findEvent($events, 'spell');
        $this->assertNotNull($spellCastEvent);
        $this->assertSame('front_2', $spellCastEvent['target_slot']);

        $healEvent = $this->findEvent($events, 'heal_applied');
        $this->assertNotNull($healEvent);
        $this->assertSame('front_2', $healEvent['target_slot']);

        // Status applied should be bless (heal + bless payload)
        $statusAppliedEvent = $this->findEvent($events, 'status_applied');
        $this->assertNotNull($statusAppliedEvent);
        $this->assertSame('bless', $statusAppliedEvent['effect']);
    }

    public function testUndeadExternalHealBlocked(): void
    {
        $spell = [
            'id' => 20,
            'name' => 'Holy Light',
            'school' => 'light',
            'type' => 'restore',
            'mana_cost' => 0,
            'cooldown' => 2,
            'tier' => 1,
            'effects' => ['instant_hp' => 'heal', 'apply_effect' => 'bless'],
        ];

        $priorities = [
            [
                'spell_id' => 20,
                'when' => 'always',
                'threshold' => 100,
                'target' => 'lowest_hp_ally',
            ],
        ];

        // Caster is human, target ally in front_2 is Undead with low HP
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], hpValues: [
            'front_1' => 100, // Caster
            'front_2' => 20,  // Undead ally
        ], races: [
            'front_2' => Race::Undead,
        ]);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], hp: 1000);

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 42);
        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        // Should block healing
        $blockedEvent = $this->findEvent($events, 'heal_blocked');
        $this->assertNotNull($blockedEvent);
        $this->assertSame('front_2', $blockedEvent['target_slot']);
        $this->assertSame('undead_external_heal', $blockedEvent['reason']);
    }

    public function testUtilitySpellFreezeSkipsTurnAndSilenceBlocksCasting(): void
    {
        // Spells configuration
        $spellWaterUtility = [
            'id' => 30,
            'name' => 'Frostbolt',
            'school' => 'water',
            'type' => 'hinder',
            'mana_cost' => 0,
            'cooldown' => 5,
            'tier' => 1,
            'effects' => ['apply_effect' => 'freeze'],
        ];

        $priorities = [
            [
                'spell_id' => 30,
                'when' => 'always',
                'threshold' => 100,
                'target' => 'enemy',
            ],
        ];

        // Side A casts frostbolt immediately at front_1 of Side B
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spellWaterUtility], spellPower: 10);
        // Make Side B's front_1 slower so Side A goes first
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1);
        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        // Frostbolt cast on B front_1 should apply freeze status
        $statusEvent = $this->findEvent($events, 'status_applied');
        $this->assertNotNull($statusEvent);
        $this->assertSame('b', $statusEvent['target_side']);
        $this->assertSame('front_1', $statusEvent['target_slot']);
        $this->assertSame('freeze', $statusEvent['effect']);

        // When B's front_1 turn comes, it should skip turn
        $skipEvent = $this->findEvent($events, 'skip_turn');
        $this->assertNotNull($skipEvent);
        $this->assertSame('b', $skipEvent['side']);
        $this->assertSame('front_1', $skipEvent['slot']);
    }

    public function testSilenceInterruptsQueuedSpell(): void
    {
        $mute = [
            'id' => 1,
            'name' => 'Mute',
            'school' => 'dark',
            'type' => 'hinder',
            'mana_cost' => 0,
            'cooldown' => 5,
            'tier' => 1,
            'effects' => ['apply_effect' => 'silence'],
        ];
        $meteor = [
            'id' => 2,
            'name' => 'Meteor',
            'school' => 'fire',
            'type' => 'harm',
            'mana_cost' => 0,
            'cooldown' => 5,
            'tier' => 5,
            'effects' => ['instant_hp' => 'damage'],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [
            ['spell_id' => 1, 'when' => 'always', 'target' => 'enemy'],
        ], [$mute], init: 20);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [
            ['spell_id' => 2, 'when' => 'always', 'target' => 'enemy'],
        ], [$meteor], init: 80);

        $events = $this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events'];

        $status = $this->findEvent($events, 'status_applied');
        $this->assertNotNull($status);
        $this->assertSame('silence', $status['effect']);
        $this->assertSame('b', $status['target_side']);

        $interrupted = null;
        foreach ($events as $event) {
            if ('action_interrupted' === ($event['type'] ?? '') && 'silence' === ($event['reason'] ?? '')) {
                $interrupted = $event;
                break;
            }
        }
        $this->assertNotNull($interrupted);
        $this->assertSame('b', $interrupted['side']);
        $this->assertSame('front_1', $interrupted['slot']);
    }

    public function testTauntForcesTargetSelection(): void
    {
        // Setup Side B so front_2 has 'taunt' active status.
        // Side A targets front_1 via L0/approach, but MUST hit front_2 due to Taunt.
        $spellDarkUtility = [
            'id' => 40,
            'name' => 'Taunt Spell',
            'school' => 'dark',
            'type' => 'hinder',
            'mana_cost' => 0,
            'cooldown' => 5,
            'effects' => ['apply_effect' => 'taunt'],
        ];
        $priorities = [
            [
                'spell_id' => 40,
                'when' => 'always',
                'target' => 'lowest_hp_ally', // cast on self or lowest hp ally to taunt
            ]
        ];

        // Side A caster will taunt himself (self)
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [
            [
                'spell_id' => 40,
                'when' => 'always',
                'target' => 'self',
            ]
        ], [$spellDarkUtility], init: 99); // Caster is extremely fast

        // Side B has no spells, just does normal physical attacks
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1);
        $result = $this->engine->simulate($request);
        $events = $result->getCombatLog()['events'];

        // Caster on side A (front_1) casts taunt on self
        $statusEvent = $this->findEvent($events, 'status_applied');
        $this->assertNotNull($statusEvent);
        $this->assertSame('a', $statusEvent['target_side']);
        $this->assertSame('front_1', $statusEvent['target_slot']);
        $this->assertSame('taunt', $statusEvent['effect']);

        // When Side B attacks, it MUST attack Side A's front_1
        $attackEvent = null;
        foreach ($events as $event) {
            if ('attack' === ($event['type'] ?? '') && 'a' === ($event['target_side'] ?? '')) {
                $attackEvent = $event;
                break;
            }
        }
        $this->assertNotNull($attackEvent);
        $this->assertSame('a', $attackEvent['target_side']);
        $this->assertSame('front_1', $attackEvent['target_slot'], 'Taunt must force target to front_1');
    }

    public function testEmptyPrioritiesCastsHighestTierOffensiveSpell(): void
    {
        $spells = [
            $this->spell(1, 'Spark', 'harm', 1),
            $this->spell(2, 'Fireball', 'harm', 3),
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [], $spells, init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Fireball', $plan['spell_name']);
        $this->assertSame('b', $plan['target_side']);
    }

    public function testEmptyPrioritiesHealsAllyUnderBalancedThreshold(): void
    {
        $spells = [
            $this->spell(3, 'Mend', 'restore', 1),
            $this->spell(4, 'Bolt', 'harm', 2),
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [], $spells, init: 50, hpValues: ['front_2' => 30]);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Mend', $plan['spell_name']);
        $this->assertSame('a', $plan['target_side']);
        $this->assertSame('front_2', $plan['target_slot']);
    }

    public function testEmptyPrioritiesSkipHealWhenAllyIsAboveAggressiveThreshold(): void
    {
        $spells = [
            $this->spell(3, 'Mend', 'restore', 1),
            $this->spell(4, 'Bolt', 'harm', 2),
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Aggressive, [], $spells, init: 50, hpValues: ['front_2' => 30]);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Bolt', $plan['spell_name']);
    }

    public function testExplicitSpellPriorityBeatsHigherTierSpell(): void
    {
        $spells = [
            $this->spell(1, 'Spark', 'harm', 1),
            $this->spell(2, 'Fireball', 'harm', 3),
        ];
        $priorities = [
            ['spell_id' => 1, 'when' => 'always', 'target' => 'enemy'],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, $spells, init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Spark', $plan['spell_name']);
    }

    public function testSpellKillScoreEmitsContractPayload(): void
    {
        $spell = [
            'id' => 10,
            'name' => 'Nuke',
            'school' => 'fire',
            'type' => 'harm',
            'mana_cost' => 0,
            'cooldown' => 1,
            'tier' => 5,
            'effects' => ['instant_hp' => 'damage'],
        ];
        $priorities = [
            ['spell_id' => 10, 'when' => 'always', 'target' => 'enemy'],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], spellPower: 500, init: 99);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], hp: 1, init: 1);

        $events = $this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events'];

        $killScore = null;
        foreach ($events as $event) {
            if ('kill_score' === ($event['type'] ?? '') && isset($event['score_a'], $event['score_b'])) {
                $killScore = $event;
                break;
            }
        }

        $this->assertNotNull($killScore, 'spell KO must emit kill_score with score_a/score_b');
        $this->assertArrayNotHasKey('side', $killScore);
        $this->assertGreaterThanOrEqual(1, $killScore['score_a']);
        $this->assertSame(0, $killScore['score_b']);
    }

    public function testUnknownSpellWhenIsSkipped(): void
    {
        $spells = [
            $this->spell(1, 'Spark', 'harm', 1),
            $this->spell(2, 'Fireball', 'harm', 3),
        ];
        $priorities = [
            ['spell_id' => 1, 'when' => 'enemy_status', 'target' => 'enemy'],
            ['spell_id' => 2, 'when' => 'always', 'target' => 'enemy'],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, $spells, init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Fireball', $plan['spell_name'], 'unimplemented when must not match; next priority wins');
    }

    public function testL0CastsHinderWhenNoHealOrHarmIsReady(): void
    {
        $spells = [
            $this->spell(3, 'Frost', 'hinder', 2),
        ];
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [], $spells, init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Frost', $plan['spell_name']);
        $this->assertSame(0, $plan['mana_cost']);
    }

    public function testEmptyPrioritiesCastsMissingEnhanceOnSelf(): void
    {
        $spells = [
            $this->spell(5, 'Haste', 'enhance', 3),
        ];
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [], $spells, init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Haste', $plan['spell_name']);
        $this->assertSame('a', $plan['target_side']);
        $this->assertSame('front_1', $plan['target_slot']);
    }

    public function testEmptyPrioritiesPrefersLowestMissingEnhanceOverHarm(): void
    {
        $spells = [
            $this->spell(4, 'Bolt', 'harm', 5),
            $this->spell(5, 'Haste', 'enhance', 1),
            $this->spell(6, 'Gale', 'enhance', 4),
        ];
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [], $spells, init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame('Haste', $plan['spell_name']);
        $this->assertSame('front_1', $plan['target_slot']);
    }

    public function testEmptyPrioritiesSkipsEnhanceOnceBuffIsActive(): void
    {
        $haste = $this->spell(5, 'Haste', 'enhance', 1);
        $haste['cooldown'] = 0;
        $spells = [
            $this->spell(4, 'Bolt', 'harm', 2),
            $haste,
        ];
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, [], $spells, init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plans = $this->spellPlans(
            $this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']
        );

        $this->assertGreaterThanOrEqual(2, count($plans));
        $this->assertSame('Haste', $plans[0]['spell_name']);
        $this->assertSame('Bolt', $plans[1]['spell_name']);
    }

    public function testSpellEventCarriesPayloadSummary(): void
    {
        $spell = [
            'id' => 10,
            'name' => 'Fireball',
            'school' => 'fire',
            'type' => 'harm',
            'mana_cost' => 0,
            'cooldown' => 3,
            'tier' => 1,
            'effects' => ['instant_hp' => 'damage', 'apply_effect' => 'burn'],
        ];
        $priorities = [
            ['spell_id' => 10, 'when' => 'always', 'threshold' => 100, 'target' => 'enemy'],
        ];
        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], spellPower: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], hp: 200);
        $events = $this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events'];

        $cast = $this->findEvent($events, 'spell');
        $this->assertNotNull($cast);
        $this->assertSame('damage', $cast['instant_hp']);
        $this->assertSame('burn', $cast['apply_effect']);
        $this->assertGreaterThan(0, $cast['amount']);
        $this->assertArrayHasKey('hp_after', $cast);
        $this->assertSame(3, $cast['status_duration']);

        $damage = null;
        foreach ($events as $event) {
            if ('damage' === ($event['type'] ?? '') && 'magical' === ($event['source'] ?? '')) {
                $damage = $event;
                break;
            }
        }
        $this->assertNotNull($damage);
        $this->assertFalse($damage['accordion']);
        $status = $this->findEvent($events, 'status_applied');
        $this->assertNotNull($status);
        $this->assertFalse($status['accordion']);
    }

    public function testSpellIsNotQueuedWhenManaIsInsufficient(): void
    {
        $spell = $this->spell(10, 'Meteor', 'harm', 5);
        $spell['mana_cost'] = 200;
        $priorities = [
            ['spell_id' => 10, 'when' => 'always', 'target' => 'enemy'],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNull($plan);
    }

    public function testPlanActionDeductsMana(): void
    {
        $spell = $this->spell(10, 'Spark', 'harm', 1);
        $spell['mana_cost'] = 15;
        $priorities = [
            ['spell_id' => 10, 'when' => 'always', 'target' => 'enemy'],
        ];

        $sideA = $this->buildSide(1, 100, FormationApproach::Balanced, $priorities, [$spell], init: 50);
        $sideB = $this->buildSide(2, 200, FormationApproach::Balanced, [], [], init: 1);

        $plan = $this->firstSpellPlan($this->engine->simulate(new CombatMatchRequest($sideA, $sideB, MatchType::League, seed: 1))->getCombatLog()['events']);

        $this->assertNotNull($plan);
        $this->assertSame(15, $plan['mana_cost']);
        $this->assertSame(85, $plan['mana_after']);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array<string, mixed>|null
     */
    private function firstSpellPlan(array $events): ?array
    {
        foreach ($events as $event) {
            if ('plan_action' === ($event['type'] ?? '') && 'spell' === ($event['action_type'] ?? '') && 'a' === ($event['side'] ?? '') && 'front_1' === ($event['slot'] ?? '')) {
                return $event;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return list<array<string, mixed>>
     */
    private function spellPlans(array $events): array
    {
        $plans = [];
        foreach ($events as $event) {
            if ('plan_action' === ($event['type'] ?? '') && 'spell' === ($event['action_type'] ?? '') && 'a' === ($event['side'] ?? '') && 'front_1' === ($event['slot'] ?? '')) {
                $plans[] = $event;
            }
        }

        return $plans;
    }

    /**
     * @param array<string, string> $effects
     *
     * @return array{id: int, name: string, school: string, type: string, mana_cost: int, cooldown: int, tier: int, effects: array<string, string>}
     */
    private function spell(int $id, string $name, string $type, int $tier, array $effects = []): array
    {
        if ([] === $effects) {
            $effects = match ($type) {
                'harm' => ['instant_hp' => 'damage'],
                'restore' => ['instant_hp' => 'heal'],
                'hinder' => ['apply_effect' => 'freeze'],
                'enhance' => ['apply_effect' => 'haste'],
                default => [],
            };
        }

        $school = match ($type) {
            'restore' => 'light',
            'hinder' => 'water',
            'enhance' => 'air',
            default => 'fire',
        };

        return [
            'id' => $id,
            'name' => $name,
            'school' => $school,
            'type' => $type,
            'mana_cost' => 0,
            'cooldown' => 1,
            'tier' => $tier,
            'effects' => $effects,
        ];
    }

    // --- Helpers ---

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>|null
     */
    private function findEvent(array $events, string $type): ?array
    {
        foreach ($events as $event) {
            if ($type === ($event['type'] ?? '')) {
                return $event;
            }
        }
        return null;
    }

    /**
     * @param list<mixed> $priorities
     * @param list<array{id: int, name: string, school: string, type: string, mana_cost: int, cooldown: int}> $spells
     * @param array<string, int> $hpValues
     * @param array<string, Race> $races
     */
    private function buildSide(
        int $teamId,
        int $heroIdBase,
        FormationApproach $approach,
        array $priorities = [],
        array $spells = [],
        int $spellPower = 20,
        int $hp = 100,
        array $hpValues = [],
        array $races = [],
        int $init = 10,
    ): CombatSide {
        $combatants = [];
        foreach (FormationPosition::cases() as $position) {
            $slotHp = $hpValues[$position->value] ?? $hp;
            $race = $races[$position->value] ?? Race::Human;
            
            // Only assign spellbook and priorities to front_1 (the main test actor)
            $isActor = ($position === FormationPosition::Front1);

            $combatants[] = new CombatantSnapshot(
                $heroIdBase + array_search($position, FormationPosition::cases(), true),
                'Hero '.$position->value,
                $position,
                $race,
                5,
                100,
                0,
                50,
                $this->buildDerived($slotHp, $spellPower, $init),
                [], // strategy
                $isActor ? $priorities : [],
                $isActor ? $spells : [],
            );
        }

        return new CombatSide($teamId, $teamId + 1000, $approach, $combatants);
    }

    private function buildDerived(int $hp, int $spellPower, int $init): DerivedCombatStats
    {
        return new DerivedCombatStats(
            maxHp: 100,
            currentHp: $hp,
            physicalAttack: 10,
            spellPower: $spellPower,
            armorValue: 10,
            physicalDamageReduction: 0.0,
            magicResistance: 10,
            magicDamageReduction: 0.1,
            baseInitiative: $init,
            accuracyPercent: 99.0,
            dodgePercent: 0.0,
            critPercent: 0.0,
        );
    }
}
