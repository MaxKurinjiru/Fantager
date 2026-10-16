<?php

declare(strict_types=1);

namespace App\Enum;

enum HeroStatus: string
{
    case Available = 'available';
    case InMatch = 'in_match';
    case Selling = 'selling';
    case Recovering = 'recovering';
    case Dead = 'dead';

    /** Reserved / unused (A6.2). No production writer; readers treat Retired like Dead for roster queries. */
    case Retired = 'retired';

    /**
     * Reserved cases stay on the enum (and DB column) but must not be written.
     */
    public function isReserved(): bool
    {
        return self::Retired === $this;
    }
}
