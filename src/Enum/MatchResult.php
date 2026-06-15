<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchResult: string
{
    case Win = 'win';
    case Loss = 'loss';
    case Draw = 'draw';
}
