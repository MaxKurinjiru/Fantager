<?php

declare(strict_types=1);

namespace App\Enum;

enum QuestProgressStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
}
