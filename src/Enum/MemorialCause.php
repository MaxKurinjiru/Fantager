<?php

declare(strict_types=1);

namespace App\Enum;

enum MemorialCause: string
{
    case Dismissed = 'dismissed';
    case CombatDeath = 'combat_death';
    case Age = 'age';
    case Retired = 'retired';
    case Death = 'death';
}
