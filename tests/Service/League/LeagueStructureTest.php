<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueTier;
use App\Service\League\LeagueStructure;
use PHPUnit\Framework\TestCase;

class LeagueStructureTest extends TestCase
{
    public function testShippedDefaultsMatchTheLock(): void
    {
        LeagueStructure::assertCreationConfig($this->shippedConfig());
        LeagueStructure::assertSeasonLength(LeagueStructure::SEASON_LENGTH_DAYS);
        $this->addToAssertionCount(1);
    }

    public function testCreationConfigRejectsAnotherGroupSize(): void
    {
        $config = $this->shippedConfig();
        $config['teams_per_group'] = 8;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('10 teams');

        LeagueStructure::assertCreationConfig($config);
    }

    public function testCreationConfigRejectsADifferentPyramid(): void
    {
        $config = $this->shippedConfig();
        $config['tiers'][0]['relegation_slots'] = 3;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('T1');

        LeagueStructure::assertCreationConfig($config);
    }

    public function testSeasonLengthRejectsOtherLengths(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('77');

        LeagueStructure::assertSeasonLength(70);
    }

    public function testSameShapeAcceptsACopiedPyramid(): void
    {
        LeagueStructure::assertSameShape($this->seasonWithT1Relegation(4), $this->seasonWithT1Relegation(4));
        $this->addToAssertionCount(1);
    }

    public function testSameShapeRejectsSlotDrift(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('promotion or relegation');

        LeagueStructure::assertSameShape($this->seasonWithT1Relegation(4), $this->seasonWithT1Relegation(3));
    }

    /**
     * @return array<string, mixed>
     */
    private function shippedConfig(): array
    {
        $config = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/config/kingdom/league_tiers.defaults.json'), true);
        $this->assertIsArray($config);

        return $config;
    }

    private function seasonWithT1Relegation(int $relegation): LeagueSeason
    {
        $season = new LeagueSeason();
        foreach (LeagueStructure::TIERS as $index => $def) {
            $tier = new LeagueTier();
            $this->setId($tier, $index + 1);
            $tier->setTierName($def['name']);
            $tier->setPromotionSlots($def['promotion_slots']);
            $tier->setRelegationSlots(0 === $index ? $relegation : $def['relegation_slots']);
            $season->addTier($tier);
            for ($g = 0; $g < $def['groups']; ++$g) {
                $group = new LeagueGroup();
                $group->setGroupName($def['name'].'-'.$g);
                $tier->addGroup($group);
            }
        }

        return $season;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
