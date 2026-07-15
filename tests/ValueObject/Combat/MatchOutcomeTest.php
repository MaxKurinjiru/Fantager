<?php

declare(strict_types=1);

namespace App\Tests\ValueObject\Combat;

use App\Enum\BattleResult;
use App\ValueObject\Combat\MatchOutcome;
use PHPUnit\Framework\TestCase;

class MatchOutcomeTest extends TestCase
{
    public function testToBattleResultMapsScores(): void
    {
        $this->assertSame(BattleResult::WinA, (new MatchOutcome(3, 1))->toBattleResult());
        $this->assertSame(BattleResult::WinB, (new MatchOutcome(0, 3))->toBattleResult());
        $this->assertSame(BattleResult::Draw, (new MatchOutcome(2, 2))->toBattleResult());
    }

    public function testForfeitFactorySetsFlag(): void
    {
        $outcome = MatchOutcome::forfeit(3, 0);

        $this->assertTrue($outcome->isForfeit());
        $this->assertSame(3, $outcome->getHomeScore());
        $this->assertSame(0, $outcome->getAwayScore());
        $this->assertSame('forfeit', $outcome->getCombatLog()['simulator']);
        $this->assertSame(['score_a' => 3, 'score_b' => 0], $outcome->getCombatLog()['result']);
        $this->assertNull($outcome->getSeed());
    }

    public function testCombatLogAndSeedAreStored(): void
    {
        $log = ['version' => 1, 'simulator' => 'combat_engine', 'events' => []];
        $outcome = new MatchOutcome(4, 2, false, $log, 99);

        $this->assertSame($log, $outcome->getCombatLog());
        $this->assertSame(99, $outcome->getSeed());
    }
}
