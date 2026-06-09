<?php

declare(strict_types=1);

namespace App\Enum;

enum TickType: string
{
    case DailyReset = 'daily_reset';
    case FatigueRecovery = 'fatigue_recovery';
    case LeagueMatch = 'league_match';
    case WeeklyTraining = 'weekly_training';
    case SeasonTransition = 'season_transition';
    case WeeklyReset = 'weekly_reset';
    case RaceOptimization = 'race_optimization';
}
