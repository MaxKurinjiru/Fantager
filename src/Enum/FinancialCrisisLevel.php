<?php

declare(strict_types=1);

namespace App\Enum;

enum FinancialCrisisLevel: string
{
    case None = 'none';
    case Warning = 'warning';
    case Restricted = 'restricted';
    case BankruptcyPending = 'bankruptcy_pending';
}
