<?php

declare(strict_types=1);

namespace App\Enum;

enum ItemSubType: string
{
    case OneHandedSword = 'one_handed_sword';
    case TwoHandedSword = 'two_handed_sword';
    case OneHandedAxe = 'one_handed_axe';
    case TwoHandedAxe = 'two_handed_axe';
    case OneHandedMace = 'one_handed_mace';
    case TwoHandedMace = 'two_handed_mace';
    case Dagger = 'dagger';
    case Bow = 'bow';
    case Crossbow = 'crossbow';
    case Wand = 'wand';
    case Staff = 'staff';
    case Shield = 'shield';
    case SpellAccelerator = 'spell_accelerator';
    case LightArmor = 'light_armor';
    case MediumArmor = 'medium_armor';
    case HeavyArmor = 'heavy_armor';
}
