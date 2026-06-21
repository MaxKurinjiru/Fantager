<?php

declare(strict_types=1);

namespace App\Enum;

enum ItemCategory: string
{
    case Weapon = 'weapon';
    case Shield = 'shield';
    case SpellAccelerator = 'spell_accelerator';
    case Armor = 'armor';
    case Accessory = 'accessory';
    case Material = 'material';
}
