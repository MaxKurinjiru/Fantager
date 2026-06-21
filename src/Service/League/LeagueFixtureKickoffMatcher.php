<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Kingdom\Kingdom;

/**
 * Aligns league-match tick instants (UTC) with fixture kickoff values stored in the database.
 */
final class LeagueFixtureKickoffMatcher
{
    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} UTC instant and legacy naive wall-clock UTC
     */
    public static function resolveMatchCandidates(Kingdom $kingdom, \DateTimeImmutable $tickUtc): array
    {
        try {
            $tz = new \DateTimeZone($kingdom->getTimezone());
        } catch (\Exception) {
            $tz = new \DateTimeZone('UTC');
        }

        $local = $tickUtc->setTimezone($tz);
        $legacyWallClockUtc = new \DateTimeImmutable(
            $local->format('Y-m-d H:i:s'),
            new \DateTimeZone('UTC'),
        );

        return [$tickUtc, $legacyWallClockUtc];
    }
}
