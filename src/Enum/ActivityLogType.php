<?php

declare(strict_types=1);

namespace App\Enum;

enum ActivityLogType: string
{
    case BattleWin = 'battle_win';
    case BattleLoss = 'battle_loss';
    case BattleDraw = 'battle_draw';
    case HeroLevelup = 'hero_levelup';
    case HeroDied = 'hero_died';
    case HeroRetired = 'hero_retired';
    case TrainingCompleted = 'training_completed';
    case ItemPurchased = 'item_purchased';
    case ItemSold = 'item_sold';
    case AchievementUnlocked = 'achievement_unlocked';
    case DungeonCompleted = 'dungeon_completed';
    case SummonCompleted = 'summon_completed';
    case SeasonEnded = 'season_ended';
    case PlayerJoined = 'player_joined';
}
