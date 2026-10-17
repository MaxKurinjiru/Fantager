<?php

declare(strict_types=1);

namespace App\Service\Kingdom;

use App\Entity\Kingdom\Kingdom;

/**
 * Reads the pace values stored on a kingdom. Those values are copied from a
 * pace preset at creation and are never inherited from a separate game speed.
 */
final class KingdomPace
{
    public static function training(Kingdom $kingdom): float
    {
        return self::positive($kingdom->getTrainingSpeed());
    }

    public static function recovery(Kingdom $kingdom): float
    {
        return self::positive($kingdom->getRecoverySpeed());
    }

    public static function aging(Kingdom $kingdom): float
    {
        return self::positive($kingdom->getAgingSpeed());
    }

    public static function construction(Kingdom $kingdom): float
    {
        return self::positive($kingdom->getConstructionSpeed());
    }

    public static function economy(Kingdom $kingdom): float
    {
        return self::positive($kingdom->getEconomyScale());
    }

    public static function fans(Kingdom $kingdom): float
    {
        return self::positive($kingdom->getFanPace());
    }

    public static function summonCap(Kingdom $kingdom): int
    {
        return max(1, $kingdom->getSummonCap());
    }

    public static function pace(Kingdom $kingdom, string $name): float
    {
        return match ($name) {
            'training' => self::training($kingdom),
            'recovery' => self::recovery($kingdom),
            'aging' => self::aging($kingdom),
            'construction' => self::construction($kingdom),
            'economy' => self::economy($kingdom),
            'fans' => self::fans($kingdom),
            default => throw new \InvalidArgumentException(sprintf('Unknown kingdom pace "%s".', $name)),
        };
    }

    public static function format(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function positive(string $raw): float
    {
        $value = (float) $raw;

        return $value > 0.0 ? $value : 1.0;
    }
}
