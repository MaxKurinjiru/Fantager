<?php

declare(strict_types=1);

namespace App\ValueObject\Hero;

final class HeroRating
{
    public function __construct(
        private readonly int $baseOvr,
        private readonly int $complexRating,
    ) {
    }

    public function getBaseOvr(): int
    {
        return $this->baseOvr;
    }

    public function getComplexRating(): int
    {
        return $this->complexRating;
    }
}
