<?php

declare(strict_types=1);

namespace App\Service\Calendar;

class TickClock
{
    private ?\DateTimeImmutable $customTime = null;

    public function setCustomTime(?\DateTimeImmutable $time): void
    {
        $this->customTime = $time;
    }

    public function getCustomTime(): ?\DateTimeImmutable
    {
        return $this->customTime;
    }

    public function getCurrentTime(): \DateTimeImmutable
    {
        return $this->customTime ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
