<?php

declare(strict_types=1);

namespace App\Service\TeamChronicle;

/**
 * Inclusive/exclusive bounds for one manager or NPC stretch of a team chronicle.
 */
final class ChroniclePeriodWindow
{
    public function __construct(
        public readonly \DateTimeImmutable $from,
        public readonly bool $fromInclusive,
        public readonly ?\DateTimeImmutable $to,
        public readonly bool $toInclusive,
    ) {
    }
}
