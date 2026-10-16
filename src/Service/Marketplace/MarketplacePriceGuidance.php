<?php

declare(strict_types=1);

namespace App\Service\Marketplace;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Item\ItemService;
use App\ValueObject\Marketplace\ListingPriceGuidance;

/**
 * Listing guidance: suggested price, floor (−50%), ceiling (+100%).
 * Spec: marketplace-system.md § Locked: listing price band.
 */
class MarketplacePriceGuidance
{
    public const FLOOR_RATIO = 0.5;

    public const CEILING_RATIO = 2.0;

    public function __construct(
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly ItemService $itemService,
    ) {
    }

    public function forHero(Hero $hero): ListingPriceGuidance
    {
        return $this->fromSuggested($this->heroRatingCalculator->estimateMarketPrice($hero));
    }

    public function forItem(Item $item): ListingPriceGuidance
    {
        return $this->fromSuggested($this->itemService->estimateMarketPrice($item));
    }

    private function fromSuggested(int $rawSuggested): ListingPriceGuidance
    {
        $suggested = max(1, $rawSuggested);
        $floor = max(1, (int) round($suggested * self::FLOOR_RATIO));
        $ceiling = max($floor, (int) round($suggested * self::CEILING_RATIO));

        return new ListingPriceGuidance($suggested, $floor, $ceiling);
    }
}
