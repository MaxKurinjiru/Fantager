<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\League\LeagueFixture;

/**
 * Deterministic u32 seed for league fixtures (see combat-system Simulation Contract).
 */
final class CombatSeedGenerator
{
    public function forLeagueFixture(LeagueFixture $fixture, int $engineVersion = CombatEngine::ENGINE_VERSION): int
    {
        $fixtureId = $fixture->getId() ?? 0;
        $seasonId = $fixture->getGroup()->getTier()->getSeason()->getId() ?? 0;
        $scheduledAt = $fixture->getScheduledAt()->getTimestamp();

        $material = sprintf('%d|%d|%d|%d', $fixtureId, $seasonId, $scheduledAt, $engineVersion);
        $hex = substr(hash('sha256', $material), 0, 8);

        return (int) (hexdec($hex) & 0x7FFFFFFF);
    }
}
