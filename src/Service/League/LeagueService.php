<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Enum\LeagueFixtureStatus;
use App\Enum\LeagueSeasonStatus;
use Doctrine\ORM\EntityManagerInterface;

class LeagueService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Finds the active league season for the given Kingdom.
     */
    public function getCurrentSeason(Kingdom $kingdom): ?LeagueSeason
    {
        return $this->em->getRepository(LeagueSeason::class)->findOneBy([
            'kingdom' => $kingdom,
            'status' => LeagueSeasonStatus::Active,
        ]);
    }

    /**
     * Returns the sorted standings table for a given league group.
     *
     * @return list<LeagueStanding>
     */
    public function getSortedStandings(LeagueGroup $group): array
    {
        $standings = $group->getStandings()->toArray();
        usort($standings, [$this, 'compareStandings']);

        return $standings;
    }

    /**
     * Returns the global standings (leaderboard) across all tiers and groups in a season.
     *
     * @return list<LeagueStanding>
     */
    public function getGlobalLeaderboard(LeagueSeason $season): array
    {
        // Query all standings for the season
        $standings = $this->em->getRepository(LeagueStanding::class)->createQueryBuilder('ls')
            ->join('ls.group', 'g')
            ->join('g.tier', 't')
            ->where('t.season = :season')
            ->setParameter('season', $season)
            ->getQuery()
            ->getResult();

        usort($standings, [$this, 'compareGlobalStandings']);

        return $standings;
    }

    /**
     * Computes the recent form (last N matches) of a team in the current season.
     * Returns a list of 'W', 'D', 'L' characters.
     *
     * @return list<string>
     */
    public function getTeamForm(Team $team, LeagueSeason $season, int $limit = 5): array
    {
        $fixtures = $this->em->getRepository(LeagueFixture::class)->createQueryBuilder('lf')
            ->join('lf.group', 'g')
            ->join('g.tier', 't')
            ->where('t.season = :season')
            ->andWhere('lf.status = :status')
            ->andWhere('(lf.homeTeam = :team OR lf.awayTeam = :team)')
            ->setParameter('season', $season)
            ->setParameter('status', LeagueFixtureStatus::Completed)
            ->setParameter('team', $team)
            ->orderBy('lf.scheduledAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $form = [];
        // Loop backwards to have chronological order (oldest to newest) or keep newest first.
        // Usually, recent form in tables displays chronological or reverse-chronological order.
        // Let's do chronological (left to right = oldest to newest in the last 5 matches) by reversing the DESC results.
        $fixtures = array_reverse($fixtures);

        foreach ($fixtures as $fixture) {
            $battle = $fixture->getBattle();
            if (null === $battle) {
                continue;
            }

            $scoreHome = $battle->getScoreA();
            $scoreAway = $battle->getScoreB();

            if ($fixture->getHomeTeam() === $team) {
                if ($scoreHome > $scoreAway) {
                    $form[] = 'W';
                } elseif ($scoreHome < $scoreAway) {
                    $form[] = 'L';
                } else {
                    $form[] = 'D';
                }
            } else {
                if ($scoreAway > $scoreHome) {
                    $form[] = 'W';
                } elseif ($scoreAway < $scoreHome) {
                    $form[] = 'L';
                } else {
                    $form[] = 'D';
                }
            }
        }

        return $form;
    }

    /**
     * Comparison function for standings within a group.
     */
    public function compareStandings(LeagueStanding $a, LeagueStanding $b): int
    {
        // 1. Points DESC
        if ($a->getPoints() !== $b->getPoints()) {
            return $b->getPoints() <=> $a->getPoints();
        }
        // 2. Goal Difference DESC
        if ($a->getGoalDifference() !== $b->getGoalDifference()) {
            return $b->getGoalDifference() <=> $a->getGoalDifference();
        }
        // 3. Wins DESC
        if ($a->getWins() !== $b->getWins()) {
            return $b->getWins() <=> $a->getWins();
        }
        // 4. Team Type (Real player (isNpc = false) comes first DESC, false (0) < true (1))
        $npcA = $a->getTeam()->isNpc();
        $npcB = $b->getTeam()->isNpc();
        if ($npcA !== $npcB) {
            return $npcA <=> $npcB; // 0 <=> 1 returns -1, so 0 (real player) ranked above 1 (NPC)
        }
        // 5. Team Reputation DESC
        if ($a->getTeam()->getReputation() !== $b->getTeam()->getReputation()) {
            return $b->getTeam()->getReputation() <=> $a->getTeam()->getReputation();
        }
        // 6. Team Chemistry DESC
        if ($a->getTeam()->getChemistry() !== $b->getTeam()->getChemistry()) {
            return $b->getTeam()->getChemistry() <=> $a->getTeam()->getChemistry();
        }

        // 7. Entity ID ASC
        return $a->getTeam()->getId() <=> $b->getTeam()->getId();
    }

    /**
     * Comparison function for global standings (across different tiers and groups).
     */
    public function compareGlobalStandings(LeagueStanding $a, LeagueStanding $b): int
    {
        $tierA = $a->getGroup()->getTier();
        $tierB = $b->getGroup()->getTier();

        if ($tierA->getId() !== $tierB->getId()) {
            // Lower tier ID is created first (T1 is created before T2, etc.)
            return $tierA->getId() <=> $tierB->getId();
        }

        return $this->compareStandings($a, $b);
    }
}
