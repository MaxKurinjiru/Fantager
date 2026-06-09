<?php

declare(strict_types=1);

namespace App\Enum;

enum LeagueFixtureStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
