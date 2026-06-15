<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\MatchResult;
use App\Repository\Team\TeamRepository;

class FanClubService
{
    public const DEFAULT_FAN_BASE = 350;
    public const MIN_FAN_BASE = 0;
    public const MAX_FAN_BASE = 10000;

    /** Share of home fans willing to travel to away fixtures. */
    public const AWAY_TRAVEL_RATE = 0.35;

    /** Daily drift toward target fan base (fraction of gap closed per day). */
    public const DAILY_DRIFT_RATE = 0.03;

    public const MATCH_WIN_DELTA = 12;
    public const MATCH_LOSS_DELTA = -10;
    public const MATCH_DRAW_DELTA = 2;

    public function __construct(
        private readonly TeamRepository $teamRepository,
    ) {
    }

    /**
     * Short-term turnout multiplier from reputation, morale, and chemistry (0.0–1.0).
     */
    public function calculateShowUpRate(Team $team): float
    {
        $reputation = max(0, $team->getReputation());
        $morale = max(0, min(100, $team->getMorale()));
        $chemistry = max(0, $team->getChemistry());

        $repFactor = $reputation / ($reputation + 100.0);
        $moraleFactor = $morale / 100.0;
        $chemFactor = min(1.0, $chemistry / 50.0);

        $score = ($repFactor * 0.35) + ($moraleFactor * 0.45) + ($chemFactor * 0.20);

        return min(1.0, max(0.0, ($score - 0.05) / 0.55));
    }

    /**
     * Equilibrium fan base derived from long-term team standing.
     */
    public function calculateTargetFanBase(Team $team): int
    {
        $target = 50
            + (int) round($team->getReputation() * 1.5)
            + (int) round($team->getMorale() * 1.2)
            + (int) round(min(50, max(0, $team->getChemistry())) * 2.0);

        return max(self::MIN_FAN_BASE, min(self::MAX_FAN_BASE, $target));
    }

    public function calculateHomeAttendance(Team $team): int
    {
        return (int) round($team->getFanBase() * $this->calculateShowUpRate($team));
    }

    public function calculateAwayAttendance(Team $team): int
    {
        return (int) round($this->calculateHomeAttendance($team) * self::AWAY_TRAVEL_RATE);
    }

    /**
     * @return array{home_attendees: int, away_attendees: int, attendance: int, home_show_up_rate: float, away_show_up_rate: float}
     */
    public function calculateMatchAttendance(Team $homeTeam, Team $awayTeam, int $capacity): array
    {
        $homeAttendees = $this->calculateHomeAttendance($homeTeam);
        $awayAttendees = $this->calculateAwayAttendance($awayTeam);
        $attendance = min($capacity, $homeAttendees + $awayAttendees);

        if ($attendance < $homeAttendees + $awayAttendees) {
            $total = max(1, $homeAttendees + $awayAttendees);
            $homeAttendees = (int) round($attendance * ($homeAttendees / $total));
            $awayAttendees = $attendance - $homeAttendees;
        }

        return [
            'home_attendees' => $homeAttendees,
            'away_attendees' => $awayAttendees,
            'attendance' => $attendance,
            'home_show_up_rate' => round($this->calculateShowUpRate($homeTeam), 3),
            'away_show_up_rate' => round($this->calculateShowUpRate($awayTeam), 3),
        ];
    }

    public function evolveFanBase(Team $team): int
    {
        $target = $this->calculateTargetFanBase($team);
        $current = $team->getFanBase();
        $delta = (int) round(($target - $current) * self::DAILY_DRIFT_RATE);

        if (0 === $delta && $target !== $current) {
            $delta = $target > $current ? 1 : -1;
        }

        $newBase = max(self::MIN_FAN_BASE, min(self::MAX_FAN_BASE, $current + $delta));
        $team->setFanBase($newBase);

        return $newBase;
    }

    public function applyMatchResult(Team $team, MatchResult $result): int
    {
        $delta = match ($result) {
            MatchResult::Win => self::MATCH_WIN_DELTA,
            MatchResult::Loss => self::MATCH_LOSS_DELTA,
            MatchResult::Draw => self::MATCH_DRAW_DELTA,
        };

        $newBase = max(self::MIN_FAN_BASE, min(self::MAX_FAN_BASE, $team->getFanBase() + $delta));
        $team->setFanBase($newBase);

        return $newBase;
    }

    public function processDailyEvolutionTick(Kingdom $kingdom): int
    {
        $teams = $this->teamRepository->findBy(['kingdom' => $kingdom]);
        $updated = 0;

        foreach ($teams as $team) {
            $this->evolveFanBase($team);
            ++$updated;
        }

        return $updated;
    }
}
