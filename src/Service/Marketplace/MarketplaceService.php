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
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\ItemStatus;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Enum\NotificationType;
use App\Enum\Race;
use App\Enum\RoyalTreasuryContributionSource;
use App\Enum\TransactionType;
use App\Exception\UserFacingException;
use App\Service\Config\RaceConfig;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Economy\RoyalTreasuryService;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Notification\NotificationHelper;
use App\Service\Team\TeamChemistryService;
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
        private readonly \App\Service\TeamChronicle\TeamChronicleService $teamChronicleService,
        private readonly TeamChemistryService $teamChemistryService,
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly RaceConfig $raceConfig,
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
            throw new UserFacingException('error.marketplace_price_positive');
        }

        $listingMode = ListingMode::tryFrom($mode);
        if (null === $listingMode) {
            throw new UserFacingException('error.invalid_listing_mode', ['%mode%' => $mode]);
        }

        $listingType = ListingType::tryFrom($type);
        if (null === $listingType) {
            throw new UserFacingException('error.invalid_listing_type', ['%type%' => $type]);
        }

        if (ListingMode::BuyNow === $listingMode) {
            $buyoutPriceGold = $priceGold;
        } else { // Auction
            if (null !== $buyoutPriceGold && $buyoutPriceGold <= $priceGold) {
                throw new UserFacingException('error.marketplace_buyout_higher');
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
                throw new UserFacingException('error.marketplace_hero_not_found');
            }
            if (HeroStatus::Available !== $hero->getStatus()) {
                throw new UserFacingException('error.marketplace_hero_only_available');
            }

            if (null !== $hero->getTrainer()) {
                throw new UserFacingException('error.marketplace_hero_assigned_trainer');
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
                throw new UserFacingException('error.marketplace_item_not_found');
            }
            if (ItemStatus::Available !== $item->getStatus() || null !== $item->getEquippedHero()) {
                throw new UserFacingException('error.marketplace_item_only_unequipped');
            }

            // Escrow item
            $item->setStatus(ItemStatus::Selling);
            $listing->setItem($item);
        } elseif (ListingType::Trainer === $listingType) {
            /** @var Hero|null $trainer */
            $trainer = $this->em->getRepository(Hero::class)->find($entityId);
            if (null === $trainer || $trainer->getTeam()->getId() !== $seller->getId() || !$trainer->isTrainer()) {
                throw new UserFacingException('error.marketplace_trainer_not_found');
            }
            if (HeroStatus::Available !== $trainer->getStatus()) {
                throw new UserFacingException('error.trainer_only_active_list');
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
            throw new UserFacingException('error.listing_not_found');
        }

        if (ListingStatus::Active !== $listing->getStatus()) {
            throw new UserFacingException('error.marketplace_only_active_cancel');
        }

        if (count($listing->getBids()) > 0) {
            throw new UserFacingException('error.marketplace_auction_has_bids');
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
            throw new UserFacingException('error.listing_not_found');
        }

        if (ListingStatus::Active !== $listing->getStatus()) {
            throw new UserFacingException('error.marketplace_listing_inactive');
        }

        if ($listing->getSellerTeam()->getId() === $buyer->getId()) {
            throw new UserFacingException('error.marketplace_cannot_buy_own');
        }

        $buyoutPrice = $listing->getBuyoutPriceGold();
        if (null === $buyoutPrice) {
            throw new UserFacingException('error.marketplace_no_instant_purchase');
        }

        if ($buyer->getGold() < $buyoutPrice) {
            throw new UserFacingException('error.insufficient_gold', ['%required%' => $buyoutPrice, '%available%' => $buyer->getGold()]);
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
                $this->notificationHelper->sendTranslatedNotification(
                    $bid->getBidderTeam()->getUser(),
                    NotificationType::MarketplaceBid,
                    'notification.marketplace_outbid_title',
                    'notification.marketplace_outbid_body',
                    [],
                    ['%amount%' => $bid->getBidAmount(), '%listing%' => (int) $listing->getId()]
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
            $this->teamChronicleService->recordHeroPurchased($buyer, $hero, $seller, $buyoutPrice);
            $this->teamChronicleService->recordHeroSold($seller, $hero, $buyer, $buyoutPrice);
        } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
            $item = $listing->getItem();
            $item->setOwnerTeam($buyer);
            $item->setStatus(ItemStatus::Available);
        } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
            $trainer = $listing->getHero();
            $trainer->setTeam($buyer);
            $trainer->setStatus(HeroStatus::Available);
            $this->teamChronicleService->recordTrainerPurchased($buyer, $trainer, $seller, $buyoutPrice);
            $this->teamChronicleService->recordTrainerSold($seller, $trainer, $buyer, $buyoutPrice);
        }

        $listing->setStatus(ListingStatus::Sold);
        $this->financialCrisisService->recordRecoveryAction($seller);
        $this->em->flush();

        if (ListingType::Hero === $listing->getListingType() && null !== $listing->getHero()) {
            $this->teamChemistryService->recalculate($buyer);
            $this->teamChemistryService->recalculate($seller);
        }

        // Send Notification to Seller
        if (null !== $seller->getUser()) {
            $this->notificationHelper->sendTranslatedNotification(
                $seller->getUser(),
                NotificationType::MarketplaceSold,
                'notification.marketplace_sold_title',
                'notification.marketplace_sold_body',
                [],
                ['%listing%' => (int) $listing->getId(), '%buyer%' => $buyer->getName(), '%amount%' => $buyoutPrice]
            );
        }
    }

    public function placeBid(Team $bidder, int $listingId, int $bidAmount, \DateTimeImmutable $now): void
    {
        /** @var MarketplaceListing|null $listing */
        $listing = $this->em->getRepository(MarketplaceListing::class)->find($listingId);
        if (null === $listing) {
            throw new UserFacingException('error.listing_not_found');
        }

        if (ListingStatus::Active !== $listing->getStatus()) {
            throw new UserFacingException('error.marketplace_not_active');
        }

        if (ListingMode::Auction !== $listing->getListingMode()) {
            throw new UserFacingException('error.marketplace_no_bidding');
        }

        if ($listing->getExpiresAt() <= $now) {
            throw new UserFacingException('error.marketplace_auction_expired');
        }

        if ($listing->getSellerTeam()->getId() === $bidder->getId()) {
            throw new UserFacingException('error.marketplace_cannot_bid_own');
        }

        if ($bidder->getGold() < $bidAmount) {
            throw new UserFacingException('error.insufficient_gold', ['%required%' => $bidAmount, '%available%' => $bidder->getGold()]);
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
                throw new UserFacingException('error.marketplace_first_bid_minimum', ['%amount%' => $listing->getPriceGold()]);
            }
        } else {
            // Bid increment rule: 5% of highest bid, rounded to whole tens
            $minIncrement = (int) ceil(($currentHighestBid->getBidAmount() * 0.05) / 10) * 10;
            $minNextBid = $currentHighestBid->getBidAmount() + $minIncrement;
            if ($bidAmount < $minNextBid) {
                throw new UserFacingException('error.marketplace_bid_too_low', ['%min%' => $minNextBid, '%increment%' => $minIncrement]);
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
            $this->notificationHelper->sendTranslatedNotification(
                $listing->getSellerTeam()->getUser(),
                NotificationType::MarketplaceBid,
                'notification.marketplace_new_bid_title',
                'notification.marketplace_new_bid_body',
                [],
                ['%amount%' => $bidAmount, '%listing%' => (int) $listing->getId()]
            );
        }
    }

    public function processExpiredListings(\DateTimeImmutable $now): void
    {
        $this->processExpiredListingsForKingdom(null, $now);
    }

    public function processExpiredListingsForKingdom(?Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        /** @var list<MarketplaceListing> $listings */
        $listings = $this->findExpiredActiveListings($kingdom, $now, $team);
        $teamsToRecalculate = [];

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
                    $this->notificationHelper->sendTranslatedNotification(
                        $listing->getSellerTeam()->getUser(),
                        NotificationType::System,
                        'notification.marketplace_expired_title',
                        'notification.marketplace_expired_body',
                        [],
                        ['%listing%' => (int) $listing->getId()]
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
                            $this->notificationHelper->sendTranslatedNotification(
                                $bid->getBidderTeam()->getUser(),
                                NotificationType::MarketplaceBid,
                                'notification.marketplace_bid_refunded_title',
                                'notification.marketplace_bid_refunded_body',
                                [],
                                ['%amount%' => $bid->getBidAmount(), '%listing%' => (int) $listing->getId()]
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
                    $this->teamChronicleService->recordHeroPurchased($winner, $hero, $seller, $winningBidAmount);
                    $this->teamChronicleService->recordHeroSold($seller, $hero, $winner, $winningBidAmount);
                    $teamsToRecalculate[] = $winner;
                    $teamsToRecalculate[] = $seller;
                } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
                    $item = $listing->getItem();
                    $item->setOwnerTeam($winner);
                    $item->setStatus(ItemStatus::Available);
                } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
                    $trainer = $listing->getHero();
                    $trainer->setTeam($winner);
                    $trainer->setStatus(HeroStatus::Available);
                    $this->teamChronicleService->recordTrainerPurchased($winner, $trainer, $seller, $winningBidAmount);
                    $this->teamChronicleService->recordTrainerSold($seller, $trainer, $winner, $winningBidAmount);
                }

                $listing->setStatus(ListingStatus::Sold);
                $this->financialCrisisService->recordRecoveryAction($seller);

                // Notifications
                if (null !== $seller->getUser()) {
                    $this->notificationHelper->sendTranslatedNotification(
                        $seller->getUser(),
                        NotificationType::MarketplaceSold,
                        'notification.marketplace_auction_won_seller_title',
                        'notification.marketplace_auction_won_seller_body',
                        [],
                        ['%listing%' => (int) $listing->getId(), '%buyer%' => $winner->getName(), '%amount%' => $winningBidAmount]
                    );
                }

                if (null !== $winner->getUser()) {
                    $this->notificationHelper->sendTranslatedNotification(
                        $winner->getUser(),
                        NotificationType::MarketplaceSold,
                        'notification.marketplace_auction_won_buyer_title',
                        'notification.marketplace_auction_won_buyer_body',
                        [],
                        ['%listing%' => (int) $listing->getId(), '%amount%' => $winningBidAmount]
                    );
                }
            }
        }

        $this->em->flush();

        $uniqueTeams = [];
        foreach ($teamsToRecalculate as $team) {
            if (!in_array($team, $uniqueTeams, true)) {
                $uniqueTeams[] = $team;
            }
        }

        foreach ($uniqueTeams as $team) {
            $this->teamChemistryService->recalculate($team);
        }
    }

    /**
     * @return array{
     *     heroes: list<Hero>,
     *     items: list<Item>,
     *     trainers: list<Hero>
     * }
     */
    public function getSellableAssets(Team $team): array
    {
        return [
            'heroes' => $this->em->getRepository(Hero::class)->findBy([
                'team' => $team,
                'role' => HeroRole::Combatant,
                'status' => HeroStatus::Available,
            ]),
            'items' => $this->em->getRepository(Item::class)->findBy([
                'ownerTeam' => $team,
                'status' => ItemStatus::Available,
                'equippedHero' => null,
            ]),
            'trainers' => $this->em->getRepository(Hero::class)->findBy([
                'team' => $team,
                'role' => HeroRole::Trainer,
                'status' => HeroStatus::Available,
            ]),
        ];
    }

    /**
     * @param array{
     *     type?: string|null,
     *     race?: string|null,
     *     level_min?: string|null,
     *     level_max?: string|null,
     *     rarity?: string|null,
     *     price_min?: string|null,
     *     price_max?: string|null,
     *     rating_min?: string|null,
     *     rating_max?: string|null,
     *     base_ovr_min?: string|null,
     *     base_ovr_max?: string|null,
     *     age_phase?: string|null,
     *     seller_reputation_min?: string|null,
     *     search?: string|null,
     *     sort?: string|null
     * } $filters
     */
    private function createSearchListingsQueryBuilder(Kingdom $kingdom, array $filters): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('l')
            ->from(MarketplaceListing::class, 'l')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.status = :active')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('active', ListingStatus::Active);

        $type = $filters['type'] ?? null;
        if ($type) {
            $qb->andWhere('l.listingType = :type')
                ->setParameter('type', $type);
        }

        $race = $filters['race'] ?? null;
        $levelMin = $filters['level_min'] ?? null;
        $levelMax = $filters['level_max'] ?? null;
        $rarity = $filters['rarity'] ?? null;
        $priceMin = $filters['price_min'] ?? null;
        $priceMax = $filters['price_max'] ?? null;
        $ratingMin = $filters['rating_min'] ?? null;
        $ratingMax = $filters['rating_max'] ?? null;
        $baseOvrMin = $filters['base_ovr_min'] ?? null;
        $baseOvrMax = $filters['base_ovr_max'] ?? null;
        $agePhase = $filters['age_phase'] ?? null;
        $sellerReputationMin = $filters['seller_reputation_min'] ?? null;
        $search = $filters['search'] ?? null;

        if ($race || $levelMin || $levelMax || $ratingMin || $ratingMax || $baseOvrMin || $baseOvrMax || $agePhase || $search) {
            $qb->leftJoin('l.hero', 'h');
        }

        if ($sellerReputationMin) {
            $qb->join('l.sellerTeam', 'st');
        }

        if ($rarity || $search) {
            $qb->leftJoin('l.item', 'it');
        }

        if ($race) {
            $qb->andWhere('h.race = :race')
                ->setParameter('race', $race);
        }

        if (null !== $levelMin && '' !== $levelMin) {
            $qb->andWhere('h.level >= :levelMin')
                ->setParameter('levelMin', (int) $levelMin);
        }

        if (null !== $levelMax && '' !== $levelMax) {
            $qb->andWhere('h.level <= :levelMax')
                ->setParameter('levelMax', (int) $levelMax);
        }

        if (null !== $ratingMin && '' !== $ratingMin) {
            $qb->andWhere('h.complexRating >= :ratingMin')
                ->setParameter('ratingMin', (int) $ratingMin);
        }

        if (null !== $ratingMax && '' !== $ratingMax) {
            $qb->andWhere('h.complexRating <= :ratingMax')
                ->setParameter('ratingMax', (int) $ratingMax);
        }

        if (null !== $baseOvrMin && '' !== $baseOvrMin) {
            $qb->andWhere('h.baseOvr >= :baseOvrMin')
                ->setParameter('baseOvrMin', (int) $baseOvrMin);
        }

        if (null !== $baseOvrMax && '' !== $baseOvrMax) {
            $qb->andWhere('h.baseOvr <= :baseOvrMax')
                ->setParameter('baseOvrMax', (int) $baseOvrMax);
        }

        if (null !== $agePhase && '' !== $agePhase) {
            $this->applyAgePhaseFilter($qb, (string) $agePhase);
        }

        if (null !== $sellerReputationMin && '' !== $sellerReputationMin) {
            $qb->andWhere('st.reputation >= :sellerReputationMin')
                ->setParameter('sellerReputationMin', (int) $sellerReputationMin);
        }

        if ($rarity) {
            $qb->andWhere('it.rarity = :rarity')
                ->setParameter('rarity', $rarity);
        }

        if (null !== $priceMin && '' !== $priceMin) {
            $qb->andWhere('l.priceGold >= :priceMin OR l.buyoutPriceGold >= :priceMin')
                ->setParameter('priceMin', (int) $priceMin);
        }

        if (null !== $priceMax && '' !== $priceMax) {
            $qb->andWhere('l.priceGold <= :priceMax OR l.buyoutPriceGold <= :priceMax')
                ->setParameter('priceMax', (int) $priceMax);
        }

        if ($search) {
            $qb->andWhere('h.name LIKE :search OR it.name LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        return $qb;
    }

    private function applyAgePhaseFilter(\Doctrine\ORM\QueryBuilder $qb, string $agePhase): void
    {
        $orX = $qb->expr()->orX();
        $index = 0;

        foreach (Race::cases() as $race) {
            $bounds = $this->resolveAgePhaseRawBounds($race, $agePhase);
            if (null === $bounds) {
                continue;
            }

            [$minRaw, $maxRaw] = $bounds;
            $parts = ['h.race = :agePhaseRace'.$index];
            $qb->setParameter('agePhaseRace'.$index, $race->value);

            if (null !== $minRaw) {
                $parts[] = 'h.age >= :agePhaseMin'.$index;
                $qb->setParameter('agePhaseMin'.$index, $minRaw);
            }

            if (null !== $maxRaw) {
                $parts[] = 'h.age <= :agePhaseMax'.$index;
                $qb->setParameter('agePhaseMax'.$index, $maxRaw);
            }

            $orX->add(implode(' AND ', $parts));
            ++$index;
        }

        if ($orX->count() > 0) {
            $qb->andWhere($orX);
        }
    }

    /**
     * @return array{0: ?int, 1: ?int}|null
     */
    private function resolveAgePhaseRawBounds(Race $race, string $agePhase): ?array
    {
        $milestones = $this->raceConfig->getAge($race);
        $maxJunior = $milestones['max_junior'];
        $primeLimit = $milestones['prime_limit'];
        $mortalityThreshold = $milestones['mortality_threshold'];

        return match ($agePhase) {
            'junior' => [null, $maxJunior * 10 + 9],
            'prime' => [$maxJunior * 10 + 10, $primeLimit * 10 + 9],
            'veteran' => [$primeLimit * 10 + 10, max(0, $mortalityThreshold - 1) * 10 + 9],
            'elder' => [$mortalityThreshold * 10, null],
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<MarketplaceListing>
     */
    public function searchListings(Kingdom $kingdom, array $filters, ?int $page = null, ?int $limit = null): array
    {
        $qb = $this->createSearchListingsQueryBuilder($kingdom, $filters);

        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'price_asc':
                $qb->orderBy('l.priceGold', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('l.priceGold', 'DESC');
                break;
            case 'rating_desc':
                $qb->leftJoin('l.hero', 'h_sort');
                $qb->orderBy('h_sort.complexRating', 'DESC');
                break;
            case 'ovr_desc':
                $qb->leftJoin('l.hero', 'h_sort');
                $qb->orderBy('h_sort.baseOvr', 'DESC');
                break;
            case 'expires_asc':
                $qb->orderBy('l.expiresAt', 'ASC');
                break;
            case 'newest':
            default:
                $qb->orderBy('l.id', 'DESC');
                break;
        }

        if (null !== $page && null !== $limit) {
            $qb->setFirstResult(($page - 1) * $limit)
               ->setMaxResults($limit);
        }

        /** @var list<MarketplaceListing> $listings */
        $listings = $qb->getQuery()->getResult();

        return $listings;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function countSearchListings(Kingdom $kingdom, array $filters): int
    {
        $qb = $this->createSearchListingsQueryBuilder($kingdom, $filters);
        $qb->select('COUNT(DISTINCT l.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<MarketplaceListing>
     */
    public function getListingsForSeller(Team $team, ?int $page = null, ?int $limit = null): array
    {
        $offset = (null !== $page && null !== $limit) ? ($page - 1) * $limit : null;

        /** @var list<MarketplaceListing> $listings */
        $listings = $this->em->getRepository(MarketplaceListing::class)->findBy(
            ['sellerTeam' => $team],
            ['id' => 'DESC'],
            $limit,
            $offset
        );

        return $listings;
    }

    public function countListingsForSeller(Team $team): int
    {
        return $this->em->getRepository(MarketplaceListing::class)->count([
            'sellerTeam' => $team,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTransactionHistory(Team $team, ?int $page = null, ?int $limit = null): array
    {
        $qb = $this->em->getRepository(MarketplaceTransaction::class)->createQueryBuilder('t')
            ->where('t.buyerTeam = :team OR t.sellerTeam = :team')
            ->setParameter('team', $team)
            ->orderBy('t.id', 'DESC');

        if (null !== $page && null !== $limit) {
            $qb->setFirstResult(($page - 1) * $limit)
               ->setMaxResults($limit);
        }

        /** @var list<MarketplaceTransaction> $transactions */
        $transactions = $qb->getQuery()->getResult();

        $data = [];
        foreach ($transactions as $tx) {
            $listing = $tx->getListing();

            $data[] = [
                'id' => $tx->getId(),
                'buyer_name' => $tx->getBuyerTeam()->getName(),
                'seller_name' => $tx->getSellerTeam()->getName(),
                'amount' => $tx->getAmount(),
                'fee_amount' => $tx->getFeeAmount(),
                'type' => $tx->getType()->value,
                'created_at' => $tx->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'listing_id' => $listing->getId(),
                'listing_type' => $listing->getListingType()->value,
                'entity_name' => null !== $listing->getHero() ? $listing->getHero()->getName() :
                    (ListingType::Item === $listing->getListingType() && null !== $listing->getItem() ? $listing->getItem()->getName() : 'Unknown'),
            ];
        }

        return $data;
    }

    public function countTransactionHistory(Team $team): int
    {
        $qb = $this->em->getRepository(MarketplaceTransaction::class)->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.buyerTeam = :team OR t.sellerTeam = :team')
            ->setParameter('team', $team);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return array<string, mixed> */
    public function serializeListing(MarketplaceListing $listing): array
    {
        $highestBid = null;
        foreach ($listing->getBids() as $bid) {
            if (null === $highestBid || $bid->getBidAmount() > $highestBid->getBidAmount()) {
                $highestBid = $bid;
            }
        }

        $entityData = null;
        if (ListingType::Hero === $listing->getListingType() && null !== $listing->getHero()) {
            $hero = $listing->getHero();
            $rating = $this->heroRatingCalculator->calculate($hero);
            $entityData = [
                'id' => $hero->getId(),
                'name' => $hero->getName(),
                'level' => $hero->getLevel(),
                'age' => $hero->getAge(),
                'race' => $hero->getRace()->value,
                'str' => $hero->getStr(),
                'dex' => $hero->getDex(),
                'kon' => $hero->getKon(),
                'spd' => $hero->getSpd(),
                'intel' => $hero->getIntel(),
                'wil' => $hero->getWil(),
                'cha' => $hero->getCha(),
                'lck' => $hero->getLck(),
                'form' => $hero->getForm(),
                'fatigue' => $hero->getFatigue(),
                'morale' => $hero->getMorale(),
                'trait' => $hero->getTrait()?->value,
                'ratings' => [
                    'base_ovr' => $rating->getBaseOvr(),
                    'complex_rating' => $rating->getComplexRating(),
                ],
            ];
        } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
            $item = $listing->getItem();
            $entityData = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'rarity' => $item->getRarity()->value,
                'slot_type' => $item->getSlotType()->value,
                'category' => $item->getCategory()->value,
                'durability' => $item->getDurability(),
                'bonuses' => $item->getBonuses(),
            ];
        } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
            $trainer = $listing->getHero();
            $rating = $this->heroRatingCalculator->calculate($trainer);
            $entityData = [
                'id' => $trainer->getId(),
                'name' => $trainer->getName(),
                'race' => $trainer->getRace()->value,
                'age' => $trainer->getAge(),
                'str' => $trainer->getStr(),
                'dex' => $trainer->getDex(),
                'kon' => $trainer->getKon(),
                'spd' => $trainer->getSpd(),
                'intel' => $trainer->getIntel(),
                'wil' => $trainer->getWil(),
                'cha' => $trainer->getCha(),
                'lck' => $trainer->getLck(),
                'ratings' => [
                    'base_ovr' => $rating->getBaseOvr(),
                    'complex_rating' => $rating->getComplexRating(),
                ],
            ];
        }

        return [
            'id' => $listing->getId(),
            'listing_type' => $listing->getListingType()->value,
            'listing_mode' => $listing->getListingMode()->value,
            'price_gold' => $listing->getPriceGold(),
            'buyout_price_gold' => $listing->getBuyoutPriceGold(),
            'expires_at' => $listing->getExpiresAt()->format(\DateTimeInterface::ATOM),
            'status' => $listing->getStatus()->value,
            'seller_team' => [
                'id' => $listing->getSellerTeam()->getId(),
                'name' => $listing->getSellerTeam()->getName(),
                'reputation' => $listing->getSellerTeam()->getReputation(),
            ],
            'highest_bid' => $highestBid ? [
                'amount' => $highestBid->getBidAmount(),
                'bidder_name' => $highestBid->getBidderTeam()->getName(),
            ] : null,
            'entity' => $entityData,
        ];
    }

    /**
     * @return list<MarketplaceListing>
     */
    private function findExpiredActiveListings(?Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): array
    {
        $qb = $this->em->getRepository(MarketplaceListing::class)->createQueryBuilder('l')
            ->where('l.status = :status')
            ->andWhere('l.expiresAt <= :now')
            ->setParameter('status', ListingStatus::Active)
            ->setParameter('now', $now);

        if (null !== $team) {
            $qb->andWhere('l.sellerTeam = :team')
                ->setParameter('team', $team);
        } elseif (null !== $kingdom) {
            $qb->andWhere('l.kingdom = :kingdom')
                ->setParameter('kingdom', $kingdom);
        }

        return $qb->getQuery()->getResult();
    }
}
