<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\League\LeagueFixture;
use App\ValueObject\Combat\MatchOutcome;

interface MatchSimulatorInterface
{
    public function simulate(LeagueFixture $fixture): MatchOutcome;
}
