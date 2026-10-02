<?php

declare(strict_types=1);

namespace App\Message;

class ProcessCombatRound
{
    public function __construct(
        private readonly int $battleId,
        private readonly int $round,
    ) {
    }

    public function getBattleId(): int
    {
        return $this->battleId;
    }

    public function getRound(): int
    {
        return $this->round;
    }
}
