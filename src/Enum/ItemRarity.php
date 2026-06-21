<?php

declare(strict_types=1);

namespace App\Enum;

enum ItemRarity: string
{
    case Common = 'common';
    case Uncommon = 'uncommon';
    case Rare = 'rare';
    case Epic = 'epic';
    case Legendary = 'legendary';
    case Mythic = 'mythic';
}
