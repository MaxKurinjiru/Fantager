<?php

declare(strict_types=1);

namespace App\Enum;

enum ListingStatus: string
{
    case Active = 'active';
    case Sold = 'sold';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
