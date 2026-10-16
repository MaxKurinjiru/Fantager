<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case BattleResult = 'battle_result';
    case TrainingComplete = 'training_complete';

    /** Reserved / unused (A6.2). No league-update notification writer. */
    case LeagueUpdate = 'league_update';

    case MarketplaceBid = 'marketplace_bid';
    case MarketplaceSold = 'marketplace_sold';

    /** Reserved / unused (A6.2). World events are deferred. */
    case EventStarted = 'event_started';

    case HeroDied = 'hero_died';
    case SeasonEnded = 'season_ended';
    case System = 'system';
    case HqUpgrade = 'hq_upgrade';
    case FinancialCrisis = 'financial_crisis';

    /** Reserved / unused (A6.2). Spell learning is recorded on team/hero chronicle only. */
    case SpellLearned = 'spell_learned';

    /**
     * Reserved cases stay on the enum (and DB column) but must not be written.
     */
    public function isReserved(): bool
    {
        return match ($this) {
            self::LeagueUpdate, self::EventStarted, self::SpellLearned => true,
            default => false,
        };
    }
}
