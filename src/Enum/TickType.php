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
    /** Arena adaptation tick (Sunday 09:30). Persisted as `race_optimization`. */
    case RaceOptimization = 'race_optimization';
    case InactiveRegistrationCleanup = 'inactive_registration_cleanup';
    case InactivePlayerCleanup = 'inactive_player_cleanup';
}
