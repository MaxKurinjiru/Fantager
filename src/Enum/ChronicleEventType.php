<?php

declare(strict_types=1);

namespace App\Enum;

enum ChronicleEventType: string
{
    case TeamEstablished = 'team_established';
    case PlayerJoined = 'player_joined';
    case PlayerReleased = 'player_released';
    case BattleWin = 'battle_win';
    case BattleLoss = 'battle_loss';
    case BattleDraw = 'battle_draw';
    case HeroLevelup = 'hero_levelup';
    case HeroDied = 'hero_died';
    case HeroRetired = 'hero_retired';
    case TrainingCompleted = 'training_completed';
    case ItemPurchased = 'item_purchased';
    case ItemSold = 'item_sold';
    case DungeonCompleted = 'dungeon_completed';
    case SummonCompleted = 'summon_completed';
    case SeasonEnded = 'season_ended';
    case HeroDismissed = 'hero_dismissed';
    case TrainerDismissed = 'trainer_dismissed';
    case HeroPurchased = 'hero_purchased';
    case HeroSold = 'hero_sold';
    case TrainerPurchased = 'trainer_purchased';
    case TrainerSold = 'trainer_sold';
    case TeamRenamed = 'team_renamed';
}
