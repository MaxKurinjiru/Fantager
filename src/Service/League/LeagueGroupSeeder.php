<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;

/**
 * Deals next-season groups by previous finishing place.
 *
 * Teams are ranked, cut into strength pots, shuffled inside each pot, then
 * dealt one team per group. Each group gets one team from every strength band.
 */
class LeagueGroupSeeder
{
    /**
     * @param list<array{team: Team, position: int, standing: LeagueStanding}> $entries
     * @param callable(LeagueStanding, LeagueStanding): int                     $compareStandings
     * @param null|callable(list<Team>): list<Team>                            $shuffleTeams
     *
     * @return list<list<Team>> index-aligned with the destination groups
     */
    public function distribute(array $entries, int $groupCount, callable $compareStandings, ?callable $shuffleTeams = null): array
    {
        if ($groupCount < 1) {
            return [];
        }

        /** @var list<list<Team>> $groups */
        $groups = array_fill(0, $groupCount, []);
        if ([] === $entries) {
            return $groups;
        }

        usort($entries, static function (array $a, array $b) use ($compareStandings): int {
            if ($a['position'] !== $b['position']) {
                return $a['position'] <=> $b['position'];
            }

            return $compareStandings($a['standing'], $b['standing']);
        });

        $shuffleTeams ??= static function (array $teams): array {
            shuffle($teams);

            return $teams;
        };

        /** @var list<Team> $ranked */
        $ranked = array_map(static fn (array $entry): Team => $entry['team'], $entries);

        foreach (array_chunk($ranked, $groupCount) as $pot) {
            /** @var list<Team> $pot */
            $pot = $shuffleTeams($pot);
            foreach ($pot as $index => $team) {
                $groups[$index][] = $team;
            }
        }

        return $groups;
    }
}
