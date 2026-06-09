<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionType: string
{
    case BuyNow = 'buy_now';
    case AuctionWin = 'auction_win';
}
