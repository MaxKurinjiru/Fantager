<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Formation\Formation;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Marketplace\MarketplaceListing;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\ItemRarity;
use App\Enum\ItemStatus;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Enum\Race;
use App\Service\Config\RaceConfig;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Hero\HeroDismissalService;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Item\ItemService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Summoning\SummoningService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class NpcEconomySimulator
{
    use NpcSimulationHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SummoningService $summoningService,
        private readonly MarketplaceService $marketplaceService,
        private readonly HeadquartersService $hqService,
        private readonly HeroDismissalService $dismissalService,
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly RaceConfig $raceConfig,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly NpcTacticsSimulator $tacticsSimulator,
        private readonly ItemService $itemService,
    ) {
    }

    /**
     * Run daily management and economy simulation: proactive dismissal, roster recycling, and summoning.
     */
    public function simulateDailyManagementAndEconomy(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            if (!$team->isNpc()) {
                return;
            }
            $teams = [$team];
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy([
                'kingdom' => $kingdom,
                'isNpc' => true,
            ]);
        }

        foreach ($teams as $team) {
            // Fetch alive heroes
            $aliveHeroes = $this->em->getRepository(Hero::class)->createQueryBuilder('h')
                ->where('h.team = :team')
                ->andWhere('h.status NOT IN (:deadStatuses)')
                ->setParameter('team', $team)
                ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
                ->getQuery()
                ->getResult();

            $heroCount = \count($aliveHeroes);
            $combatantCount = 0;
            foreach ($aliveHeroes as $h) {
                if ($h->isCombatant()) {
                    ++$combatantCount;
                }
            }
            $rosterLimit = $this->hqService->getRosterLimit($team);

            // Calculate selling count
            $sellingCount = 0;
            foreach ($aliveHeroes as $h) {
                if (HeroStatus::Selling === $h->getStatus()) {
                    ++$sellingCount;
                }
            }

            // 0a. Trait-aware dismissal / trainer conversion:
            // Heroes with a purely negative trait degrade team performance.
            // If they have a high raw stat (>= 150), they are converted to a trainer first.
            // Otherwise, they are dismissed immediately.
            foreach ($aliveHeroes as $hero) {
                $heroId = $hero->getId();
                if (null === $heroId) {
                    continue;
                }
                if (HeroStatus::Available !== $hero->getStatus()) {
                    continue;
                }
                if ($hero->isTrainer()) {
                    continue; // Never dismiss active trainers
                }
                if (!$this->isPurelyNegativeTrait($hero->getTrait())) {
                    continue; // Only negative-trait heroes
                }

                // Keep if in active formation (we need the body count)
                $activeFormationsTrait = $this->em->getRepository(Formation::class)->findBy(['team' => $team]);
                $activeHeroIdsTrait = [];
                foreach ($activeFormationsTrait as $form) {
                    foreach ($form->getSlots() as $slot) {
                        $heroInSlot = $slot->getHero();
                        if (null !== $heroInSlot && null !== $heroInSlot->getId()) {
                            $activeHeroIdsTrait[$heroInSlot->getId()] = true;
                        }
                    }
                }
                if (isset($activeHeroIdsTrait[$heroId])) {
                    continue;
                }

                // Check if they have a high enough stat to qualify as a trainer candidate.
                // Instead of a fixed value, we compute the threshold dynamically as 75% of the team's highest combatant stat (min 5.0).
                $teamMaxStat = 0;
                foreach ($aliveHeroes as $h) {
                    if ($h->isCombatant()) {
                        $hMax = max(
                            $h->getStrRaw(),
                            $h->getDexRaw(),
                            $h->getKonRaw(),
                            $h->getSpdRaw(),
                            $h->getIntelRaw(),
                            $h->getWilRaw(),
                            $h->getChaRaw(),
                            $h->getLckRaw()
                        );
                        if ($hMax > $teamMaxStat) {
                            $teamMaxStat = $hMax;
                        }
                    }
                }
                $threshold = max(50, (int) round($teamMaxStat * 0.75));

                $maxStat = max(
                    $hero->getStrRaw(),
                    $hero->getDexRaw(),
                    $hero->getKonRaw(),
                    $hero->getSpdRaw(),
                    $hero->getIntelRaw(),
                    $hero->getWilRaw(),
                    $hero->getChaRaw(),
                    $hero->getLckRaw()
                );

                if ($maxStat >= $threshold) {
                    // Check if team is already at or above their trainer limit
                    $trainersCount = 0;
                    foreach ($aliveHeroes as $h) {
                        if ($h->isTrainer()) {
                            ++$trainersCount;
                        }
                    }

                    $hq = $this->hqService->getForTeam($team);
                    $trainingLevel = 1;
                    foreach ($hq->getFacilities() as $facility) {
                        if (FacilityType::Training === $facility->getType()) {
                            $trainingLevel = $facility->getLevel();
                            break;
                        }
                    }
                    $trainerLimit = 2 + (int) floor(($trainingLevel - 1) / 2);

                    if ($trainersCount < $trainerLimit) {
                        try {
                            $hero->setRole(HeroRole::Trainer);
                            $hero->setTrainingType(null);
                            $hero->setTargetAttribute(null);

                            // Unequip all items
                            $equippedItems = $this->em->getRepository(Item::class)->findBy(['equippedHero' => $hero]);
                            foreach ($equippedItems as $item) {
                                $item->setEquippedHero(null);
                                $item->setEquippedSlot(null);
                            }

                            // Remove hero from active formations
                            $slots = $this->em->getRepository(\App\Entity\Formation\FormationSlot::class)->findBy(['hero' => $hero]);
                            foreach ($slots as $slot) {
                                $slot->setHero(null);
                            }

                            --$combatantCount;
                            // Add to list of trainers for this tick so count is accurate
                            $aliveHeroes = array_map(function (Hero $h) use ($heroId) {
                                if ($h->getId() === $heroId) {
                                    $h->setRole(HeroRole::Trainer);
                                }

                                return $h;
                            }, $aliveHeroes);
                            continue;
                        } catch (\Throwable) {
                            // Ignore and proceed
                        }
                    }
                }

                // If they don't qualify or we are at the trainer limit: dismiss
                try {
                    $this->dismissalService->dismiss($team, $hero);
                    $aliveHeroes = array_filter($aliveHeroes, static fn (Hero $h) => $h->getId() !== $heroId);
                    $heroCount = \count($aliveHeroes);
                    --$combatantCount;
                    break; // Dismiss at most one negative-trait hero per tick
                } catch (\Throwable) {
                    // Dismissal failed, continue
                }
            }

            // 0b. Recycle Roster: If roster is full and we have at least 1 hero listed for sale,
            // dismiss the worst available combatant to make room for a new summon.
            if ($combatantCount >= $rosterLimit && $sellingCount >= 1) {
                $summonStatus = $this->summoningService->getStatus($team);
                if ($team->getGold() >= ($summonStatus['gold_cost'] + 150)) {
                    // Find active formation hero IDs to avoid dismissing active combatants
                    $activeFormations = $this->em->getRepository(Formation::class)->findBy(['team' => $team]);
                    $activeHeroIds = [];
                    foreach ($activeFormations as $form) {
                        foreach ($form->getSlots() as $slot) {
                            $heroInSlot = $slot->getHero();
                            if (null !== $heroInSlot) {
                                $heroId = $heroInSlot->getId();
                                if (null !== $heroId) {
                                    $activeHeroIds[$heroId] = true;
                                }
                            }
                        }
                    }

                    // Find candidates for dismissal: status is Available, not a trainer, not in active formation
                    $dismissalCandidates = [];
                    foreach ($aliveHeroes as $hero) {
                        $heroId = $hero->getId();
                        if (null === $heroId) {
                            continue;
                        }
                        if (HeroStatus::Available !== $hero->getStatus()) {
                            continue;
                        }
                        if ($hero->isTrainer()) {
                            continue;
                        }
                        if (isset($activeHeroIds[$heroId])) {
                            continue;
                        }

                        $dismissalCandidates[] = $hero;
                    }

                    if (\count($dismissalCandidates) > 0) {
                        // Score candidates by sum of raw attributes (lowest is worst)
                        usort($dismissalCandidates, static function (Hero $a, Hero $b): int {
                            $scoreA = $a->getStrRaw() + $a->getDexRaw() + $a->getKonRaw() + $a->getSpdRaw()
                                + $a->getIntelRaw() + $a->getWilRaw() + $a->getChaRaw() + $a->getLckRaw();
                            $scoreB = $b->getStrRaw() + $b->getDexRaw() + $b->getKonRaw() + $b->getSpdRaw()
                                + $b->getIntelRaw() + $b->getWilRaw() + $b->getChaRaw() + $b->getLckRaw();

                            return $scoreA <=> $scoreB;
                        });

                        $worstHero = $dismissalCandidates[0];
                        try {
                            $this->dismissalService->dismiss($team, $worstHero);
                            // Refresh hero list and count
                            $aliveHeroes = array_filter($aliveHeroes, static fn (Hero $h) => $h->getId() !== $worstHero->getId());
                            $heroCount = \count($aliveHeroes);
                            --$combatantCount;
                        } catch (\Throwable) {
                            // Dismissal failed, ignore
                        }
                    }
                }
            }

            // 1. Summoning
            // NPC teams keep summoning to train and sell up to roster limit whenever they have space
            $summonThreshold = $rosterLimit - 1;

            if ($combatantCount <= $summonThreshold) {
                $summonStatus = $this->summoningService->getStatus($team);
                if ($summonStatus['available'] && $team->getGold() >= ($summonStatus['gold_cost'] + 150)) {
                    try {
                        $this->summoningService->summon($team);
                    } catch (\Throwable) {
                        // Summon failed, ignore
                    }
                }
            }
        }

        $this->em->flush();
    }

    /**
     * Run marketplace simulation: buying and selling items/heroes twice weekly.
     */
    public function simulateMarketplaceActions(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            if (!$team->isNpc()) {
                return;
            }
            $teams = [$team];
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy([
                'kingdom' => $kingdom,
                'isNpc' => true,
            ]);
        }

        foreach ($teams as $team) {
            $role = $this->getHelperEconomicRole($team);

            // Fetch alive heroes
            $aliveHeroes = $this->em->getRepository(Hero::class)->createQueryBuilder('h')
                ->where('h.team = :team')
                ->andWhere('h.status NOT IN (:deadStatuses)')
                ->setParameter('team', $team)
                ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
                ->getQuery()
                ->getResult();

            $heroCount = \count($aliveHeroes);

            // HQ reference for safety reserve
            $hq = $this->hqService->getForTeam($team);
            $weeklyMaintenance = $this->hqService->calculateWeeklyMaintenanceFee($hq);
            $safetyReserve = 2 * $weeklyMaintenance;

            // 3. Marketplace - Selling (Item and Hero/Trainer)
            $unequippedItems = $this->em->getRepository(Item::class)->findBy([
                'ownerTeam' => $team,
                'status' => ItemStatus::Available,
                'equippedHero' => null,
            ]);

            // Group unequipped items by slot type
            $itemsBySlot = [];
            foreach ($unequippedItems as $item) {
                $slotKey = $item->getSlotType()->value;
                $itemsBySlot[$slotKey][] = $item;
            }

            // Define the rarities sorting map
            $rarityMap = [
                ItemRarity::Common->value => 1,
                ItemRarity::Uncommon->value => 2,
                ItemRarity::Rare->value => 3,
                ItemRarity::Epic->value => 4,
                ItemRarity::Legendary->value => 5,
                ItemRarity::Mythic->value => 6,
            ];

            // Sort helper
            $sortItems = function (Item $a, Item $b) use ($rarityMap) {
                $aRarity = $rarityMap[$a->getRarity()->value];
                $bRarity = $rarityMap[$b->getRarity()->value];
                if ($aRarity !== $bRarity) {
                    return $aRarity <=> $bRarity;
                }

                return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
            };

            $sellableCandidates = [];
            foreach ($itemsBySlot as $slotKey => $slotItems) {
                // Sort within the slot group
                usort($slotItems, $sortItems);

                // Roll a random safety reserve of 1 to 2 items per slot
                $reserveLimit = random_int(1, 2);

                if (\count($slotItems) > $reserveLimit) {
                    // Sell lower-rarity items first (keep best in reserve at the end of the sorted array)
                    $excessCount = \count($slotItems) - $reserveLimit;
                    $excessItems = array_slice($slotItems, 0, $excessCount);
                    foreach ($excessItems as $item) {
                        $sellableCandidates[] = $item;
                    }
                }
            }

            // Sort all sellable candidates combined to ensure we list lowest-value items first
            usort($sellableCandidates, $sortItems);

            // Roll how many items we should sell this tick (0 to 3)
            $targetSellCount = random_int(0, 3);
            $itemsSold = 0;

            foreach ($sellableCandidates as $itemToList) {
                if ($itemsSold >= $targetSellCount) {
                    break;
                }
                $itemId = $itemToList->getId();
                if (null !== $itemId) {
                    $price = $this->calculateItemMarketPrice($itemToList);
                    try {
                        $this->marketplaceService->createListing(
                            $team,
                            ListingType::Item->value,
                            $itemId,
                            $price,
                            $price,
                            ListingMode::BuyNow->value,
                            7,
                            $now
                        );
                        ++$itemsSold;
                    } catch (\Throwable) {
                        // Ignore
                    }
                }
            }

            // Fetch default formation to determine active lineup and equipment needs
            $formation = $this->em->getRepository(Formation::class)->findOneBy([
                'team' => $team,
                'isDefault' => true,
                'isTemporary' => false,
            ]);

            $activeLineup = [];
            $lineupGear = [];

            if (null !== $formation) {
                foreach ($formation->getSlots() as $slot) {
                    $hero = $slot->getHero();
                    if (null !== $hero) {
                        $activeLineup[] = $hero;
                        $heroId = $hero->getId();
                        if (null !== $heroId) {
                            $lineupGear[$heroId] = [];
                            $equippedItems = $this->em->getRepository(Item::class)->findBy(['equippedHero' => $hero]);
                            foreach ($equippedItems as $equipped) {
                                if (null !== $equipped->getEquippedSlot()) {
                                    $lineupGear[$heroId][$equipped->getEquippedSlot()->value] = $this->getRarityValue($equipped->getRarity());
                                }
                            }
                        }
                    }
                }
            }

            // Selling surplus/trained heroes and redundant trainers (processed separately)
            if ($heroCount >= 10) {
                // Find candidates who are available and not in active formations
                $activeFormations = $this->em->getRepository(Formation::class)->findBy(['team' => $team]);
                $activeHeroIds = [];
                foreach ($activeFormations as $form) {
                    foreach ($form->getSlots() as $slot) {
                        $heroInSlot = $slot->getHero();
                        if (null !== $heroInSlot) {
                            $heroId = $heroInSlot->getId();
                            if (null !== $heroId) {
                                $activeHeroIds[$heroId] = true;
                            }
                        }
                    }
                }

                $combatantCandidates = [];
                $trainerCandidates = [];
                foreach ($aliveHeroes as $hero) {
                    $heroId = $hero->getId();
                    if (null === $heroId) {
                        continue;
                    }
                    if (HeroStatus::Available !== $hero->getStatus()) {
                        continue;
                    }
                    if (isset($activeHeroIds[$heroId])) {
                        continue;
                    }
                    if ($hero->getLevel() < 1) {
                        continue;
                    }

                    if ($hero->isTrainer()) {
                        $trainerCandidates[] = $hero;
                    } else {
                        // Exclude purely negative-trait heroes from being sold as combatants on the marketplace
                        if ($this->isPurelyNegativeTrait($hero->getTrait())) {
                            continue;
                        }
                        $combatantCandidates[] = $hero;
                    }
                }

                $theme = $this->summoningService->getArenaRaceTheme($team);

                // Sort combatants: prioritize those with negative traits and lower compatibility
                usort($combatantCandidates, function (Hero $a, Hero $b) use ($theme): int {
                    $hasNegA = 'negative' === $a->getTrait()?->getCategory();
                    $hasNegB = 'negative' === $b->getTrait()?->getCategory();

                    $relA = $theme ? $this->raceConfig->getRelationship($a->getRace(), $theme) : 100;
                    $relB = $theme ? $this->raceConfig->getRelationship($b->getRace(), $theme) : 100;

                    $priorityA = ($hasNegA ? 100 : 0) + (100 - $relA);
                    $priorityB = ($hasNegB ? 100 : 0) + (100 - $relB);

                    return $priorityB <=> $priorityA;
                });

                // Sort trainers: prioritize those with negative traits
                usort($trainerCandidates, static function (Hero $a, Hero $b): int {
                    $hasNegA = 'negative' === $a->getTrait()?->getCategory();
                    $hasNegB = 'negative' === $b->getTrait()?->getCategory();

                    return ($hasNegB ? 1 : 0) <=> ($hasNegA ? 1 : 0);
                });

                // 1. Sell combatants up to limit
                $heroSellLimit = $this->getHeroSellLimitForRole($role);
                $heroesSold = 0;
                foreach ($combatantCandidates as $sellingHero) {
                    if ($heroesSold >= $heroSellLimit) {
                        break;
                    }
                    $sellingHeroId = $sellingHero->getId();
                    if (null !== $sellingHeroId) {
                        $price = $this->calculateHeroMarketPrice($sellingHero);
                        try {
                            $this->marketplaceService->createListing(
                                $team,
                                ListingType::Hero->value,
                                $sellingHeroId,
                                $price,
                                $price,
                                ListingMode::BuyNow->value,
                                7,
                                $now
                            );
                            ++$heroesSold;
                        } catch (\Throwable) {
                            // Ignore
                        }
                    }
                }

                // 2. Sell trainers up to limit
                $trainerSellLimit = $this->getTrainerSellLimitForRole($role);
                $trainersSold = 0;
                foreach ($trainerCandidates as $sellingTrainer) {
                    if ($trainersSold >= $trainerSellLimit) {
                        break;
                    }
                    $sellingTrainerId = $sellingTrainer->getId();
                    if (null !== $sellingTrainerId) {
                        $price = $this->calculateHeroMarketPrice($sellingTrainer);
                        try {
                            $this->marketplaceService->createListing(
                                $team,
                                ListingType::Trainer->value,
                                $sellingTrainerId,
                                $price,
                                $price,
                                ListingMode::BuyNow->value,
                                7,
                                $now
                            );
                            ++$trainersSold;
                        } catch (\Throwable) {
                            // Ignore
                        }
                    }
                }
            }

            // 4. Marketplace - Buying
            // Scan and buy player listings in the same Kingdom if budget allows, based on priority score
            $listings = $this->em->getRepository(MarketplaceListing::class)->findBy([
                'kingdom' => $kingdom,
                'status' => ListingStatus::Active,
            ], ['id' => 'ASC'], 50);

            $itemCandidates = [];
            $heroCandidates = [];
            $trainerCandidates = [];

            foreach ($listings as $listing) {
                if ($listing->getSellerTeam()->getId() === $team->getId()) {
                    continue;
                }

                $price = $listing->getBuyoutPriceGold() ?? $listing->getPriceGold();
                $score = 0; // 0 means not interested

                if (ListingType::Item === $listing->getListingType()) {
                    $item = $listing->getItem();
                    if (null !== $item) {
                        if (NpcSimulationService::ROLE_ROYAL_COLLECTOR === $role) {
                            if (\in_array($item->getRarity(), [ItemRarity::Epic, ItemRarity::Legendary, ItemRarity::Mythic], true)) {
                                $score = 100;
                            } else {
                                $score = 10;
                            }
                        } elseif (NpcSimulationService::ROLE_SCAVENGER_CLAN === $role) {
                            if ($price < 150) {
                                $score = 100;
                            } else {
                                $score = 20;
                            }
                        } else {
                            if (\in_array($item->getRarity(), [ItemRarity::Epic, ItemRarity::Rare], true) && $price < 300) {
                                $score = 30;
                            }
                        }
                    }
                    if ($score > 0) {
                        $itemCandidates[] = ['listing' => $listing, 'score' => $score, 'price' => $price];
                    }
                } elseif (ListingType::Hero === $listing->getListingType()) {
                    $hero = $listing->getHero();
                    if (null !== $hero && $hero->getLevel() >= 1) {
                        $theme = $this->summoningService->getArenaRaceTheme($team);
                        if (Race::Genie === $theme) {
                            $compatibleRaces = [Race::Genie];
                        } else {
                            $compatibleRaces = $this->summoningService->getCompatibleRacesForTeam($team);
                        }

                        if (\in_array($hero->getRace(), $compatibleRaces, true)) {
                            if (NpcSimulationService::ROLE_MERCENARY_ACADEMY === $role) {
                                if (\in_array($hero->getRace(), [Race::Orc, Race::Dwarf, Race::Human], true)) {
                                    $score = 100;
                                } else {
                                    $score = 60;
                                }
                            } elseif (NpcSimulationService::ROLE_ROYAL_COLLECTOR === $role) {
                                $score = 80;
                            } else {
                                $score = 20;
                            }
                        }
                    }
                    if ($score > 0) {
                        $heroCandidates[] = ['listing' => $listing, 'score' => $score, 'price' => $price];
                    }
                } elseif (ListingType::Trainer === $listing->getListingType()) {
                    $hero = $listing->getHero();
                    if (null !== $hero && $hero->getLevel() >= 1) {
                        if (NpcSimulationService::ROLE_VETERAN_GUILD === $role) {
                            $score = 100;
                        } elseif (NpcSimulationService::ROLE_ROYAL_COLLECTOR === $role) {
                            $score = 80;
                        } else {
                            $score = 20;
                        }
                    }
                    if ($score > 0) {
                        $trainerCandidates[] = ['listing' => $listing, 'score' => $score, 'price' => $price];
                    }
                }
            }

            // Sort helper
            $sortCandidates = function (array $a, array $b): int {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }

                return $a['price'] <=> $b['price'];
            };

            usort($itemCandidates, $sortCandidates);
            usort($heroCandidates, $sortCandidates);
            usort($trainerCandidates, $sortCandidates);

            // 1. Buy items that are useful upgrades for active lineup
            foreach ($itemCandidates as $cand) {
                if ($team->getGold() < ($safetyReserve + $cand['price'])) {
                    continue;
                }
                $item = $cand['listing']->getItem();
                if (null !== $item) {
                    $targetHero = $this->tacticsSimulator->isItemUsefulForLineup($activeLineup, $item, $lineupGear);
                    if (null !== $targetHero) {
                        $listingId = $cand['listing']->getId();
                        if (null !== $listingId) {
                            try {
                                $this->marketplaceService->buyListing($team, $listingId, $now);
                                // Update in-memory gear tracking
                                $heroId = $targetHero->getId();
                                if (null !== $heroId) {
                                    $lineupGear[$heroId][$item->getSlotType()->value] = $this->getRarityValue($item->getRarity());
                                }
                            } catch (\Throwable) {
                            }
                        }
                    }
                }
            }

            // 2. Buy heroes up to limit
            $heroBuyLimit = $this->getHeroBuyLimitForRole($role);
            $heroesBought = 0;
            foreach ($heroCandidates as $cand) {
                if ($heroesBought >= $heroBuyLimit) {
                    break;
                }
                if ($team->getGold() >= ($safetyReserve + $cand['price'])) {
                    $listingId = $cand['listing']->getId();
                    if (null !== $listingId) {
                        try {
                            $this->marketplaceService->buyListing($team, $listingId, $now);
                            ++$heroesBought;
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            // 3. Buy trainers up to limit
            $trainerBuyLimit = $this->getTrainerBuyLimitForRole($role);
            $trainersBought = 0;
            foreach ($trainerCandidates as $cand) {
                if ($trainersBought >= $trainerBuyLimit) {
                    break;
                }
                if ($team->getGold() >= ($safetyReserve + $cand['price'])) {
                    $listingId = $cand['listing']->getId();
                    if (null !== $listingId) {
                        try {
                            $this->marketplaceService->buyListing($team, $listingId, $now);
                            ++$trainersBought;
                        } catch (\Throwable) {
                        }
                    }
                }
            }
        }

        $this->em->flush();
    }

    /**
     * Run weekly management and economy simulation: HQ upgrades.
     */
    public function simulateWeeklyManagementAndEconomy(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            if (!$team->isNpc()) {
                return;
            }
            $teams = [$team];
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy([
                'kingdom' => $kingdom,
                'isNpc' => true,
            ]);
        }

        foreach ($teams as $team) {
            $role = $this->getHelperEconomicRole($team);

            // 1. Arena Theme Optimization
            $this->simulateArenaOptimizationForTeam($team, $role);

            // 2. HQ Upgrades
            $hq = $this->hqService->getForTeam($team);
            $weeklyMaintenance = $this->hqService->calculateWeeklyMaintenanceFee($hq);
            $safetyReserve = 2 * $weeklyMaintenance;

            if (null === $hq->getUpgradingFacility()) {
                $priorities = $this->getFacilityPrioritiesForRole($role);
                $upgradeCandidates = [];
                foreach ($hq->getFacilities() as $facility) {
                    $facilityType = $facility->getType();
                    $priorityIndex = array_search($facilityType, $priorities, true);
                    if (false === $priorityIndex) {
                        $priorityIndex = 3;
                    }

                    $upgradeCandidates[] = [
                        'facility' => $facility,
                        'type' => $facilityType,
                        'level' => $facility->getLevel(),
                        'priorityIndex' => $priorityIndex,
                    ];
                }

                // Sort candidates: first by priorityIndex ascending (higher priority first), then by level ascending (balance equal priorities)
                usort($upgradeCandidates, static function (array $a, array $b): int {
                    if ($a['priorityIndex'] !== $b['priorityIndex']) {
                        return $a['priorityIndex'] <=> $b['priorityIndex'];
                    }

                    return $a['level'] <=> $b['level'];
                });

                foreach ($upgradeCandidates as $candidate) {
                    $facility = $candidate['facility'];
                    $facilityType = $candidate['type'];
                    $cost = $this->hqService->calculateUpgradeCost($facilityType, $facility->getLevel(), $hq->getComputedTotalLevel());
                    if ($team->getGold() >= ($safetyReserve + $cost)) {
                        // Compute level difference constraint among ALL facilities:
                        // Level difference between highest and lowest facilities after this upgrade must be <= 3
                        $tempLevels = [];
                        foreach ($upgradeCandidates as $uc) {
                            $tempLevels[] = ($uc['type'] === $facilityType) ? $uc['level'] + 1 : $uc['level'];
                        }
                        $maxLevel = max($tempLevels);
                        $minLevel = min($tempLevels);

                        if (($maxLevel - $minLevel) <= 3) {
                            try {
                                $this->hqService->upgradeFacility($team, $facilityType, $now);
                                break; // Upgrade at most one facility per week
                            } catch (\Throwable) {
                                // Try next candidate
                            }
                        }
                    }
                }
            }
        }

        $this->em->flush();
    }

    private function calculateItemMarketPrice(Item $item): int
    {
        $merchantPrice = $this->itemService->getBasicItemMerchantPrice($item);
        if (null !== $merchantPrice) {
            return (int) round($merchantPrice * 0.7);
        }

        return match ($item->getRarity()) {
            ItemRarity::Common => 75,
            ItemRarity::Uncommon => 180,
            ItemRarity::Rare => 400,
            ItemRarity::Epic => 1000,
            ItemRarity::Legendary => 2500,
            ItemRarity::Mythic => 6000,
        };
    }

    private function calculateHeroMarketPrice(Hero $hero): int
    {
        return max(1, $this->heroRatingCalculator->estimateMarketPrice($hero));
    }

    private function getHeroBuyLimitForRole(string $role): int
    {
        return match ($role) {
            NpcSimulationService::ROLE_MERCENARY_ACADEMY => random_int(1, 3),
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => random_int(1, 2),
            NpcSimulationService::ROLE_SCAVENGER_CLAN => random_int(1, 2),
            default => random_int(1, 1), // veteran_guild
        };
    }

    private function getHeroSellLimitForRole(string $role): int
    {
        return match ($role) {
            NpcSimulationService::ROLE_MERCENARY_ACADEMY => random_int(1, 3),
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => random_int(1, 2),
            NpcSimulationService::ROLE_SCAVENGER_CLAN => random_int(1, 2),
            default => random_int(1, 1),
        };
    }

    private function getTrainerBuyLimitForRole(string $role): int
    {
        return match ($role) {
            NpcSimulationService::ROLE_VETERAN_GUILD => random_int(1, 3),
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => random_int(1, 2),
            default => random_int(1, 1), // mercenary_academy, scavenger_clan
        };
    }

    private function getTrainerSellLimitForRole(string $role): int
    {
        return match ($role) {
            NpcSimulationService::ROLE_VETERAN_GUILD => random_int(1, 3),
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => random_int(1, 2),
            default => random_int(1, 1),
        };
    }

    private function getRarityValue(ItemRarity $rarity): int
    {
        return match ($rarity) {
            ItemRarity::Common => 1,
            ItemRarity::Uncommon => 2,
            ItemRarity::Rare => 3,
            ItemRarity::Epic => 4,
            ItemRarity::Legendary => 5,
            ItemRarity::Mythic => 6,
        };
    }

    /**
     * @param array<Hero> $aliveHeroes
     */
    private function determineOptimalRace(Team $team, string $role, array $aliveHeroes): Race
    {
        $raceCounts = [];
        foreach ($aliveHeroes as $hero) {
            $raceVal = $hero->getRace()->value;
            $raceCounts[$raceVal] = ($raceCounts[$raceVal] ?? 0) + 1;
        }

        if (NpcSimulationService::ROLE_MERCENARY_ACADEMY === $role) {
            $academyRaces = [Race::Orc->value, Race::Dwarf->value, Race::Human->value];
            $bestRaceVal = null;
            $maxCount = -1;
            foreach ($academyRaces as $r) {
                $count = $raceCounts[$r] ?? 0;
                if ($count > $maxCount) {
                    $maxCount = $count;
                    $bestRaceVal = $r;
                }
            }

            return Race::from($bestRaceVal);
        }

        if (\count($raceCounts) > 0) {
            arsort($raceCounts);
            $bestRaceVal = array_key_first($raceCounts);

            return Race::from($bestRaceVal);
        }

        $hq = $this->hqService->getForTeam($team);
        $currentOpt = $hq->getRaceOptimization();
        if (null !== $currentOpt) {
            $currentRace = Race::tryFrom($currentOpt);
            if (null !== $currentRace) {
                return $currentRace;
            }
        }

        return Race::Human;
    }

    private function simulateArenaOptimizationForTeam(Team $team, string $role): void
    {
        $hq = $this->hqService->getForTeam($team);

        if ($hq->isRaceOptimizationLockCycle()) {
            return;
        }

        $aliveHeroes = $this->em->getRepository(Hero::class)->createQueryBuilder('h')
            ->where('h.team = :team')
            ->andWhere('h.status NOT IN (:deadStatuses)')
            ->setParameter('team', $team)
            ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
            ->getQuery()
            ->getResult();

        $optimalRace = $this->determineOptimalRace($team, $role, $aliveHeroes);
        $currentOpt = $hq->getRaceOptimization();

        if ($currentOpt !== $optimalRace->value) {
            $hq->setRaceOptimization($optimalRace->value);
            $hq->setRaceOptimizationLockCycle(true);
            $this->teamChronicleService->recordRaceOptimizationChanged($team, $optimalRace->value);
        }
    }

    /**
     * @return array<FacilityType>
     */
    private function getFacilityPrioritiesForRole(string $role): array
    {
        return match ($role) {
            NpcSimulationService::ROLE_MERCENARY_ACADEMY => [
                FacilityType::Training,
                FacilityType::SummoningChamber,
                FacilityType::Barracks,
            ],
            NpcSimulationService::ROLE_VETERAN_GUILD => [
                FacilityType::Training,
                FacilityType::Treasury,
                FacilityType::Medical,
            ],
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => [
                FacilityType::Arena,
                FacilityType::Library,
                FacilityType::SummoningChamber,
            ],
            NpcSimulationService::ROLE_SCAVENGER_CLAN => [
                FacilityType::Treasury,
                FacilityType::Medical,
                FacilityType::Training,
            ],
            default => [
                FacilityType::Arena,
                FacilityType::Training,
                FacilityType::Treasury,
            ],
        };
    }
}
