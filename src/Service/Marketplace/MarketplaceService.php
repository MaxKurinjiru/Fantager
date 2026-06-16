<?php

declare(strict_types=1);

namespace App\Service\Marketplace;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Marketplace\MarketplaceBid;
use App\Entity\Marketplace\MarketplaceListing;
use App\Entity\Marketplace\MarketplaceTransaction;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\HeroStatus;
use App\Enum\ItemStatus;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Enum\NotificationType;
use App\Enum\RoyalTreasuryContributionSource;
use App\Enum\TransactionType;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Economy\RoyalTreasuryService;
use App\Service\Notification\NotificationHelper;
use App\Service\Team\TeamRosterService;
use Doctrine\ORM\EntityManagerInterface;

class MarketplaceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EconomyService $economyService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly RoyalTreasuryService $royalTreasuryService,
        private readonly NotificationHelper $notificationHelper,
        private readonly TeamRosterService $teamRosterService,
    ) {
    }

    public function createListing(
        Team $seller,
        string $type,
        int $entityId,
        int $priceGold,
        ?int $buyoutPriceGold,
        string $mode,
        int $durationDays,
        \DateTimeImmutable $now,
    ): MarketplaceListing {
        if ($priceGold <= 0) {
            throw new \DomainException('Price or starting bid must be positive.');
        }

        $listingMode = ListingMode::tryFrom($mode);
        if (null === $listingMode) {
            throw new \InvalidArgumentException(sprintf('Invalid listing mode "%s".', $mode));
        }

        $listingType = ListingType::tryFrom($type);
        if (null === $listingType) {
            throw new \InvalidArgumentException(sprintf('Invalid listing type "%s".', $type));
        }

        if (ListingMode::BuyNow === $listingMode) {
            $buyoutPriceGold = $priceGold;
        } else { // Auction
            if (null !== $buyoutPriceGold && $buyoutPriceGold <= $priceGold) {
                throw new \DomainException('Buyout price must be higher than starting bid.');
            }
        }

        $listing = new MarketplaceListing();
        $listing->setKingdom($seller->getKingdom());
        $listing->setSellerTeam($seller);
        $listing->setListingType($listingType);
        $listing->setListingMode($listingMode);
        $listing->setPriceGold($priceGold);
        $listing->setBuyoutPriceGold($buyoutPriceGold);
        $listing->setExpiresAt($now->modify(sprintf('+%d days', $durationDays)));
        $listing->setStatus(ListingStatus::Active);

        // Fetch and escrow the entity
        if (ListingType::Hero === $listingType) {
            /** @var Hero|null $hero */
            $hero = $this->em->getRepository(Hero::class)->find($entityId);
            if (null === $hero || $hero->getTeam()->getId() !== $seller->getId()) {
                throw new \DomainException('Hero not found or does not belong to your team.');
            }
            if (HeroStatus::Available !== $hero->getStatus()) {
                throw new \DomainException('Only available heroes can be listed.');
            }

            if (null !== $hero->getTrainer()) {
                throw new \DomainException('Hero is assigned to a trainer. Unassign before listing.');
            }

            $this->teamRosterService->assertCanRemoveCombatReadyHero($seller, $hero);

            // Escrow hero
            $hero->setStatus(HeroStatus::Selling);
            $listing->setHero($hero);

            // Remove hero from active formations
            /** @var list<\App\Entity\Formation\FormationSlot> $slots */
            $slots = $this->em->getRepository(\App\Entity\Formation\FormationSlot::class)->findBy(['hero' => $hero]);
            foreach ($slots as $slot) {
                $slot->setHero(null);
            }
        } elseif (ListingType::Item === $listingType) {
            /** @var Item|null $item */
            $item = $this->em->getRepository(Item::class)->find($entityId);
            if (null === $item || $item->getOwnerTeam()->getId() !== $seller->getId()) {
                throw new \DomainException('Item not found or does not belong to your team.');
            }
            if (ItemStatus::Available !== $item->getStatus() || null !== $item->getEquippedHero()) {
                throw new \DomainException('Only unequipped available items can be listed.');
            }

            // Escrow item
            $item->setStatus(ItemStatus::Selling);
            $listing->setItem($item);
        } elseif (ListingType::Trainer === $listingType) {
            /** @var Hero|null $trainer */
            $trainer = $this->em->getRepository(Hero::class)->find($entityId);
            if (null === $trainer || $trainer->getTeam()->getId() !== $seller->getId() || !$trainer->isTrainer()) {
                throw new \DomainException('Trainer not found or does not belong to your team.');
            }
            if (HeroStatus::Available !== $trainer->getStatus()) {
                throw new \DomainException('Only active trainers can be listed.');
            }

            foreach ($trainer->getTrainees() as $hero) {
                $trainer->removeTrainee($hero);
            }

            $trainer->setStatus(HeroStatus::Selling);
            $listing->setHero($trainer);
        }

        $this->em->persist($listing);
        $this->em->flush();

        return $listing;
    }

    public function cancelListing(Team $seller, int $listingId): void
    {
        /** @var MarketplaceListing|null $listing */
        $listing = $this->em->getRepository(MarketplaceListing::class)->find($listingId);
        if (null === $listing || $listing->getSellerTeam()->getId() !== $seller->getId()) {
            throw new \DomainException('Listing not found.');
        }

        if (ListingStatus::Active !== $listing->getStatus()) {
            throw new \DomainException('Only active listings can be cancelled.');
        }

        if (count($listing->getBids()) > 0) {
            throw new \DomainException('Auctions with bids cannot be cancelled.');
        }

        $listing->setStatus(ListingStatus::Cancelled);

        // Restore entity
        if (ListingType::Hero === $listing->getListingType() && null !== $listing->getHero()) {
            $listing->getHero()->setStatus(HeroStatus::Available);
        } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
            $listing->getItem()->setStatus(ItemStatus::Available);
        } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
            $listing->getHero()->setStatus(HeroStatus::Available);
        }

        $this->em->flush();
    }

    public function buyListing(Team $buyer, int $listingId, \DateTimeImmutable $now): void
    {
        /** @var MarketplaceListing|null $listing */
        $listing = $this->em->getRepository(MarketplaceListing::class)->find($listingId);
        if (null === $listing) {
            throw new \DomainException('Listing not found.');
        }

        if (ListingStatus::Active !== $listing->getStatus()) {
            throw new \DomainException('Listing is no longer active.');
        }

        if ($listing->getSellerTeam()->getId() === $buyer->getId()) {
            throw new \DomainException('You cannot purchase your own listing.');
        }

        $buyoutPrice = $listing->getBuyoutPriceGold();
        if (null === $buyoutPrice) {
            throw new \DomainException('This listing does not support instant purchase.');
        }

        if ($buyer->getGold() < $buyoutPrice) {
            throw new \DomainException(sprintf('Insufficient gold. Required: %d, available: %d.', $buyoutPrice, $buyer->getGold()));
        }

        $this->financialCrisisService->assertSpendingAllowed($buyer, 'marketplace_purchase');

        $seller = $listing->getSellerTeam();

        // Calculate tax fee based on Kingdom configuration
        $taxRate = (float) $listing->getKingdom()->getMarketplaceTaxRate();
        $tax = (int) floor(($buyoutPrice * $taxRate) / 100);
        $payout = $buyoutPrice - $tax;

        // Deduct gold from buyer
        $this->economyService->deductGold($buyer, $buyoutPrice, FinancialRecordType::MarketplacePurchase, FinancialRecordActor::Active, ['listing_id' => $listing->getId()]);

        // Pay seller: record sale + deduct fee/tax in seller ledger
        $this->economyService->addGold($seller, $buyoutPrice, FinancialRecordType::MarketplaceSale, FinancialRecordActor::Active, ['listing_id' => $listing->getId()]);
        if ($tax > 0) {
            $this->economyService->deductGold($seller, $tax, FinancialRecordType::MarketplaceFee, FinancialRecordActor::Active, ['listing_id' => $listing->getId()]);
            $this->royalTreasuryService->collectFee(
                $listing->getKingdom(),
                $tax,
                RoyalTreasuryContributionSource::MarketplaceTax,
                ['listing_id' => $listing->getId()],
            );
        }

        // Refund all bids in the reservation pool
        foreach ($listing->getBids() as $bid) {
            $this->economyService->addGold(
                $bid->getBidderTeam(),
                $bid->getBidAmount(),
                FinancialRecordType::MarketplacePurchase,
                FinancialRecordActor::System,
                ['listing_id' => $listing->getId(), 'is_refund' => true]
            );
            $this->em->remove($bid);

            if (null !== $bid->getBidderTeam()->getUser()) {
                $this->notificationHelper->sendNotification(
                    $bid->getBidderTeam()->getUser(),
                    NotificationType::MarketplaceBid,
                    'Outbid / Refunded',
                    sprintf('You were refunded %d gold as listing #%d was bought out.', $bid->getBidAmount(), $listing->getId())
                );
            }
        }

        // Record Transaction
        $transaction = new MarketplaceTransaction();
        $transaction->setBuyerTeam($buyer);
        $transaction->setSellerTeam($seller);
        $transaction->setListing($listing);
        $transaction->setAmount($buyoutPrice);
        $transaction->setFeeAmount($tax);
        $transaction->setType(TransactionType::BuyNow);
        $this->em->persist($transaction);

        // Transfer ownership
        if (ListingType::Hero === $listing->getListingType() && null !== $listing->getHero()) {
            $hero = $listing->getHero();
            $hero->setTeam($buyer);
            $hero->setStatus(HeroStatus::Available);
            $hero->setMorale(50); // Reset morale to base
        } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
            $item = $listing->getItem();
            $item->setOwnerTeam($buyer);
            $item->setStatus(ItemStatus::Available);
        } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
            $trainer = $listing->getHero();
            $trainer->setTeam($buyer);
            $trainer->setStatus(HeroStatus::Available);
        }

        $listing->setStatus(ListingStatus::Sold);
        $this->financialCrisisService->recordRecoveryAction($seller);
        $this->em->flush();

        // Send Notification to Seller
        if (null !== $seller->getUser()) {
            $this->notificationHelper->sendNotification(
                $seller->getUser(),
                NotificationType::MarketplaceSold,
                'Listing Sold',
                sprintf('Your listing #%d was bought by "%s" for %d gold.', $listing->getId(), $buyer->getName(), $buyoutPrice)
            );
        }
    }

    public function placeBid(Team $bidder, int $listingId, int $bidAmount, \DateTimeImmutable $now): void
    {
        /** @var MarketplaceListing|null $listing */
        $listing = $this->em->getRepository(MarketplaceListing::class)->find($listingId);
        if (null === $listing) {
            throw new \DomainException('Listing not found.');
        }

        if (ListingStatus::Active !== $listing->getStatus()) {
            throw new \DomainException('Listing is not active.');
        }

        if (ListingMode::Auction !== $listing->getListingMode()) {
            throw new \DomainException('This listing does not support bidding.');
        }

        if ($listing->getExpiresAt() <= $now) {
            throw new \DomainException('The auction has already expired.');
        }

        if ($listing->getSellerTeam()->getId() === $bidder->getId()) {
            throw new \DomainException('You cannot bid on your own listing.');
        }

        if ($bidder->getGold() < $bidAmount) {
            throw new \DomainException(sprintf('Insufficient gold. Required: %d, available: %d.', $bidAmount, $bidder->getGold()));
        }

        $this->financialCrisisService->assertSpendingAllowed($bidder, 'marketplace_bid');

        // Determine highest bid
        $currentHighestBid = null;
        foreach ($listing->getBids() as $b) {
            if (null === $currentHighestBid || $b->getBidAmount() > $currentHighestBid->getBidAmount()) {
                $currentHighestBid = $b;
            }
        }

        if (null === $currentHighestBid) {
            if ($bidAmount < $listing->getPriceGold()) {
                throw new \DomainException(sprintf('First bid must be at least the starting bid of %d gold.', $listing->getPriceGold()));
            }
        } else {
            // Bid increment rule: 5% of highest bid, rounded to whole tens
            $minIncrement = (int) ceil(($currentHighestBid->getBidAmount() * 0.05) / 10) * 10;
            $minNextBid = $currentHighestBid->getBidAmount() + $minIncrement;
            if ($bidAmount < $minNextBid) {
                throw new \DomainException(sprintf('Bid is too low. Minimum next bid is %d gold (min increment: %d).', $minNextBid, $minIncrement));
            }
        }

        // Deduct and reserve gold immediately from bidder
        $this->economyService->deductGold($bidder, $bidAmount, FinancialRecordType::MarketplacePurchase, FinancialRecordActor::Active, ['listing_id' => $listing->getId(), 'is_bid_reservation' => true]);

        // Record Bid
        $bid = new MarketplaceBid();
        $bid->setListing($listing);
        $bid->setBidderTeam($bidder);
        $bid->setBidAmount($bidAmount);
        $bid->setBidTime($now);
        $this->em->persist($bid);
        $this->em->flush();

        // Send Notification to Seller
        if (null !== $listing->getSellerTeam()->getUser()) {
            $this->notificationHelper->sendNotification(
                $listing->getSellerTeam()->getUser(),
                NotificationType::MarketplaceBid,
                'New Bid Placed',
                sprintf('A new bid of %d gold was placed on listing #%d.', $bidAmount, $listing->getId())
            );
        }
    }

    public function processExpiredListings(\DateTimeImmutable $now): void
    {
        $this->processExpiredListingsForKingdom(null, $now);
    }

    public function processExpiredListingsForKingdom(?Kingdom $kingdom, \DateTimeImmutable $now): void
    {
        /** @var list<MarketplaceListing> $listings */
        $listings = $this->findExpiredActiveListings($kingdom, $now);

        foreach ($listings as $listing) {
            $bids = $listing->getBids();
            $highestBid = null;

            foreach ($bids as $bid) {
                if (null === $highestBid || $bid->getBidAmount() > $highestBid->getBidAmount()) {
                    $highestBid = $bid;
                }
            }

            if (null === $highestBid) {
                // Expired with no bids: return entity to seller
                $listing->setStatus(ListingStatus::Expired);

                if (ListingType::Hero === $listing->getListingType() && null !== $listing->getHero()) {
                    $listing->getHero()->setStatus(HeroStatus::Available);
                } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
                    $listing->getItem()->setStatus(ItemStatus::Available);
                } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
                    $listing->getHero()->setStatus(HeroStatus::Available);
                }

                if (null !== $listing->getSellerTeam()->getUser()) {
                    $this->notificationHelper->sendNotification(
                        $listing->getSellerTeam()->getUser(),
                        NotificationType::System,
                        'Listing Expired',
                        sprintf('Your listing #%d expired with no bids. The entity has been returned to your roster/inventory.', $listing->getId())
                    );
                }
            } else {
                // Sold to highest bidder
                $winner = $highestBid->getBidderTeam();
                $winningBidAmount = $highestBid->getBidAmount();
                $seller = $listing->getSellerTeam();

                // Calculate tax fee
                $taxRate = (float) $listing->getKingdom()->getMarketplaceTaxRate();
                $tax = (int) floor(($winningBidAmount * $taxRate) / 100);
                $payout = $winningBidAmount - $tax;

                // Gold is already deducted from winner's wallet as reservation, so we just pay seller
                $this->economyService->addGold($seller, $winningBidAmount, FinancialRecordType::MarketplaceSale, FinancialRecordActor::System, ['listing_id' => $listing->getId()]);
                if ($tax > 0) {
                    $this->economyService->deductGold($seller, $tax, FinancialRecordType::MarketplaceFee, FinancialRecordActor::System, ['listing_id' => $listing->getId()]);
                    $this->royalTreasuryService->collectFee(
                        $listing->getKingdom(),
                        $tax,
                        RoyalTreasuryContributionSource::MarketplaceTax,
                        ['listing_id' => $listing->getId()],
                    );
                }

                // Refund all OTHER (losing) bids
                foreach ($bids as $bid) {
                    if ($bid->getId() !== $highestBid->getId()) {
                        $this->economyService->addGold(
                            $bid->getBidderTeam(),
                            $bid->getBidAmount(),
                            FinancialRecordType::MarketplacePurchase,
                            FinancialRecordActor::System,
                            ['listing_id' => $listing->getId(), 'is_refund' => true]
                        );

                        if (null !== $bid->getBidderTeam()->getUser()) {
                            $this->notificationHelper->sendNotification(
                                $bid->getBidderTeam()->getUser(),
                                NotificationType::MarketplaceBid,
                                'Auction Refund',
                                sprintf('Your bid of %d gold on listing #%d was refunded.', $bid->getBidAmount(), $listing->getId())
                            );
                        }
                    }
                    $this->em->remove($bid);
                }

                // Record Transaction
                $transaction = new MarketplaceTransaction();
                $transaction->setBuyerTeam($winner);
                $transaction->setSellerTeam($seller);
                $transaction->setListing($listing);
                $transaction->setAmount($winningBidAmount);
                $transaction->setFeeAmount($tax);
                $transaction->setType(TransactionType::AuctionWin);
                $this->em->persist($transaction);

                // Transfer ownership
                if (ListingType::Hero === $listing->getListingType() && null !== $listing->getHero()) {
                    $hero = $listing->getHero();
                    $hero->setTeam($winner);
                    $hero->setStatus(HeroStatus::Available);
                    $hero->setMorale(50);
                } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
                    $item = $listing->getItem();
                    $item->setOwnerTeam($winner);
                    $item->setStatus(ItemStatus::Available);
                } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
                    $trainer = $listing->getHero();
                    $trainer->setTeam($winner);
                    $trainer->setStatus(HeroStatus::Available);
                }

                $listing->setStatus(ListingStatus::Sold);
                $this->financialCrisisService->recordRecoveryAction($seller);

                // Notifications
                if (null !== $seller->getUser()) {
                    $this->notificationHelper->sendNotification(
                        $seller->getUser(),
                        NotificationType::MarketplaceSold,
                        'Auction Closed',
                        sprintf('Your auction #%d sold to "%s" for %d gold.', $listing->getId(), $winner->getName(), $winningBidAmount)
                    );
                }

                if (null !== $winner->getUser()) {
                    $this->notificationHelper->sendNotification(
                        $winner->getUser(),
                        NotificationType::MarketplaceSold,
                        'Auction Won',
                        sprintf('You won the auction #%d for %d gold.', $listing->getId(), $winningBidAmount)
                    );
                }
            }
        }

        $this->em->flush();
    }

    /**
     * @return list<MarketplaceListing>
     */
    private function findExpiredActiveListings(?Kingdom $kingdom, \DateTimeImmutable $now): array
    {
        $qb = $this->em->getRepository(MarketplaceListing::class)->createQueryBuilder('l')
            ->where('l.status = :status')
            ->andWhere('l.expiresAt <= :now')
            ->setParameter('status', ListingStatus::Active)
            ->setParameter('now', $now);

        if (null !== $kingdom) {
            $qb->andWhere('l.kingdom = :kingdom')
                ->setParameter('kingdom', $kingdom);
        }

        return $qb->getQuery()->getResult();
    }
}
