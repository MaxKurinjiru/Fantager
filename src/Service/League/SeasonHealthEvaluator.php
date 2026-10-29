<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Service\Config\SeasonHealthConfig;

class SeasonHealthEvaluator
{
    public function __construct(
        private readonly SeasonHealthConfig $config,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{metric: string, value: float|null, band_low: float|null, band_high: float|null, status: string}>
     */
    public function evaluate(array $payload): array
    {
        $rows = [];
        foreach ($this->config->getMetrics() as $key => $band) {
            $raw = $this->readPath($payload, $band['path']);
            $value = is_numeric($raw) ? (float) $raw : null;
            $status = 'ok';
            if (null === $value) {
                $status = 'missing';
            } elseif (null !== $band['min'] && $value < $band['min']) {
                $status = 'low';
            } elseif (null !== $band['max'] && $value > $band['max']) {
                $status = 'high';
            }

            $rows[] = [
                'metric' => $key,
                'value' => $value,
                'band_low' => $band['min'],
                'band_high' => $band['max'],
                'status' => $status,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readPath(array $payload, string $path): mixed
    {
        $cursor = $payload;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
