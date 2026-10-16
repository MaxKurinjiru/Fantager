<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\FacilityType;
use PHPUnit\Framework\TestCase;

/**
 * A6.5: summon_stat_total_cap stays computed but must not look like a live HQ bonus.
 */
class FacilityTypeTest extends TestCase
{
    public function testSummonStatTotalCapIsComputedAndUnread(): void
    {
        $all = FacilityType::SummoningChamber->getPassiveBonuses(5);

        $this->assertSame(2.0, $all['summon_base_stat_bonus']);
        $this->assertSame(5.0, $all['summon_stat_random_bonus']);
        $this->assertSame(35.0, $all['summon_stat_total_cap']);
        $this->assertTrue(FacilityType::isUnreadPassiveBonus('summon_stat_total_cap'));
        $this->assertFalse(FacilityType::isUnreadPassiveBonus('summon_base_stat_bonus'));

        $displayed = FacilityType::withoutUnreadBonuses($all);
        $this->assertArrayNotHasKey('summon_stat_total_cap', $displayed);
        $this->assertSame(2.0, $displayed['summon_base_stat_bonus']);
        $this->assertSame(5.0, $displayed['summon_stat_random_bonus']);
    }

    public function testOtherFacilitiesHaveNoUnreadBonuses(): void
    {
        $training = FacilityType::Training->getPassiveBonuses(3);
        $this->assertSame($training, FacilityType::withoutUnreadBonuses($training));
    }
}
