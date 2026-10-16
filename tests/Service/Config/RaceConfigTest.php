<?php

declare(strict_types=1);

namespace App\Tests\Service\Config;

use App\Enum\Race;
use App\Service\Config\RaceConfig;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RaceConfigTest extends KernelTestCase
{
    private RaceConfig $raceConfig;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var RaceConfig $config */
        $config = self::getContainer()->get(RaceConfig::class);
        $this->raceConfig = $config;
    }

    public function testHumanMortalityThreshold(): void
    {
        $this->assertSame(80, $this->raceConfig->getMortalityThreshold(Race::Human));
    }

    public function testGenieAdaptationUsesHigherRelationshipBar(): void
    {
        $this->assertEqualsCanonicalizing(
            [Race::Human, Race::Elf, Race::Genie],
            $this->raceConfig->getCompatibleRacesForAdaptation(Race::Genie),
        );
        $this->assertNotContains(Race::Undead, $this->raceConfig->getCompatibleRacesForAdaptation(Race::Genie));
        $this->assertCount(8, $this->raceConfig->getCompatibleRaces(Race::Genie, RaceConfig::COMPATIBLE_RACE_MIN));
    }

    public function testNonGenieAdaptationKeepsNeutralRelationshipBar(): void
    {
        $this->assertEqualsCanonicalizing(
            [Race::Human, Race::Elf, Race::Dwarf, Race::Ent, Race::Genie],
            $this->raceConfig->getCompatibleRacesForAdaptation(Race::Human),
        );
        $this->assertNotContains(Race::Orc, $this->raceConfig->getCompatibleRacesForAdaptation(Race::Human));
    }

    public function testResolveAgePhaseUsesMortalityThreshold(): void
    {
        $this->assertSame('Veteran', $this->raceConfig->resolveAgePhase(Race::Human, 79));
        $this->assertSame('Elder', $this->raceConfig->resolveAgePhase(Race::Human, 80));
        $this->assertTrue($this->raceConfig->isAtOrAboveMortalityThreshold(Race::Human, 80));
        $this->assertFalse($this->raceConfig->isAtOrAboveMortalityThreshold(Race::Human, 79));
    }

    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }
}
