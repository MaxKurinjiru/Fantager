<?php

declare(strict_types=1);

namespace App\Enum;

enum TrainerStatus: string
{
    case Active = 'active';
    case Retired = 'retired';
    case Dead = 'dead';
    case Selling = 'selling';
}
