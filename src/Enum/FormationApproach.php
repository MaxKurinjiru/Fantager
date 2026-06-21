<?php

declare(strict_types=1);

namespace App\Enum;

enum FormationApproach: string
{
    case Aggressive = 'aggressive';
    case Balanced = 'balanced';
    case Defensive = 'defensive';
}
