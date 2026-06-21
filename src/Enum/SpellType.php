<?php

declare(strict_types=1);

namespace App\Enum;

enum SpellType: string
{
    case Offensive = 'offensive';
    case Defensive = 'defensive';
    case Utility = 'utility';
}
