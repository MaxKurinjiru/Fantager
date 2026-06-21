<?php

declare(strict_types=1);

namespace App\Enum;

enum ItemSlotType: string
{
    case MainHand = 'main_hand';
    case OffHand = 'off_hand';
    case Head = 'head';
    case Body = 'body';
    case Hands = 'hands';
    case Feet = 'feet';
    case Amulet = 'amulet';
    case Ring = 'ring';
}
