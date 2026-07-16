<?php

declare(strict_types=1);

namespace App\Enum;

enum BattleStatus: string
{
    case Simulating = 'simulating';
    case Stalled = 'stalled';
    case Completed = 'completed';
}
