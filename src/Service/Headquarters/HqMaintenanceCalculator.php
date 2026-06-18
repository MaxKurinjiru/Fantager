<?php

declare(strict_types=1);

namespace App\Service\Headquarters;

use App\Entity\Headquarters\Headquarters;

final class HqMaintenanceCalculator
{
    private const HQ_WEEKLY_MAINTENANCE_BASE = 50;

    /** @var array<string, int> */
    private const FACILITY_WEEKLY_MAINTENANCE = [
        'training' => 25,
        'medical' => 20,
        'library' => 30,
        'treasury' => 22,
        'barracks' => 18,
        'summoning_chamber' => 40,
        'arena' => 45,
    ];

    private const HQ_MAINTENANCE_PER_TOTAL_LEVEL = 3;

    /**
     * @return array{total: int, hq: int, facilities: int}
     */
    public static function calculateWeeklyMaintenanceBreakdown(Headquarters $hq): array
    {
        $totalLevel = $hq->getComputedTotalLevel();
        $hqFee = self::HQ_WEEKLY_MAINTENANCE_BASE + ($totalLevel * self::HQ_MAINTENANCE_PER_TOTAL_LEVEL);

        $facilitiesFee = 0;
        foreach ($hq->getFacilities() as $facility) {
            $base = self::FACILITY_WEEKLY_MAINTENANCE[$facility->getType()->value];
            $facilitiesFee += $base * $facility->getLevel();
        }

        $speed = 1.0;
        try {
            $team = $hq->getTeam();
            $kingdom = $team->getKingdom();
            $speed = (float) $kingdom->getGameSpeed();
        } catch (\Throwable) {
            $speed = 1.0;
        }
        if ($speed <= 0.0) {
            $speed = 1.0;
        }

        $hqFeeScaled = (int) round($hqFee * $speed);
        $facilitiesFeeScaled = (int) round($facilitiesFee * $speed);

        return [
            'total' => $hqFeeScaled + $facilitiesFeeScaled,
            'hq' => $hqFeeScaled,
            'facilities' => $facilitiesFeeScaled,
        ];
    }

    public static function calculateWeeklyMaintenanceFee(Headquarters $hq): int
    {
        return self::calculateWeeklyMaintenanceBreakdown($hq)['total'];
    }
}
