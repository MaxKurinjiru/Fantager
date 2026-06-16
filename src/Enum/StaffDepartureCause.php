<?php

declare(strict_types=1);

namespace App\Enum;

enum StaffDepartureCause: string
{
    case Dismissed = 'dismissed';
    case Retired = 'retired';
    case Death = 'death';
}
