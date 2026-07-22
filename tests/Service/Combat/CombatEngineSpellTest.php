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
            'type' => 'offensive',
            'mana_cost' => 0,
            'cooldown' => 3,
            'tier' => 1,
            'effects' => [],
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

        // Should contain spell_cast fireball
        $spellCastEvent = $this->findEvent($events, 'spell_cast');
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
    }

    public function testDefensiveSpellHealAndUndeadBlocked(): void
    {
        $spell = [
            'id' => 20,
            'name' => 'Holy Light',
            'school' => 'light',
            'type' => 'defensive',
            'mana_cost' => 0,
            'cooldown' => 2,
            'tier' => 1,
            'effects' => [],
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
        $spellCastEvent = $this->findEvent($events, 'spell_cast');
        $this->assertNotNull($spellCastEvent);
        $this->assertSame('front_2', $spellCastEvent['target_slot']);

        $healEvent = $this->findEvent($events, 'heal_applied');
        $this->assertNotNull($healEvent);
        $this->assertSame('front_2', $healEvent['target_slot']);

        // Status applied should be bless (light defensive)
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
            'type' => 'defensive',
            'mana_cost' => 0,
            'cooldown' => 2,
            'tier' => 1,
            'effects' => [],
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
            'type' => 'utility', // maps to freeze (Utility Water status)
            'mana_cost' => 0,
            'cooldown' => 5,
            'tier' => 1,
            'effects' => [],
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

    public function testTauntForcesTargetSelection(): void
    {
        // Setup Side B so front_2 has 'taunt' active status.
        // Side A targets front_1 via L0/approach, but MUST hit front_2 due to Taunt.
        $spellDarkUtility = [
            'id' => 40,
            'name' => 'Taunt Spell',
            'school' => 'dark',
            'type' => 'utility', // maps to taunt (Utility Dark status)
            'mana_cost' => 0,
            'cooldown' => 5,
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
