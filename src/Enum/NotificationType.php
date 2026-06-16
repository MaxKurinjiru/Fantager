<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case BattleResult = 'battle_result';
    case TrainingComplete = 'training_complete';
    case LeagueUpdate = 'league_update';
    case MarketplaceBid = 'marketplace_bid';
    case MarketplaceSold = 'marketplace_sold';
    case EventStarted = 'event_started';
    case HeroDied = 'hero_died';
    case SeasonEnded = 'season_ended';
    case System = 'system';
}
