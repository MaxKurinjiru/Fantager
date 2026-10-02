<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\League\LeagueFixture;
use App\ValueObject\Combat\MatchOutcome;

/**
 * @deprecated Kept for reference; league wiring uses {@see LeagueMatchSimulator}.
 *
 * Placeholder combat engine: random kill scores (0–6). Trait hooks and real
 * resolution live in {@see CombatEngine}.
 */
class StubRandomMatchSimulator implements MatchSimulatorInterface
{
    public function simulate(LeagueFixture $fixture): MatchOutcome
    {
        return new MatchOutcome(
            random_int(0, 6),
            random_int(0, 6),
            false,
            [
                'version' => 1,
                'simulator' => 'stub_random',
                'events' => [],
            ],
        );
    }
}
