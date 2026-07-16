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
