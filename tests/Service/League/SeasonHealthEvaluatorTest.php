<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Service\Config\SeasonHealthConfig;
use App\Service\League\SeasonHealthEvaluator;
use PHPUnit\Framework\TestCase;

class SeasonHealthEvaluatorTest extends TestCase
{
    public function testFlagsOutOfBandAndMissing(): void
    {
        $config = new SeasonHealthConfig(dirname(__DIR__, 3));
        $evaluator = new SeasonHealthEvaluator($config);

        $payload = [
            'aggregates' => [
                'kingdom' => [
                    'gold' => ['p50' => 999999],
                    'gold_gini' => 0.1,
                    'age' => ['p50' => 20],
                    'level' => ['p50' => 3],
                    'ovr' => ['p50' => 40],
                    'crisis_count' => 0,
                ],
            ],
            'combat' => [
                'home_win_rate' => 0.9,
                'round_200_share' => 0.1,
                'forfeit_rate' => 0.05,
            ],
            'aging' => ['combat_deaths' => 2],
            'market' => ['volume_gold' => 100],
            'league' => ['yo_yo_count' => 0],
        ];

        $rows = $evaluator->evaluate($payload);
        $byMetric = [];
        foreach ($rows as $row) {
            $byMetric[$row['metric']] = $row;
        }

        $this->assertSame('high', $byMetric['median_gold']['status']);
        $this->assertSame('high', $byMetric['home_win_rate']['status']);
        $this->assertSame('ok', $byMetric['median_combatant_age']['status']);
    }
}
