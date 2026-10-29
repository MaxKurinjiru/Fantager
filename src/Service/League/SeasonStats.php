<?php

declare(strict_types=1);

namespace App\Service\League;

final class SeasonStats
{
    /**
     * @param list<int|float> $values
     */
    public static function percentile(array $values, float $percent): ?float
    {
        if ([] === $values) {
            return null;
        }

        sort($values);
        $n = count($values);
        $index = (int) round(($percent / 100.0) * ($n - 1));
        $index = max(0, min($n - 1, $index));

        return (float) $values[$index];
    }

    /**
     * @param list<int|float> $values
     */
    public static function median(array $values): ?float
    {
        return self::percentile($values, 50.0);
    }

    /**
     * @param list<int|float> $values
     */
    public static function mean(array $values): ?float
    {
        if ([] === $values) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * Gini coefficient for non-negative values. All zeros → 0.
     *
     * @param list<int|float> $values
     */
    public static function gini(array $values): ?float
    {
        if ([] === $values) {
            return null;
        }

        $n = count($values);
        $sum = array_sum($values);
        if ($sum <= 0) {
            return 0.0;
        }

        sort($values);
        $weighted = 0.0;
        foreach ($values as $i => $value) {
            $weighted += ($i + 1) * (float) $value;
        }

        return ((2.0 * $weighted) / ($n * $sum)) - (($n + 1) / $n);
    }

    /**
     * @param list<int|float> $values
     *
     * @return array{p25: float|null, p50: float|null, p75: float|null}
     */
    public static function spread(array $values): array
    {
        return [
            'p25' => self::percentile($values, 25.0),
            'p50' => self::median($values),
            'p75' => self::percentile($values, 75.0),
        ];
    }
}
