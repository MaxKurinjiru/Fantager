<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Service\League\SeasonYearbookPresenter;
use PHPUnit\Framework\TestCase;

class SeasonYearbookPresenterTest extends TestCase
{
    public function testHidesOtherTeamsAndTreasury(): void
    {
        $presenter = new SeasonYearbookPresenter();
        $view = $presenter->presentForPlayer($this->payload(), 1);

        $this->assertNotNull($view);
        $this->assertSame('Wolves', $view['team']['name']);
        $this->assertArrayNotHasKey('royal_treasury_gold', $view['kingdom']);
        $this->assertArrayNotHasKey('health', $view);
        $this->assertArrayNotHasKey('teams', $view);
        $this->assertArrayNotHasKey('ledger', $view['team']);
        $this->assertSame(50.0, $view['versus']['points']['median']);
        $this->assertSame(10.0, $view['versus']['points']['delta']);
        $this->assertSame('Wolves', $view['highlights']['champion']);
        $this->assertSame(200, $view['team']['income']['arena']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $team = [
            'id' => 1,
            'name' => 'Wolves',
            'is_npc' => false,
            'npc_role' => null,
            'tier' => 'T1',
            'group' => 'T1-G1',
            'group_id' => 9,
            'position' => 1,
            'played' => 18,
            'wins' => 10,
            'draws' => 4,
            'losses' => 4,
            'points' => 60,
            'goal_difference' => 8,
            'status' => 'retained',
            'gold' => ['open' => 100, 'end' => 400, 'reward' => 50],
            'ledger' => ['arena_revenue' => 200, 'marketplace_sale' => 10],
            'morale' => 50,
            'reputation' => 20,
            'chemistry' => 40,
            'fan_base' => 400,
            'hq' => ['total_level' => 7, 'facilities' => []],
            'roster' => [
                'combat_ready' => 8,
                'trainers' => 1,
                'mean_level' => 4.0,
                'mean_ovr' => 45.0,
                'median_age' => 22.0,
                'at_mortality' => 0,
                'by_race' => ['human' => 4],
            ],
            'events' => [
                'summons' => 1,
                'buys' => 0,
                'sells' => 0,
                'dismissals' => 0,
                'combat_deaths' => 0,
                'crisis_changes' => 0,
            ],
        ];

        return [
            'kingdom' => [
                'name' => 'Test',
                'pace_preset' => 'classic',
                'team_count' => 2,
                'royal_treasury_gold' => 9999,
            ],
            'season' => ['number' => 1],
            'teams' => [
                $team,
                array_merge($team, ['id' => 2, 'name' => 'Bears', 'position' => 2, 'points' => 40]),
            ],
            'aggregates' => [
                'by_group' => [
                    'T1-G1' => [
                        'gold' => ['p50' => 300.0],
                        'ovr' => ['p50' => 40.0],
                        'age' => ['p50' => 20.0],
                        'level' => ['p50' => 4.0],
                        'points' => ['p50' => 50.0],
                    ],
                ],
                'by_tier' => [
                    'T1' => [
                        'gold' => ['p50' => 300.0],
                    ],
                ],
            ],
            'health' => [['metric' => 'median_gold', 'status' => 'high']],
        ];
    }
}
