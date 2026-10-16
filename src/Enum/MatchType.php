<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchType: string
{
    case League = 'league';
    case Friendly = 'friendly';

    /** Reserved / unused (A6.2). Live battles use League or Friendly. Do not write this case. */
    case Arena = 'arena';

    /**
     * Reserved cases stay on the enum (and DB column) but must not be written.
     */
    public function isReserved(): bool
    {
        return self::Arena === $this;
    }
}
