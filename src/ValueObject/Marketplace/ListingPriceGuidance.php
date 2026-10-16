<?php

declare(strict_types=1);

namespace App\ValueObject\Marketplace;

final class ListingPriceGuidance
{
    public function __construct(
        private readonly int $suggested,
        private readonly int $floor,
        private readonly int $ceiling,
    ) {
    }

    public function getSuggested(): int
    {
        return $this->suggested;
    }

    public function getFloor(): int
    {
        return $this->floor;
    }

    public function getCeiling(): int
    {
        return $this->ceiling;
    }

    public function contains(int $price): bool
    {
        return $price >= $this->floor && $price <= $this->ceiling;
    }
}
