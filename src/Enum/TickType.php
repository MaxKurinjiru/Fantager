<?php

declare(strict_types=1);

namespace App\Enum;

enum TickType: string
{
    case DailyReset = 'daily_reset';
    case FatigueRecovery = 'fatigue_recovery';
    case LeagueMatch = 'league_match';
    case FriendlyMatch = 'friendly_match';
    case WeeklyTraining = 'weekly_training';
    case SeasonTransition = 'season_transition';
    case WeeklyReset = 'weekly_reset';
    /** Arena adaptation tick (Sunday 09:30). Persisted as `race_optimization`. */
    case RaceOptimization = 'race_optimization';
    case InactiveRegistrationCleanup = 'inactive_registration_cleanup';
    case InactivePlayerCleanup = 'inactive_player_cleanup';

    public function getPriority(): int
    {
        return match ($this) {
            self::WeeklyTraining => 2,
            self::LeagueMatch,
            self::FriendlyMatch => 3,
            self::SeasonTransition => 4,
            self::FatigueRecovery => 5,
            self::DailyReset,
            self::WeeklyReset,
            self::RaceOptimization,
            self::InactiveRegistrationCleanup,
            self::InactivePlayerCleanup => 6,
        };
    }

    /** Whether KingdomTickRunner creates per-team KingdomTickLog rows for this tick. */
    public function createsTeamScopedLogs(): bool
    {
        return match ($this) {
            self::WeeklyTraining,
            self::WeeklyReset,
            self::RaceOptimization,
            self::FatigueRecovery,
            self::InactivePlayerCleanup,
            self::DailyReset => true,
            default => false,
        };
    }

    /** Whether KingdomTickRunner creates a kingdom-wide (team=null) KingdomTickLog row. */
    public function createsKingdomScopedLogs(): bool
    {
        return match ($this) {
            self::SeasonTransition,
            self::InactiveRegistrationCleanup,
            self::WeeklyReset => true,
            default => false,
        };
    }
}
