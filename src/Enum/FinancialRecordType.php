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
    case DismantleGain = 'dismantle_gain';
    case ItemRepair = 'item_repair';
    case SpellLearningCost = 'spell_learning_cost';

    /** Reserved / unused (A6.2). Magic capacity expands via training; never write this ledger type. */
    case SpellSlotCost = 'spell_slot_cost';

    case HqUpgradeCost = 'hq_upgrade_cost';
    case HqMaintenanceFee = 'hq_maintenance_fee';

    /** Reserved / unused (A6.2). No morale-restoration gold sink exists. Do not write this case. */
    case MoraleRestoration = 'morale_restoration';

    case DebtRepayment = 'debt_repayment';
    case HeroDismissalCompensation = 'hero_dismissal_compensation';
    case TrainerDismissalCompensation = 'trainer_dismissal_compensation';
    case HqDowngradeRefund = 'hq_downgrade_refund';
    case HqUpgradeRefund = 'hq_upgrade_refund';
    case KingdomReward = 'kingdom_reward';
    case HeroSalary = 'hero_salary';
    case TrainerSalary = 'trainer_salary';

    /**
     * Reserved cases stay on the enum (and DB column) but must not be written.
     */
    public function isReserved(): bool
    {
        return match ($this) {
            self::SpellSlotCost, self::MoraleRestoration => true,
            default => false,
        };
    }

    /**
     * Player-facing filter options — excludes reserved cases.
     *
     * @return list<self>
     */
    public static function liveCases(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => !$case->isReserved()));
    }
}
