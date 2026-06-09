<?php

declare(strict_types=1);

namespace App\Enum;

enum QuestType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Story = 'story';
    case Repeatable = 'repeatable';
}
