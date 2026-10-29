<?php

declare(strict_types=1);

namespace App\Service\League;

class SeasonYearbookPresenter
{
    /**
     * Player-facing subset: own team plus public group/tier medians. No wallets, treasury, or health flags.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    public function presentForPlayer(array $payload, ?int $teamId): ?array
    {
        $teams = $payload['teams'] ?? [];
        if (!is_array($teams) || [] === $teams) {
            return null;
        }

        $own = null;
        if (null !== $teamId) {
            foreach ($teams as $row) {
                if (is_array($row) && (int) ($row['id'] ?? 0) === $teamId) {
                    $own = $row;
                    break;
                }
            }
        }

        $groupKey = is_array($own) ? (string) ($own['group'] ?? '') : '';
        $tierKey = is_array($own) ? (string) ($own['tier'] ?? '') : '';
        $groupAgg = is_array($payload['aggregates']['by_group'][$groupKey] ?? null)
            ? $payload['aggregates']['by_group'][$groupKey]
            : null;
        $tierAgg = is_array($payload['aggregates']['by_tier'][$tierKey] ?? null)
            ? $payload['aggregates']['by_tier'][$tierKey]
            : null;

        $highlights = $this->publicHighlights(array_values($teams), $groupKey);

        return [
            'season' => $payload['season'] ?? null,
            'kingdom' => [
                'name' => $payload['kingdom']['name'] ?? null,
                'pace_preset' => $payload['kingdom']['pace_preset'] ?? null,
                'team_count' => $payload['kingdom']['team_count'] ?? null,
            ],
            'team' => null === $own ? null : $this->publicTeam($own),
            'versus' => null === $own || !is_array($groupAgg) ? null : [
                'group_name' => $groupKey,
                'tier_name' => $tierKey,
                'points' => $this->delta((int) $own['points'], $groupAgg['points']['p50'] ?? null),
                'gold' => $this->delta((int) $own['gold']['end'], $groupAgg['gold']['p50'] ?? null),
                'ovr' => $this->delta(
                    is_numeric($own['roster']['mean_ovr'] ?? null) ? (float) $own['roster']['mean_ovr'] : null,
                    $groupAgg['ovr']['p50'] ?? null,
                ),
                'age' => $this->delta(
                    is_numeric($own['roster']['median_age'] ?? null) ? (float) $own['roster']['median_age'] : null,
                    $groupAgg['age']['p50'] ?? null,
                ),
                'level' => $this->delta(
                    is_numeric($own['roster']['mean_level'] ?? null) ? (float) $own['roster']['mean_level'] : null,
                    $groupAgg['level']['p50'] ?? null,
                ),
            ],
            'group' => $groupAgg,
            'tier' => $tierAgg,
            'highlights' => $highlights,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function publicTeam(array $row): array
    {
        $ledger = is_array($row['ledger'] ?? null) ? $row['ledger'] : [];

        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'tier' => $row['tier'],
            'group' => $row['group'],
            'position' => $row['position'],
            'played' => $row['played'],
            'wins' => $row['wins'],
            'draws' => $row['draws'],
            'losses' => $row['losses'],
            'points' => $row['points'],
            'goal_difference' => $row['goal_difference'],
            'status' => $row['status'],
            'gold' => $row['gold'],
            'income' => [
                'arena' => (int) ($ledger['arena_revenue'] ?? 0),
                'marketplace' => (int) ($ledger['marketplace_sale'] ?? 0),
                'kingdom_reward' => (int) ($ledger['kingdom_reward'] ?? 0),
                'league_reward' => (int) ($ledger['league_reward'] ?? 0),
            ],
            'morale' => $row['morale'],
            'reputation' => $row['reputation'],
            'chemistry' => $row['chemistry'],
            'fan_base' => $row['fan_base'],
            'hq_total_level' => $row['hq']['total_level'] ?? 0,
            'roster' => $row['roster'],
            'events' => $row['events'],
        ];
    }

    /**
     * @param list<mixed> $teams
     *
     * @return array{champion: string|null, best_goal_difference: string|null}
     */
    private function publicHighlights(array $teams, string $groupKey): array
    {
        $champion = null;
        $bestGdName = null;
        $bestGd = null;
        foreach ($teams as $row) {
            if (!is_array($row) || (string) ($row['group'] ?? '') !== $groupKey) {
                continue;
            }
            if (1 === (int) ($row['position'] ?? 0)) {
                $champion = (string) ($row['name'] ?? '');
            }
            $gd = (int) ($row['goal_difference'] ?? 0);
            if (null === $bestGd || $gd > $bestGd) {
                $bestGd = $gd;
                $bestGdName = (string) ($row['name'] ?? '');
            }
        }

        return [
            'champion' => $champion,
            'best_goal_difference' => $bestGdName,
        ];
    }

    /**
     * @return array{value: float|null, median: float|null, delta: float|null}
     */
    private function delta(int|float|null $value, mixed $median): array
    {
        $medianNum = is_numeric($median) ? (float) $median : null;
        $valueNum = is_numeric($value) ? (float) $value : null;

        return [
            'value' => $valueNum,
            'median' => $medianNum,
            'delta' => null !== $valueNum && null !== $medianNum ? $valueNum - $medianNum : null,
        ];
    }
}
