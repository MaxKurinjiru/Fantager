<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Formation\Formation;
use App\Entity\League\LeagueFixture;
use App\Enum\MatchType;
use App\Service\Formation\MatchLineupService;
use App\ValueObject\Combat\MatchOutcome;

/**
 * League adapter: resolve kickoff lineups → build request → {@see CombatEngine}.
 */
class LeagueMatchSimulator implements MatchSimulatorInterface
{
    public function __construct(
        private readonly MatchLineupService $matchLineupService,
        private readonly CombatMatchRequestBuilder $requestBuilder,
        private readonly CombatSeedGenerator $seedGenerator,
        private readonly CombatEngine $combatEngine,
    ) {
    }

    public function simulate(LeagueFixture $fixture): MatchOutcome
    {
        $homeFormation = $this->matchLineupService->resolveForKickoff(
            $fixture->getHomeTeam(),
            $fixture->getHomeFormation(),
            $fixture,
        );
        $awayFormation = $this->matchLineupService->resolveForKickoff(
            $fixture->getAwayTeam(),
            $fixture->getAwayFormation(),
            $fixture,
        );

        if (!$homeFormation instanceof Formation || !$awayFormation instanceof Formation) {
            throw new \RuntimeException(sprintf('Fixture %d cannot be simulated without two match-ready lineups.', $fixture->getId() ?? 0));
        }

        $seed = $this->seedGenerator->forLeagueFixture($fixture);
        $request = $this->requestBuilder->fromFormations(
            $homeFormation,
            $awayFormation,
            MatchType::League,
            $seed,
        );

        return $this->combatEngine->simulate($request)->toMatchOutcome();
    }
}
