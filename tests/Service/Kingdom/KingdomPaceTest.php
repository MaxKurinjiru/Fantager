<?php

declare(strict_types=1);

namespace App\Tests\Service\Kingdom;

use App\Entity\Kingdom\Kingdom;
use App\Enum\PacePreset;
use App\Service\Kingdom\KingdomPace;
use PHPUnit\Framework\TestCase;

class KingdomPaceTest extends TestCase
{
    public function testClassicDefaultsAreOne(): void
    {
        $kingdom = new Kingdom();

        $this->assertSame(PacePreset::Classic, $kingdom->getPacePreset());
        $this->assertSame(1.0, KingdomPace::training($kingdom));
        $this->assertSame(1.0, KingdomPace::recovery($kingdom));
        $this->assertSame(1.0, KingdomPace::aging($kingdom));
        $this->assertSame(1.0, KingdomPace::construction($kingdom));
        $this->assertSame(1.0, KingdomPace::economy($kingdom));
        $this->assertSame(1.0, KingdomPace::fans($kingdom));
        $this->assertSame(1, KingdomPace::summonCap($kingdom));
    }

    public function testStoredPacesAreIndependent(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setPacePreset(PacePreset::FastSeason);
        $kingdom->setTrainingSpeed('1.50');
        $kingdom->setRecoverySpeed('1.50');
        $kingdom->setAgingSpeed('1.00');
        $kingdom->setConstructionSpeed('2.00');
        $kingdom->setEconomyScale('1.00');
        $kingdom->setFanPace('1.00');
        $kingdom->setSummonCap(1);

        $this->assertSame(1.5, KingdomPace::pace($kingdom, 'training'));
        $this->assertSame(2.0, KingdomPace::construction($kingdom));
        $this->assertSame(1, KingdomPace::summonCap($kingdom));
    }

    public function testNonPositivePaceFallsBackToOneAndSummonCapStaysAtLeastOne(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setTrainingSpeed('0.00');
        $kingdom->setSummonCap(0);

        $this->assertSame(1.0, KingdomPace::training($kingdom));
        $this->assertSame(1, $kingdom->getSummonCap());
    }

    public function testUnknownPaceNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        KingdomPace::pace(new Kingdom(), 'calendar');
    }
}
