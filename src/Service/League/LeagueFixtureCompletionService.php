<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Combat\Battle;
use App\Entity\League\LeagueFixture;
use App\Enum\LeagueFixtureStatus;
use App\Service\Formation\FixtureFormationService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Canonical entry point for marking a league fixture as completed.
 * Called from forfeit resolution and from completeBattle after post-match rewards.
 *
 * Temporary match-specific formations are deleted here (after Completed), not at kickoff.
 * DailyReset still sweeps orphans via FixtureFormationService::cleanupStaleTemporaryFormationsForTeam.
 */
class LeagueFixtureCompletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FixtureFormationService $fixtureFormationService,
    ) {
    }

    public function complete(LeagueFixture $fixture, Battle $battle): void
    {
        if (LeagueFixtureStatus::Completed === $fixture->getStatus()) {
            return;
        }

        $fixture->setBattle($battle);
        $fixture->setStatus(LeagueFixtureStatus::Completed);
        $this->em->flush();

        $this->fixtureFormationService->cleanupTemporaryFormationsAfterCompletion($fixture);
    }
}
