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

    /**
     * Two-handed occupancy: these occupy main_hand and clear off_hand.
     * Matches the NPC gear skip list (great weapons, bow, crossbow, staff).
     */
    public function occupiesBothHands(): bool
    {
        return match ($this) {
            self::TwoHandedSword,
            self::TwoHandedAxe,
            self::TwoHandedMace,
            self::Bow,
            self::Crossbow,
            self::Staff => true,
            default => false,
        };
    }

    /** One-handed melee that may be equipped in main_hand or off_hand. */
    public function isDualWieldable(): bool
    {
        return match ($this) {
            self::OneHandedSword,
            self::OneHandedAxe,
            self::OneHandedMace,
            self::Dagger => true,
            default => false,
        };
    }
}
