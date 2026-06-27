<?php

declare(strict_types=1);

namespace App\Message;

class ExecuteSingleTickMessage
{
    public function __construct(
        private readonly int $tickLogId,
    ) {
    }

    public function getTickLogId(): int
    {
        return $this->tickLogId;
    }
}
