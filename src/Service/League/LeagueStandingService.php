<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\League\LeagueStanding;

class LeagueStandingService
{
    public function applyMatchResult(
        LeagueStanding $homeStanding,
        LeagueStanding $awayStanding,
        int $homeScore,
        int $awayScore,
    ): void {
        $homeStanding->setPlayed($homeStanding->getPlayed() + 1);
        $awayStanding->setPlayed($awayStanding->getPlayed() + 1);

        $homeStanding->setGoalDifference($homeStanding->getGoalDifference() + ($homeScore - $awayScore));
        $awayStanding->setGoalDifference($awayStanding->getGoalDifference() + ($awayScore - $homeScore));

        if ($homeScore > $awayScore) {
            $homeStanding->setWins($homeStanding->getWins() + 1);
            $homeStanding->setPoints($homeStanding->getPoints() + 3);
            $awayStanding->setLosses($awayStanding->getLosses() + 1);
        } elseif ($homeScore < $awayScore) {
            $awayStanding->setWins($awayStanding->getWins() + 1);
            $awayStanding->setPoints($awayStanding->getPoints() + 3);
            $homeStanding->setLosses($homeStanding->getLosses() + 1);
        } else {
            $homeStanding->setDraws($homeStanding->getDraws() + 1);
            $homeStanding->setPoints($homeStanding->getPoints() + 1);
            $awayStanding->setDraws($awayStanding->getDraws() + 1);
            $awayStanding->setPoints($awayStanding->getPoints() + 1);
        }
    }
}
