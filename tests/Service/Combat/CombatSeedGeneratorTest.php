<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueTier;
use App\Service\Combat\CombatSeedGenerator;
use PHPUnit\Framework\TestCase;

class CombatSeedGeneratorTest extends TestCase
{
    public function testSameFixtureContextYieldsSameSeed(): void
    {
        $generator = new CombatSeedGenerator();
        $fixture = $this->createFixture(7, 3, new \DateTimeImmutable('2026-06-17T18:00:00Z'));

        $this->assertSame(
            $generator->forLeagueFixture($fixture),
            $generator->forLeagueFixture($fixture),
        );
    }

    public function testEngineVersionChangesSeed(): void
    {
        $generator = new CombatSeedGenerator();
        $fixture = $this->createFixture(7, 3, new \DateTimeImmutable('2026-06-17T18:00:00Z'));

        $v1 = $generator->forLeagueFixture($fixture, 1);
        $v2 = $generator->forLeagueFixture($fixture, 2);

        $this->assertNotSame($v1, $v2);
    }

    private function createFixture(int $fixtureId, int $seasonId, \DateTimeImmutable $at): LeagueFixture
    {
        $season = (new LeagueSeason());
        $this->setEntityId($season, $seasonId);

        $tier = new LeagueTier();
        $tier->setSeason($season);

        $group = new LeagueGroup();
        $group->setTier($tier);

        $fixture = new LeagueFixture();
        $fixture->setGroup($group);
        $fixture->setScheduledAt($at);
        $this->setEntityId($fixture, $fixtureId);

        return $fixture;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }
}
