<?php

declare(strict_types=1);

namespace App\Enum;

enum RoyalTreasuryContributionSource: string
{
    case MarketplaceTax = 'marketplace_tax';
    case HqUpgradeCost = 'hq_upgrade_cost';
    case HqMaintenanceFee = 'hq_maintenance_fee';
    case SummonFee = 'summon_fee';
}
