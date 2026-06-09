<?php

declare(strict_types=1);

namespace App\Enum;

enum StatusEffect: string
{
    case Burn = 'burn';
    case Freeze = 'freeze';
    case Shock = 'shock';
    case Petrify = 'petrify';
    case Blind = 'blind';
    case Curse = 'curse';
    case Stun = 'stun';
    case Poison = 'poison';
    case Shield = 'shield';
    case Regeneration = 'regeneration';
    case Haste = 'haste';
    case Bless = 'bless';
    case Fury = 'fury';
    case ShadowCloak = 'shadow_cloak';
    case Taunt = 'taunt';
    case Silence = 'silence';
}
