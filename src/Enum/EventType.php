<?php

declare(strict_types=1);

namespace App\Enum;

enum EventType: string
{
    case WorldEvent = 'world_event';
    case Seasonal = 'seasonal';
    case LimitedMission = 'limited_mission';
    case SpecialEconomic = 'special_economic';
}
