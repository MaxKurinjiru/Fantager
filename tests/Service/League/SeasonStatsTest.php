<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Service\League\SeasonStats;
use PHPUnit\Framework\TestCase;

class SeasonStatsTest extends TestCase
{
    public function testMedianOfOddList(): void
    {
        $this->assertSame(2.0, SeasonStats::median([1, 3, 2]));
    }

    public function testEmptyMeanIsNull(): void
    {
        $this->assertNull(SeasonStats::mean([]));
        $this->assertNull(SeasonStats::median([]));
        $this->assertNull(SeasonStats::gini([]));
    }

    public function testGiniAllEqualIsZero(): void
    {
        $this->assertEqualsWithDelta(0.0, SeasonStats::gini([10, 10, 10]), 0.0001);
    }

    public function testSpreadPercentiles(): void
    {
        $spread = SeasonStats::spread([1, 2, 3, 4]);
        $this->assertSame(2.0, $spread['p25']);
        $this->assertSame(3.0, $spread['p50']);
        $this->assertSame(3.0, $spread['p75']);
    }
}
