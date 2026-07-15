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
        $this->assertTrue($log['placeholder_scores']);
        $this->assertSame('match_start', $log['events'][0]['type']);
        $this->assertSame('match_end', $log['events'][1]['type']);
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
        $b = $engine->simulate($this->buildRequest(99999));

        // Extremely unlikely both pairs match for distant seeds with LCG; assert seed stored.
        $this->assertSame(1, $a->getSeed());
        $this->assertSame(99999, $b->getSeed());
        $this->assertNotSame(
            [$a->getScoreA(), $a->getScoreB()],
            [$b->getScoreA(), $b->getScoreB()],
        );
    }

    private function buildRequest(int $seed): CombatMatchRequest
    {
        return new CombatMatchRequest(
            $this->buildSide(10, 100),
            $this->buildSide(20, 200),
            MatchType::League,
            $seed,
        );
    }

    private function buildSide(int $teamId, int $heroIdBase): CombatSide
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

        return new CombatSide($teamId, $teamId + 1000, FormationApproach::Balanced, $combatants);
    }

    private function emptyDerived(): DerivedCombatStats
    {
        return new DerivedCombatStats(
            100,
            100,
            10,
            10,
            10,
            0.1,
            10,
            0.1,
            10,
            80.0,
            10.0,
            5.0,
        );
    }
}
