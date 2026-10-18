<?php

declare(strict_types=1);

namespace App\Tests\Service\Hero;

use App\Enum\HeroTrait;
use App\Service\Hero\KnockoutOutcome;
use PHPUnit\Framework\TestCase;

class KnockoutOutcomeTest extends TestCase
{
    private KnockoutOutcome $outcome;

    protected function setUp(): void
    {
        $this->outcome = new KnockoutOutcome();
    }

    public function testBattleHardenedHalvesKnockoutYears(): void
    {
        $this->assertSame(1, $this->outcome->yearsAdded(1, null));
        $this->assertSame(0, $this->outcome->yearsAdded(1, HeroTrait::BattleHardened));
        $this->assertSame(3, $this->outcome->yearsAdded(2, null));
        $this->assertSame(1, $this->outcome->yearsAdded(2, HeroTrait::BattleHardened));
        $this->assertSame(6, $this->outcome->yearsAdded(3, null));
        $this->assertSame(3, $this->outcome->yearsAdded(3, HeroTrait::BattleHardened));
    }

    public function testVolatileRaisesElderDeathChanceByTenPoints(): void
    {
        $this->assertEqualsWithDelta(0.05, $this->outcome->permanentDeathChance(0, null), 0.0001);
        $this->assertEqualsWithDelta(0.15, $this->outcome->permanentDeathChance(0, HeroTrait::Volatile), 0.0001);
        $this->assertSame(1.0, $this->outcome->permanentDeathChance(30, HeroTrait::Volatile));
    }
}
