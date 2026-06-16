<?php

declare(strict_types=1);

namespace App\Enum;

enum HeroStatus: string
{
    case Available = 'available';
    case InMatch = 'in_match';
    case Selling = 'selling';
    case Recovering = 'recovering';
    case Dead = 'dead';
    case Retired = 'retired';
}
