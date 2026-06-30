<?php

declare(strict_types=1);

namespace App\Tests\Service\Marketplace;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Marketplace\MarketplaceListing;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\HeroStatus;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Exception\UserFacingException;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Notification\NotificationHelper;
use App\Service\Team\TeamRosterService;
use App\Service\Team\TeamChemistryService;
use App\Service\Training\TrainingService;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class MarketplaceServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EconomyService */
    private $economyServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&FinancialCrisisService */
    private $financialCrisisServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\RoyalTreasuryService */
    private $royalTreasuryServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&NotificationHelper */
    private $notificationHelperMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamRosterService */
    private $teamRosterServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\TeamChronicle\TeamChronicleService */
    private $teamChronicleServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamChemistryService */
    private $teamChemistryServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroRatingCalculator */
    private $heroRatingCalculatorMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Config\RaceConfig */
    private $raceConfigMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeadquartersService */
    private $hqServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TrainingService */
    private $trainingServiceMock;
    private MarketplaceService $marketplaceService;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->financialCrisisServiceMock = $this->createMock(FinancialCrisisService::class);
        $this->royalTreasuryServiceMock = $this->createMock(\App\Service\Economy\RoyalTreasuryService::class);
        $this->notificationHelperMock = $this->createMock(NotificationHelper::class);
        $this->teamRosterServiceMock = $this->createMock(TeamRosterService::class);
        $this->teamChronicleServiceMock = $this->createMock(\App\Service\TeamChronicle\TeamChronicleService::class);
        $this->teamChemistryServiceMock = $this->createMock(TeamChemistryService::class);
        $this->heroRatingCalculatorMock = $this->createMock(HeroRatingCalculator::class);
        $this->raceConfigMock = $this->createMock(\App\Service\Config\RaceConfig::class);
        $this->hqServiceMock = $this->createMock(HeadquartersService::class);
        $this->trainingServiceMock = $this->createMock(TrainingService::class);

        $symfonyTranslatorMock = $this->createMock(TranslatorInterface::class);
        $requestStackMock = $this->createMock(RequestStack::class);
        $translator = new UserMessageTranslator($symfonyTranslatorMock, $requestStackMock);

        $this->marketplaceService = new MarketplaceService(
            $this->entityManagerMock,
            $this->economyServiceMock,
            $this->financialCrisisServiceMock,
            $this->royalTreasuryServiceMock,
            $this->notificationHelperMock,
            $this->teamRosterServiceMock,
            $this->teamChronicleServiceMock,
            $this->teamChemistryServiceMock,
            $this->heroRatingCalculatorMock,
            $this->raceConfigMock,
            $translator,
            $this->hqServiceMock,
            $this->trainingServiceMock,
        );
    }

    public function testCreateListingRejectsNonPositivePrice(): void
    {
        $seller = new Team();

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.marketplace_price_positive');

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

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.invalid_listing_mode');

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
        $listingRepo->method('find')->willReturnCallback(function ($id) use ($listing) {
            $this->assertSame(42, $id);
            return $listing;
        });

        $this->entityManagerMock
            ->method('getRepository')
            ->willReturnCallback(function ($className) use ($listingRepo) {
                $this->assertSame(MarketplaceListing::class, $className);
                return $listingRepo;
            });

        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->marketplaceService->cancelListing($seller, 42);

        $this->assertSame(HeroStatus::Available, $hero->getStatus());
        $this->assertSame(ListingStatus::Cancelled, $listing->getStatus());
    }

    public function testCreateListingUnequipsHeroItems(): void
    {
        $kingdom = new Kingdom();
        $seller = new Team();
        $seller->setKingdom($kingdom);
        $hero = new Hero();
        $hero->setTeam($seller);
        $hero->setStatus(HeroStatus::Available);

        $item = new Item();
        $item->setOwnerTeam($seller);
        $item->setEquippedHero($hero);
        $item->setEquippedSlot(\App\Enum\ItemSlotType::MainHand);

        $heroRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $heroRepo->method('find')->willReturn($hero);

        $itemRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $itemRepo->expects($this->once())->method('findBy')
            ->with(['equippedHero' => $hero])
            ->willReturn([$item]);

        $formationSlotRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $formationSlotRepo->method('findBy')->willReturn([]);

        $this->entityManagerMock
            ->method('getRepository')
            ->willReturnCallback(function ($className) use ($heroRepo, $itemRepo, $formationSlotRepo) {
                if ($className === Hero::class) {
                    return $heroRepo;
                }
                if ($className === Item::class) {
                    return $itemRepo;
                }
                if ($className === \App\Entity\Formation\FormationSlot::class) {
                    return $formationSlotRepo;
                }
                throw new \InvalidArgumentException("Unexpected class name: $className");
            });

        $this->teamRosterServiceMock
            ->expects($this->once())
            ->method('assertCanRemoveCombatReadyHero')
            ->with($seller, $hero);

        $this->entityManagerMock->expects($this->once())->method('persist');
        $this->entityManagerMock->expects($this->once())->method('flush');

        $listing = $this->marketplaceService->createListing(
            $seller,
            ListingType::Hero->value,
            1,
            100,
            null,
            ListingMode::BuyNow->value,
            7,
            new \DateTimeImmutable('now')
        );

        $this->assertSame(HeroStatus::Selling, $hero->getStatus());
        $this->assertNull($item->getEquippedHero());
        $this->assertNull($item->getEquippedSlot());
    }
}
