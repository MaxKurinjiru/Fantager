<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\Team\Team;
use App\Enum\LeagueFixtureStatus;
use Doctrine\ORM\EntityManagerInterface;

class LeagueFixtureScheduler
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Schedules 18 rounds of league fixtures for a group of teams.
     * Ensures weekly home/away balance and double round-robin constraints.
     */
    public function scheduleFixturesForGroup(LeagueGroup $group, \DateTimeImmutable $startDate): void
    {
        /** @var list<Team> $teams */
        $teams = [];
        foreach ($group->getStandings() as $standing) {
            $teams[] = $standing->getTeam();
        }

        $N = count($teams);
        if ($N < 2 || 0 !== $N % 2) {
            throw new \InvalidArgumentException('The number of teams in a group must be an even number >= 2.');
        }

        // 1. Generate 9 rounds of pairings using the standard Circle Method
        $roundsPairings = $this->generateCirclePairings($teams);

        // 2. Leg 1 and Leg 2 (Leg 2 rounds are in reverse order to make Home/Away balancing solvable)
        $allRoundsPairings = [];
        for ($r = 0; $r < 9; ++$r) {
            $allRoundsPairings[$r] = $roundsPairings[$r];          // Leg 1
            $allRoundsPairings[$r + 9] = $roundsPairings[8 - $r];  // Leg 2 (reversed)
        }

        // 3. Solve Home/Away assignments using a backtracking solver to satisfy constraints:
        // - In each week (Rounds 2w and 2w+1), every team plays exactly 1 Home and 1 Away.
        // - Across the 18 rounds, the two matches for each matchup must have opposite Home/Away status.
        $assignments = $this->solveHomeAway($allRoundsPairings, $N);
        if (null === $assignments) {
            throw new \RuntimeException('Failed to generate a valid league schedule satisfying Home/Away constraints.');
        }

        // 4. Calculate calendar dates aligned to Monday of the next calendar week
        $prepMonday = (1 === (int) $startDate->format('N'))
            ? $startDate->setTime(0, 0, 0)
            : $startDate->modify('next monday')->setTime(0, 0, 0);

        for ($r = 0; $r < 18; ++$r) {
            $wPlay = (int) ($r / 2); // 0-indexed week of play (0 to 8)
            $calendarWeek = $wPlay + 2; // Week 1 is prep, play starts in Week 2 (calendar weeks 2 to 10)

            $mondayOfWeek = $prepMonday->modify(sprintf('+%d weeks', $calendarWeek - 1));

            $dayOffset = (0 === $r % 2) ? 1 : 4; // Tuesday (Monday + 1) or Friday (Monday + 4)
            $scheduledAt = $mondayOfWeek->modify(sprintf('+%d days', $dayOffset))->setTime(18, 0, 0);

            $pairings = $allRoundsPairings[$r];
            foreach ($pairings as $m => $pair) {
                [$teamA, $teamB] = $pair;
                $homeTeam = $assignments[$r][$m] ?? null;
                if (!$homeTeam instanceof Team) {
                    throw new \RuntimeException('Home team assignment is missing or invalid.');
                }
                $awayTeam = ($homeTeam === $teamA) ? $teamB : $teamA;

                $fixture = new LeagueFixture();
                $fixture->setGroup($group);
                $fixture->setHomeTeam($homeTeam);
                $fixture->setAwayTeam($awayTeam);
                $fixture->setScheduledAt($scheduledAt);
                $fixture->setStatus(LeagueFixtureStatus::Scheduled);

                $group->getFixtures()->add($fixture);
                $this->em->persist($fixture);
            }
        }
    }

    /**
     * @param list<Team> $teams
     *
     * @return array<int, list<array{0: Team, 1: Team}>>
     */
    private function generateCirclePairings(array $teams): array
    {
        $N = count($teams);
        /** @var list<Team> $R */
        $R = [];
        for ($i = 1; $i < $N; ++$i) {
            $R[] = $teams[$i];
        }

        $rounds = [];
        for ($r = 0; $r < $N - 1; ++$r) {
            $pairings = [];
            // Fix the first team
            $firstTeam = $teams[0] ?? null;
            $rFirst = $R[0] ?? null;
            if ($firstTeam instanceof Team && $rFirst instanceof Team) {
                $pairings[] = [$firstTeam, $rFirst];
            }
            for ($k = 1; $k < $N / 2; ++$k) {
                $team1 = $R[$k] ?? null;
                $team2 = $R[count($R) - $k] ?? null;
                if ($team1 instanceof Team && $team2 instanceof Team) {
                    $pairings[] = [$team1, $team2];
                }
            }
            $rounds[$r] = $pairings;

            // Rotate R right
            $last = array_pop($R);
            if ($last instanceof Team) {
                array_unshift($R, $last);
            }
        }

        return $rounds;
    }

    /**
     * @param array<int, list<array{0: Team, 1: Team}>> $roundsPairings
     *
     * @return array<int, array<int, Team|null>>|null Maps round -> match index -> home team
     */
    private function solveHomeAway(array $roundsPairings, int $N): ?array
    {
        $assignedHome = [];
        for ($r = 0; $r < 18; ++$r) {
            $assignedHome[$r] = array_fill(0, (int) ($N / 2), null);
        }

        // State trackers
        $teamHomeCountInWeek = [];
        $teamAwayCountInWeek = [];
        for ($w = 0; $w < 9; ++$w) {
            $teamHomeCountInWeek[$w] = [];
            $teamAwayCountInWeek[$w] = [];
        }

        // Maps sorted team ID pair string (e.g. "5-12") -> Team that was Home in Leg 1
        $matchupHomeTeam = [];

        if ($this->backtrack($roundsPairings, $assignedHome, $teamHomeCountInWeek, $teamAwayCountInWeek, $matchupHomeTeam, 0, 0, $N)) {
            return $assignedHome;
        }

        return null;
    }

    /**
     * @param array<int, list<array{0: Team, 1: Team}>> $roundsPairings
     * @param array<int, array<int, Team|null>>         $assignedHome
     * @param array<int, array<int, int>>               $teamHomeCountInWeek
     * @param array<int, array<int, int>>               $teamAwayCountInWeek
     * @param array<string, int>                        $matchupHomeTeam
     */
    private function backtrack(
        array $roundsPairings,
        array &$assignedHome,
        array &$teamHomeCountInWeek,
        array &$teamAwayCountInWeek,
        array &$matchupHomeTeam,
        int $roundIndex,
        int $matchIndex,
        int $N,
    ): bool {
        if (18 === $roundIndex) {
            return true;
        }

        if ($matchIndex === (int) ($N / 2)) {
            return $this->backtrack($roundsPairings, $assignedHome, $teamHomeCountInWeek, $teamAwayCountInWeek, $matchupHomeTeam, $roundIndex + 1, 0, $N);
        }

        [$teamA, $teamB] = $roundsPairings[$roundIndex][$matchIndex] ?? [null, null];
        if (!$teamA instanceof Team || !$teamB instanceof Team) {
            return false;
        }
        $idA = spl_object_id($teamA);
        $idB = spl_object_id($teamB);
        $weekIndex = (int) ($roundIndex / 2);

        $matchupKey = ($idA < $idB) ? "$idA-$idB" : "$idB-$idA";
        $isSecondLeg = $roundIndex >= 9;

        // Try Option 1: Team A is Home, Team B is Away
        if (($teamHomeCountInWeek[$weekIndex][$idA] ?? 0) < 1 && ($teamAwayCountInWeek[$weekIndex][$idB] ?? 0) < 1) {
            $valid = true;
            if ($isSecondLeg) {
                $firstLegHomeId = $matchupHomeTeam[$matchupKey] ?? null;
                if ($firstLegHomeId === $idA) {
                    $valid = false;
                }
            }

            if ($valid) {
                $assignedHome[$roundIndex][$matchIndex] = $teamA;
                $teamHomeCountInWeek[$weekIndex][$idA] = ($teamHomeCountInWeek[$weekIndex][$idA] ?? 0) + 1;
                $teamAwayCountInWeek[$weekIndex][$idB] = ($teamAwayCountInWeek[$weekIndex][$idB] ?? 0) + 1;
                if (!$isSecondLeg) {
                    $matchupHomeTeam[$matchupKey] = $idA;
                }

                if ($this->backtrack($roundsPairings, $assignedHome, $teamHomeCountInWeek, $teamAwayCountInWeek, $matchupHomeTeam, $roundIndex, $matchIndex + 1, $N)) {
                    return true;
                }

                // Revert
                $assignedHome[$roundIndex][$matchIndex] = null;
                --$teamHomeCountInWeek[$weekIndex][$idA];
                --$teamAwayCountInWeek[$weekIndex][$idB];
                if (!$isSecondLeg) {
                    unset($matchupHomeTeam[$matchupKey]);
                }
            }
        }

        // Try Option 2: Team B is Home, Team A is Away
        if (($teamHomeCountInWeek[$weekIndex][$idB] ?? 0) < 1 && ($teamAwayCountInWeek[$weekIndex][$idA] ?? 0) < 1) {
            $valid = true;
            if ($isSecondLeg) {
                $firstLegHomeId = $matchupHomeTeam[$matchupKey] ?? null;
                if ($firstLegHomeId === $idB) {
                    $valid = false;
                }
            }

            if ($valid) {
                $assignedHome[$roundIndex][$matchIndex] = $teamB;
                $teamHomeCountInWeek[$weekIndex][$idB] = ($teamHomeCountInWeek[$weekIndex][$idB] ?? 0) + 1;
                $teamAwayCountInWeek[$weekIndex][$idA] = ($teamAwayCountInWeek[$weekIndex][$idA] ?? 0) + 1;
                if (!$isSecondLeg) {
                    $matchupHomeTeam[$matchupKey] = $idB;
                }

                if ($this->backtrack($roundsPairings, $assignedHome, $teamHomeCountInWeek, $teamAwayCountInWeek, $matchupHomeTeam, $roundIndex, $matchIndex + 1, $N)) {
                    return true;
                }

                // Revert
                $assignedHome[$roundIndex][$matchIndex] = null;
                --$teamHomeCountInWeek[$weekIndex][$idB];
                --$teamAwayCountInWeek[$weekIndex][$idA];
                if (!$isSecondLeg) {
                    unset($matchupHomeTeam[$matchupKey]);
                }
            }
        }

        return false;
    }
}
