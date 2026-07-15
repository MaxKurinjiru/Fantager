<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\League\LeagueFixture;
use App\Enum\MatchType;
use App\Service\Formation\FixtureFormationService;
use App\ValueObject\Combat\MatchOutcome;

/**
 * League adapter: resolve formations → build request → {@see CombatEngine}.
 */
class LeagueMatchSimulator implements MatchSimulatorInterface
{
    public function __construct(
        private readonly FixtureFormationService $fixtureFormationService,
        private readonly CombatMatchRequestBuilder $requestBuilder,
        private readonly CombatSeedGenerator $seedGenerator,
        private readonly CombatEngine $combatEngine,
    ) {
    }

    public function simulate(LeagueFixture $fixture): MatchOutcome
    {
        $homeFormation = $this->fixtureFormationService->resolveFormation(
            $fixture,
            $fixture->getHomeTeam(),
        );
        $awayFormation = $this->fixtureFormationService->resolveFormation(
            $fixture,
            $fixture->getAwayTeam(),
        );

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
