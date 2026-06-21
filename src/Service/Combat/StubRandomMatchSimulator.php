<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\League\LeagueFixture;
use App\ValueObject\Combat\MatchOutcome;

/**
 * Placeholder combat engine: random kill scores (0–6) until the real simulator ships.
 */
class StubRandomMatchSimulator implements MatchSimulatorInterface
{
    public function simulate(LeagueFixture $fixture): MatchOutcome
    {
        return new MatchOutcome(
            random_int(0, 6),
            random_int(0, 6),
        );
    }
}
