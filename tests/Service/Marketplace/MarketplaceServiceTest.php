<?php

declare(strict_types=1);

namespace App\Tests\Service\Marketplace;

use App\Entity\Hero\Hero;
use App\Entity\Marketplace\MarketplaceListing;
use App\Entity\Team\Team;
use App\Enum\HeroStatus;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Service\Economy\EconomyService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Notification\NotificationHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MarketplaceServiceTest extends TestCase
{
    private EntityManagerInterface $entityManagerMock;
    private EconomyService $economyServiceMock;
    private NotificationHelper $notificationHelperMock;
    private MarketplaceService $marketplaceService;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->notificationHelperMock = $this->createMock(NotificationHelper::class);

        $this->marketplaceService = new MarketplaceService(
            $this->entityManagerMock,
            $this->economyServiceMock,
            $this->notificationHelperMock,
        );
    }

    public function testCreateListingRejectsNonPositivePrice(): void
    {
        $seller = new Team();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Price or starting bid must be positive.');

        $this->marketplaceService->createListing(
            $seller,
            ListingType::Hero->value,
            1,
            0,
            null,
            ListingMode::BuyNow->value,
            7,
            new \DateTimeImmutable('now')
        );
    }

    public function testCreateListingRejectsInvalidMode(): void
    {
        $seller = new Team();

        $this->expectException(\InvalidArgumentException::class);

        $this->marketplaceService->createListing(
            $seller,
            ListingType::Hero->value,
            1,
            100,
            null,
            'invalid_mode',
            7,
            new \DateTimeImmutable('now')
        );
    }

    public function testCancelListingRestoresHeroStatus(): void
    {
        $seller = new Team();
        $hero = new Hero();
        $hero->setStatus(HeroStatus::Selling);

        $listing = new MarketplaceListing();
        $listing->setSellerTeam($seller);
        $listing->setListingType(ListingType::Hero);
        $listing->setHero($hero);
        $listing->setStatus(ListingStatus::Active);

        $listingRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $listingRepo->method('find')->with(42)->willReturn($listing);

        $this->entityManagerMock
            ->method('getRepository')
            ->with(MarketplaceListing::class)
            ->willReturn($listingRepo);

        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->marketplaceService->cancelListing($seller, 42);

        $this->assertSame(HeroStatus::Available, $hero->getStatus());
        $this->assertSame(ListingStatus::Cancelled, $listing->getStatus());
    }
}
