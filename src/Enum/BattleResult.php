<?php

declare(strict_types=1);

namespace App\Enum;

enum BattleResult: string
{
    case WinA = 'win_a';
    case WinB = 'win_b';
    case Draw = 'draw';

    public static function fromScores(int $scoreA, int $scoreB): self
    {
        if ($scoreA > $scoreB) {
            return self::WinA;
        }
        if ($scoreA < $scoreB) {
            return self::WinB;
        }

        return self::Draw;
    }
}
