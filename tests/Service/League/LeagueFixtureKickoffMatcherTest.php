<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\Kingdom\Kingdom;
use App\Service\League\LeagueFixtureKickoffMatcher;
use PHPUnit\Framework\TestCase;

class LeagueFixtureKickoffMatcherTest extends TestCase
{
    public function testResolveMatchCandidatesForPragueSeparatesUtcAndLegacyWallClock(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setTimezone('Europe/Prague');

        $tickUtc = new \DateTimeImmutable('2026-06-09 16:00:00', new \DateTimeZone('UTC'));

        [$utcInstant, $legacyWallClockUtc] = LeagueFixtureKickoffMatcher::resolveMatchCandidates($kingdom, $tickUtc);

        $this->assertSame('2026-06-09T16:00:00+00:00', $utcInstant->format(\DateTimeInterface::ATOM));
        $this->assertSame('2026-06-09T18:00:00+00:00', $legacyWallClockUtc->format(\DateTimeInterface::ATOM));
    }

    public function testResolveMatchCandidatesForUtcKingdomUsesSameInstant(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setTimezone('UTC');

        $tickUtc = new \DateTimeImmutable('2026-06-09 18:00:00', new \DateTimeZone('UTC'));

        [$utcInstant, $legacyWallClockUtc] = LeagueFixtureKickoffMatcher::resolveMatchCandidates($kingdom, $tickUtc);

        $this->assertEquals($utcInstant, $legacyWallClockUtc);
    }
}
