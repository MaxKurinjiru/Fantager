<?php

declare(strict_types=1);

namespace App\Enum;

enum FacilityOperation: string
{
    case Upgrade = 'upgrade';
    case Downgrade = 'downgrade';
}
