<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Combat\Battle;
use App\Entity\League\LeagueFixture;
use App\Enum\LeagueFixtureStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Canonical entry point for marking a league fixture as completed.
 * Combat simulation should call this after creating the Battle entity.
 *
 * Temporary formation cleanup runs asynchronously via kingdom ticks
 * (see FixtureFormationService::cleanupStaleTemporaryFormationsForKingdom).
 */
class LeagueFixtureCompletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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
    }
}
