<?php

declare(strict_types=1);

namespace App\Service\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads expected season-report metric bands from config/game/season-health.yaml.
 */
class SeasonHealthConfig
{
    /** @var array<string, array{path: string, min: float|null, max: float|null}> */
    private array $metrics = [];

    public function __construct(string $projectDir)
    {
        /** @var array{metrics?: array<string, array<string, mixed>>} $parsed */
        $parsed = Yaml::parseFile($projectDir.'/config/game/season-health.yaml');

        foreach ($parsed['metrics'] ?? [] as $key => $row) {
            $this->metrics[$key] = [
                'path' => (string) ($row['path'] ?? $key),
                'min' => isset($row['min']) ? (float) $row['min'] : null,
                'max' => isset($row['max']) ? (float) $row['max'] : null,
            ];
        }
    }

    /**
     * @return array<string, array{path: string, min: float|null, max: float|null}>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
