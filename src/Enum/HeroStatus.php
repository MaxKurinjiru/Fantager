<?php

declare(strict_types=1);

namespace App\Enum;

enum HeroStatus: string
{
    case Available = 'available';
    case Tired = 'tired';
    case Training = 'training';
    case InMatch = 'in_match';
    case Injured = 'injured';
    case Dead = 'dead';
    case Selling = 'selling';
}
