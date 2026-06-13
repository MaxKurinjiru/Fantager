<?php

declare(strict_types=1);

namespace App\Service\Marketplace;

use App\Entity\Team\Team;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Training\Trainer;
use App\Entity\Marketplace\MarketplaceListing;
use App\Entity\Marketplace\MarketplaceBid;
use App\Entity\Marketplace\Transaction;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Enum\TransactionType;
use App\Enum\HeroStatus;
use App\Enum\TrainerStatus;
use App\Enum\ItemStatus;
use App\Enum\FinancialRecordType;
use App\Enum\FinancialRecordActor;
use App\Enum\NotificationType;
use App\Service\Economy\EconomyService;
use App\Service\Notification\NotificationHelper;
use Doctrine\ORM\EntityManagerInterface;

class MarketplaceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EconomyService $economyService,
        private readonly NotificationHelper $notificationHelper,
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
        \DateTimeImmutable $now
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

        if ($listingMode === ListingMode::BuyNow) {
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
        if ($listingType === ListingType::Hero) {
            /** @var Hero|null $hero */
            $hero = $this->em->getRepository(Hero::class)->find($entityId);
            if (null === $hero || $hero->getTeam()->getId() !== $seller->getId()) {
                throw new \DomainException('Hero not found or does not belong to your team.');
            }
            if ($hero->getStatus() !== HeroStatus::Available && $hero->getStatus() !== HeroStatus::Tired) {
                throw new \DomainException('Only available or tired heroes can be listed.');
            }

            // Escrow hero
            $hero->setStatus(HeroStatus::Selling);
            $listing->setHero($hero);

            // Remove hero from active formations
            $slots = $this->em->getRepository(\App\Entity\Formation\FormationSlot::class)->findBy(['hero' => $hero]);
            foreach ($slots as $slot) {
                $slot->setHero(null);
            }
        } elseif ($listingType === ListingType::Item) {
            /** @var Item|null $item */
            $item = $this->em->getRepository(Item::class)->find($entityId);
            if (null === $item || $item->getOwnerTeam()->getId() !== $seller->getId()) {
                throw new \DomainException('Item not found or does not belong to your team.');
            }
            if ($item->getStatus() !== ItemStatus::Available || null !== $item->getEquippedHero()) {
                throw new \DomainException('Only unequipped available items can be listed.');
            }

            // Escrow item
            $item->setStatus(ItemStatus::Selling);
            $listing->setItem($item);
        } elseif ($listingType === ListingType::Trainer) {
            /** @var Trainer|null $trainer */
            $trainer = $this->em->getRepository(Trainer::class)->find($entityId);
            if (null === $trainer || $trainer->getTeam()->getId() !== $seller->getId()) {
                throw new \DomainException('Trainer not found or does not belong to your team.');
            }
            if ($trainer->getStatus() !== TrainerStatus::Active) {
                throw new \DomainException('Only active trainers can be listed.');
            }

            // Unassign all trainees (heroes)
            foreach ($trainer->getHeroes() as $hero) {
                $trainer->removeHero($hero);
                $hero->setStatus(HeroStatus::Available);
            }

            // Escrow trainer
            $trainer->setStatus(TrainerStatus::Selling);
            $listing->setTrainer($trainer);
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

        if ($listing->getStatus() !== ListingStatus::Active) {
            throw new \DomainException('Only active listings can be cancelled.');
        }

        if (count($listing->getBids()) > 0) {
            throw new \DomainException('Auctions with bids cannot be cancelled.');
        }

        $listing->setStatus(ListingStatus::Cancelled);

        // Restore entity
        if ($listing->getListingType() === ListingType::Hero && null !== $listing->getHero()) {
            $listing->getHero()->setStatus(HeroStatus::Available);
        } elseif ($listing->getListingType() === ListingType::Item && null !== $listing->getItem()) {
            $listing->getItem()->setStatus(ItemStatus::Available);
        } elseif ($listing->getListingType() === ListingType::Trainer && null !== $listing->getTrainer()) {
            $listing->getTrainer()->setStatus(TrainerStatus::Active);
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

        if ($listing->getStatus() !== ListingStatus::Active) {
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

            if ($bid->getBidderTeam()->getUser() !== null) {
                $this->notificationHelper->sendNotification(
                    $bid->getBidderTeam()->getUser(),
                    NotificationType::MarketplaceBid,
                    'Outbid / Refunded',
                    sprintf('You were refunded %d gold as listing #%d was bought out.', $bid->getBidAmount(), $listing->getId())
                );
            }
        }

        // Record Transaction
        $transaction = new Transaction();
        $transaction->setBuyerTeam($buyer);
        $transaction->setSellerTeam($seller);
        $transaction->setListing($listing);
        $transaction->setAmount($buyoutPrice);
        $transaction->setFeeAmount($tax);
        $transaction->setType(TransactionType::BuyNow);
        $this->em->persist($transaction);

        // Transfer ownership
        if ($listing->getListingType() === ListingType::Hero && null !== $listing->getHero()) {
            $hero = $listing->getHero();
            $hero->setTeam($buyer);
            $hero->setStatus(HeroStatus::Available);
            $hero->setMorale(50); // Reset morale to base
        } elseif ($listing->getListingType() === ListingType::Item && null !== $listing->getItem()) {
            $item = $listing->getItem();
            $item->setOwnerTeam($buyer);
            $item->setStatus(ItemStatus::Available);
        } elseif ($listing->getListingType() === ListingType::Trainer && null !== $listing->getTrainer()) {
            $trainer = $listing->getTrainer();
            $trainer->setTeam($buyer);
            $trainer->setStatus(TrainerStatus::Active);
        }

        $listing->setStatus(ListingStatus::Sold);
        $this->em->flush();

        // Send Notification to Seller
        if ($seller->getUser() !== null) {
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

        if ($listing->getStatus() !== ListingStatus::Active) {
            throw new \DomainException('Listing is not active.');
        }

        if ($listing->getListingMode() !== ListingMode::Auction) {
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

        // Determine highest bid
        $currentHighestBid = null;
        foreach ($listing->getBids() as $b) {
            if ($currentHighestBid === null || $b->getBidAmount() > $currentHighestBid->getBidAmount()) {
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
        if ($listing->getSellerTeam()->getUser() !== null) {
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
        $listings = $this->em->getRepository(MarketplaceListing::class)->createQueryBuilder('l')
            ->where('l.status = :status')
            ->andWhere('l.expiresAt <= :now')
            ->setParameter('status', ListingStatus::Active)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        /** @var list<MarketplaceListing> $listings */
        foreach ($listings as $listing) {
            $bids = $listing->getBids();
            $highestBid = null;

            foreach ($bids as $bid) {
                if ($highestBid === null || $bid->getBidAmount() > $highestBid->getBidAmount()) {
                    $highestBid = $bid;
                }
            }

            if (null === $highestBid) {
                // Expired with no bids: return entity to seller
                $listing->setStatus(ListingStatus::Expired);

                if ($listing->getListingType() === ListingType::Hero && null !== $listing->getHero()) {
                    $listing->getHero()->setStatus(HeroStatus::Available);
                } elseif ($listing->getListingType() === ListingType::Item && null !== $listing->getItem()) {
                    $listing->getItem()->setStatus(ItemStatus::Available);
                } elseif ($listing->getListingType() === ListingType::Trainer && null !== $listing->getTrainer()) {
                    $listing->getTrainer()->setStatus(TrainerStatus::Active);
                }

                if ($listing->getSellerTeam()->getUser() !== null) {
                    $this->notificationHelper->sendNotification(
                        $listing->getSellerTeam()->getUser(),
                        NotificationType::QuestExpired, // reusing expired notification type
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

                        if ($bid->getBidderTeam()->getUser() !== null) {
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
                $transaction = new Transaction();
                $transaction->setBuyerTeam($winner);
                $transaction->setSellerTeam($seller);
                $transaction->setListing($listing);
                $transaction->setAmount($winningBidAmount);
                $transaction->setFeeAmount($tax);
                $transaction->setType(TransactionType::AuctionWin);
                $this->em->persist($transaction);

                // Transfer ownership
                if ($listing->getListingType() === ListingType::Hero && null !== $listing->getHero()) {
                    $hero = $listing->getHero();
                    $hero->setTeam($winner);
                    $hero->setStatus(HeroStatus::Available);
                    $hero->setMorale(50);
                } elseif ($listing->getListingType() === ListingType::Item && null !== $listing->getItem()) {
                    $item = $listing->getItem();
                    $item->setOwnerTeam($winner);
                    $item->setStatus(ItemStatus::Available);
                } elseif ($listing->getListingType() === ListingType::Trainer && null !== $listing->getTrainer()) {
                    $trainer = $listing->getTrainer();
                    $trainer->setTeam($winner);
                    $trainer->setStatus(TrainerStatus::Active);
                }

                $listing->setStatus(ListingStatus::Sold);

                // Notifications
                if ($seller->getUser() !== null) {
                    $this->notificationHelper->sendNotification(
                        $seller->getUser(),
                        NotificationType::MarketplaceSold,
                        'Auction Closed',
                        sprintf('Your auction #%d sold to "%s" for %d gold.', $listing->getId(), $winner->getName(), $winningBidAmount)
                    );
                }

                if ($winner->getUser() !== null) {
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
}
