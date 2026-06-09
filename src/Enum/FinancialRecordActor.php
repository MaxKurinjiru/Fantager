<?php

declare(strict_types=1);

namespace App\Enum;

enum FinancialRecordActor: string
{
    case System = 'system';
    case Active = 'active';
    case Passive = 'passive';
}
