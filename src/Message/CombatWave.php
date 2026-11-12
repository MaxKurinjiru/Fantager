<?php

declare(strict_types=1);

namespace App\Message;

class CombatWave
{
    public function __construct(
        private readonly int $kingdomId,
        private readonly \DateTimeImmutable $scheduledAt,
        private readonly int $round,
    ) {
    }

    public function getKingdomId(): int
    {
        return $this->kingdomId;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getLockName(): string
    {
        return sprintf('combat_wave_%d_%s', $this->kingdomId, $this->scheduledAt->format('U.u'));
    }
}
