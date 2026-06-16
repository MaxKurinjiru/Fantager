<?php

declare(strict_types=1);

namespace App\Enum;

enum GraveyardCause: string
{
    case Dismissed = 'dismissed';
    case CombatDeath = 'combat_death';
    case Age = 'age';
}
