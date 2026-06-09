<?php

declare(strict_types=1);

namespace App\Enum;

enum DungeonResult: string
{
    case Win = 'win';
    case Loss = 'loss';
    case Abandoned = 'abandoned';
}
