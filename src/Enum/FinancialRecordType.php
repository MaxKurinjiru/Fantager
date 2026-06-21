<?php

declare(strict_types=1);

namespace App\Enum;

enum FinancialRecordType: string
{
    case LeagueReward = 'league_reward';
    case ArenaRevenue = 'arena_revenue';
    case SummonFee = 'summon_fee';
    case MarketplaceSale = 'marketplace_sale';
    case MarketplacePurchase = 'marketplace_purchase';
    case MarketplaceFee = 'marketplace_fee';
    case DungeonReward = 'dungeon_reward';
    case DismantleGain = 'dismantle_gain';
    case ItemRepair = 'item_repair';
    case SpellLearningCost = 'spell_learning_cost';
    case SpellSlotCost = 'spell_slot_cost';
    case HqUpgradeCost = 'hq_upgrade_cost';
    case HqMaintenanceFee = 'hq_maintenance_fee';
    case MoraleRestoration = 'morale_restoration';
    case DebtRepayment = 'debt_repayment';
    case HeroDismissalCompensation = 'hero_dismissal_compensation';
    case TrainerDismissalCompensation = 'trainer_dismissal_compensation';
    case HqDowngradeRefund = 'hq_downgrade_refund';
    case KingdomReward = 'kingdom_reward';
}
