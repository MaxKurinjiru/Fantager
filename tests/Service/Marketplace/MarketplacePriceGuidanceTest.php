<?php

declare(strict_types=1);

namespace App\Tests\Service\Marketplace;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Item\ItemService;
use App\Service\Marketplace\MarketplacePriceGuidance;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MarketplacePriceGuidanceTest extends TestCase
{
    public function testHeroBandIsHalfToDoubleSuggested(): void
    {
        $hero = new Hero();
        $calculator = $this->createMock(HeroRatingCalculator::class);
        $calculator->method('estimateMarketPrice')->with($hero)->willReturn(100);
        $itemService = $this->createMock(ItemService::class);

        $guidance = (new MarketplacePriceGuidance($calculator, $itemService))->forHero($hero);

        $this->assertSame(100, $guidance->getSuggested());
        $this->assertSame(50, $guidance->getFloor());
        $this->assertSame(200, $guidance->getCeiling());
        $this->assertTrue($guidance->contains(50));
        $this->assertTrue($guidance->contains(100));
        $this->assertTrue($guidance->contains(200));
        $this->assertFalse($guidance->contains(49));
        $this->assertFalse($guidance->contains(201));
    }

    public function testSuggestedOfZeroBecomesOneGoldBand(): void
    {
        $hero = new Hero();
        $calculator = $this->createMock(HeroRatingCalculator::class);
        $calculator->method('estimateMarketPrice')->willReturn(0);
        $itemService = $this->createMock(ItemService::class);

        $guidance = (new MarketplacePriceGuidance($calculator, $itemService))->forHero($hero);

        $this->assertSame(1, $guidance->getSuggested());
        $this->assertSame(1, $guidance->getFloor());
        $this->assertSame(2, $guidance->getCeiling());
    }

    public function testOddSuggestedRoundsHalfAwayFromZero(): void
    {
        $hero = new Hero();
        $calculator = $this->createMock(HeroRatingCalculator::class);
        $calculator->method('estimateMarketPrice')->willReturn(3);
        $itemService = $this->createMock(ItemService::class);

        $guidance = (new MarketplacePriceGuidance($calculator, $itemService))->forHero($hero);

        $this->assertSame(3, $guidance->getSuggested());
        $this->assertSame(2, $guidance->getFloor());
        $this->assertSame(6, $guidance->getCeiling());
    }

    public function testItemBandUsesItemServiceSuggestedPrice(): void
    {
        $item = new Item();
        $calculator = $this->createMock(HeroRatingCalculator::class);
        $itemService = $this->createMock(ItemService::class);
        $itemService->method('estimateMarketPrice')->with($item)->willReturn(180);

        $guidance = (new MarketplacePriceGuidance($calculator, $itemService))->forItem($item);

        $this->assertSame(180, $guidance->getSuggested());
        $this->assertSame(90, $guidance->getFloor());
        $this->assertSame(360, $guidance->getCeiling());
    }
}
