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
    }
}
