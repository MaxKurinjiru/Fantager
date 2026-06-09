<?php

declare(strict_types=1);

namespace App\Enum;

enum EventStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
