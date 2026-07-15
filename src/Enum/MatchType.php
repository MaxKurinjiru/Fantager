<?php

declare(strict_types=1);

namespace App\Enum;

enum MatchType: string
{
    case League = 'league';
    case Friendly = 'friendly';
    case Arena = 'arena';
}
