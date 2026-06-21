<?php

declare(strict_types=1);

namespace App\Exception;

class InactiveSeasonException extends \RuntimeException
{
    public function __construct(string $message = 'The kingdom does not have an active season.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
