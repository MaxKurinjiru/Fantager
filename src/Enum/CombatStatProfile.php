<?php

declare(strict_types=1);

namespace App\Enum;

enum CombatStatProfile: string
{
    /** Full combat state: equipment, attuned mastery, trait, actual race, hero form. */
    case Equipped = 'equipped';

    /** Cross-race OVR: human race passives only, no items, mastery, or trait; form = 100 %. */
    case HumanNeutral = 'human_neutral';

    /** Intrinsic combat potential: actual race, no items, mastery, or trait; form = 100 %. */
    case FullIntrinsic = 'full_intrinsic';
}
