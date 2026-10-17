<?php

declare(strict_types=1);

namespace App\Enum;

enum PacePreset: string
{
    case Classic = 'classic';
    case FastSeason = 'fast_season';
    case ShortCareers = 'short_careers';
    case RichWorld = 'rich_world';
    case Test = 'test';
}
