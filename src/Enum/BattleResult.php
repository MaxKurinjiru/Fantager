<?php

declare(strict_types=1);

namespace App\Enum;

enum BattleResult: string
{
    case WinA = 'win_a';
    case WinB = 'win_b';
    case Draw = 'draw';
}
