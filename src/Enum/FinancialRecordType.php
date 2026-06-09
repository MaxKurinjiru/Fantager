<?php

declare(strict_types=1);

namespace App\Enum;

enum FinancialRecordType: string
{
    case LeagueReward = 'league_reward';
    case ArenaRevenue = 'arena_revenue';
    case TrainingCost = 'training_cost';
    case SummonFee = 'summon_fee';
    case MarketplaceSale = 'marketplace_sale';
    case MarketplacePurchase = 'marketplace_purchase';
    case MarketplaceFee = 'marketplace_fee';
    case QuestReward = 'quest_reward';
    case DungeonReward = 'dungeon_reward';
    case CraftingCost = 'crafting_cost';
    case DismantleGain = 'dismantle_gain';
    case ItemRepair = 'item_repair';
    case SpellLearningCost = 'spell_learning_cost';
    case SpellSlotCost = 'spell_slot_cost';
    case HqUpgradeCost = 'hq_upgrade_cost';
    case MoraleRestoration = 'morale_restoration';
}
