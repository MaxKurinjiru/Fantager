<?php

declare(strict_types=1);

namespace App\Enum;

enum ListingMode: string
{
    case BuyNow = 'buy_now';
    case Auction = 'auction';
}
