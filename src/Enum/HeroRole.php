<?php

declare(strict_types=1);

namespace App\Enum;

enum HeroRole: string
{
    case Combatant = 'combatant';
    case Trainer = 'trainer';
}
