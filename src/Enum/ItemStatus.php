<?php

declare(strict_types=1);

namespace App\Enum;

enum ItemStatus: string
{
    case Available = 'available';
    case Selling = 'selling';
}
