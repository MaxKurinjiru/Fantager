<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Marketplace\MarketplaceListing;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Enum\ItemSubType;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Enum\Race;
use App\Enum\TrainingType;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Hero\HeroDismissalService;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Item\ItemService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Summoning\SummoningService;
use App\Service\Training\TrainingService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Handles autonomous behaviors for NPC teams including tactics, training, and economy.
 */
class NpcSimulationService
{
    public const ROLE_MERCENARY_ACADEMY = 'mercenary_academy'; // Produces combat heroes
    public const ROLE_VETERAN_GUILD = 'veteran_guild';         // Produces trainers
    public const ROLE_ROYAL_COLLECTOR = 'royal_collector';     // Cash buyer (gold faucet)
    public const ROLE_SCAVENGER_CLAN = 'scavenger_clan';       // Low-end liquidity / dismantler

    private const ROLES = [
        self::ROLE_MERCENARY_ACADEMY,
        self::ROLE_VETERAN_GUILD,
        self::ROLE_ROYAL_COLLECTOR,
        self::ROLE_SCAVENGER_CLAN,
    ];

    private const SUBTYPE_TO_BASIC_ITEM = [
        'one_handed_sword' => 'short_sword',
        'two_handed_sword' => 'greatsword',
        'one_handed_axe' => 'hand_axe',
        'two_handed_axe' => 'battle_axe',
        'one_handed_mace' => 'flanged_mace',
        'two_handed_mace' => 'warhammer',
        'dagger' => 'iron_dagger',
        'bow' => 'short_bow',
        'crossbow' => 'light_crossbow',
        'wand' => 'wooden_wand',
        'staff' => 'wooden_staff',
        'shield' => 'wooden_shield',
        'spell_accelerator' => 'apprentice_wand',
    ];

    private const ARMOR_TO_BASIC_ITEM = [
        'light_armor' => [
            'head' => 'leather_helmet',
            'body' => 'leather_jerkin',
            'hands' => 'leather_gloves',
            'feet' => 'leather_boots',
        ],
        'medium_armor' => [
            'head' => 'chain_coif',
            'body' => 'chain_hauberk',
            'hands' => 'chain_gloves',
            'feet' => 'chain_boots',
        ],
        'heavy_armor' => [
            'head' => 'iron_helmet',
            'body' => 'plate_armor',
            'hands' => 'iron_gauntlets',
            'feet' => 'iron_greaves',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SummoningService $summoningService,
        private readonly MarketplaceService $marketplaceService,
        private readonly HeadquartersService $hqService,
        private readonly TrainingService $trainingService,
        private readonly ItemService $itemService,
        private readonly HeroDismissalService $dismissalService,
        private readonly HeroRatingCalculator $heroRatingCalculator,
    ) {
    }

    /**
     * Determine the economic role/archetype of a team deterministically.
     */
    public function getEconomicRole(Team $team): string
    {
        $id = $team->getId() ?? 0;

        return self::ROLES[$id % count(self::ROLES)];
    }

    /**
     * Run tactical simulation: optimize formation slotting (3-3), rotate tired heroes, and auto-equip gear.
     */
    public function simulateTactics(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            $teams = [$team];
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy([
                'kingdom' => $kingdom,
                'isNpc' => true,
            ]);
        }

        foreach ($teams as $team) {
            // 1. Get or create default formation
            $formation = $this->em->getRepository(Formation::class)->findOneBy([
                'team' => $team,
                'isDefault' => true,
                'isTemporary' => false,
            ]);

            if (null === $formation) {
                $formation = new Formation();
                $formation->setTeam($team);
                $formation->setName('Default NPC Formation');
                $formation->setIsDefault(true);
                $formation->setApproach(FormationApproach::Balanced);
                $this->em->persist($formation);

                foreach (FormationPosition::cases() as $pos) {
                    $slot = new FormationSlot();
                    $slot->setFormation($formation);
                    $slot->setPosition($pos);
                    $slot->setStrategy([]);
                    $slot->setSpellPriorities([]);
                    $this->em->persist($slot);
                    $formation->addSlot($slot);
                }
                $this->em->flush();
            }

            // 2. Get available non-trainer heroes
            $heroes = $this->em->getRepository(Hero::class)->findBy([
                'team' => $team,
                'status' => HeroStatus::Available,
            ]);

            if (count($heroes) < 6) {
                continue;
            }

            $frontCandidates = [];
            $backCandidates = [];

            foreach ($heroes as $hero) {
                if ($hero->isTrainer()) {
                    continue;
                }
                $heroId = $hero->getId();
                if (null === $heroId) {
                    continue;
                }

                $fatigue = $hero->getFatigue();
                $form = $hero->getForm();

                $readinessMultiplier = 1.0;
                if ($fatigue > 50) {
                    $readinessMultiplier *= 0.5;
                }
                if ($form < 40) {
                    $readinessMultiplier *= 0.6;
                }

                $frontScore = ($hero->getKon() + $hero->getStr()) * $readinessMultiplier;
                $backScore = ($hero->getIntel() + $hero->getSpd() + $hero->getDex()) * $readinessMultiplier;

                // Trait-aware score adjustments
                $trait = $hero->getTrait();
                if (null !== $trait) {
                    // Traits boosting physical front-row performance
                    if (\in_array($trait, [HeroTrait::Berserker, HeroTrait::Overconfident, HeroTrait::BattleHardened], true)) {
                        $frontScore *= 1.20;
                    }
                    // Traits boosting back-row / ranged / magic performance
                    if (\in_array($trait, [HeroTrait::Glasscannon, HeroTrait::Reckless], true)) {
                        $backScore *= 1.20;
                        $frontScore *= 0.85; // Penalty for fragile back-row heroes in front
                    }
                    // Clutch: better front-row (thrives under pressure)
                    if (HeroTrait::Clutch === $trait) {
                        $frontScore *= 1.10;
                    }
                    // Fragile / GlassJaw: penalize front-row placement
                    if (\in_array($trait, [HeroTrait::Fragile, HeroTrait::GlassJaw], true)) {
                        $frontScore *= 0.80;
                    }
                    // Volatile: penalize — unstable heroes are suboptimal, prefer bench
                    if (HeroTrait::Volatile === $trait) {
                        $frontScore *= 0.90;
                        $backScore *= 0.90;
                    }
                }

                $frontCandidates[] = ['hero' => $hero, 'id' => $heroId, 'score' => $frontScore];
                $backCandidates[] = ['hero' => $hero, 'id' => $heroId, 'score' => $backScore];
            }

            usort($frontCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);
            usort($backCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);

            $selectedFront = [];
            $selectedBack = [];
            $usedHeroIds = [];

            foreach ($frontCandidates as $item) {
                if (count($selectedFront) >= 3) {
                    break;
                }
                $hero = $item['hero'];
                $heroId = $item['id'];
                $selectedFront[] = $hero;
                $usedHeroIds[$heroId] = true;
            }

            foreach ($backCandidates as $item) {
                if (count($selectedBack) >= 3) {
                    break;
                }
                $hero = $item['hero'];
                $heroId = $item['id'];
                if (!isset($usedHeroIds[$heroId])) {
                    $selectedBack[] = $hero;
                    $usedHeroIds[$heroId] = true;
                }
            }

            // Fallback if not enough unique heroes selected
            if (count($selectedFront) + count($selectedBack) < 6) {
                foreach ($heroes as $hero) {
                    if ($hero->isTrainer()) {
                        continue;
                    }
                    $heroId = $hero->getId();
                    if (null === $heroId) {
                        continue;
                    }
                    if (count($selectedFront) + count($selectedBack) >= 6) {
                        break;
                    }
                    if (!isset($usedHeroIds[$heroId])) {
                        if (count($selectedFront) < 3) {
                            $selectedFront[] = $hero;
                        } else {
                            $selectedBack[] = $hero;
                        }
                        $usedHeroIds[$heroId] = true;
                    }
                }
            }

            // Absolute fallback (including trainers if needed to avoid forfeit)
            if (count($selectedFront) + count($selectedBack) < 6) {
                foreach ($heroes as $hero) {
                    $heroId = $hero->getId();
                    if (null === $heroId) {
                        continue;
                    }
                    if (count($selectedFront) + count($selectedBack) >= 6) {
                        break;
                    }
                    if (!isset($usedHeroIds[$heroId])) {
                        if (count($selectedFront) < 3) {
                            $selectedFront[] = $hero;
                        } else {
                            $selectedBack[] = $hero;
                        }
                        $usedHeroIds[$heroId] = true;
                    }
                }
            }

            // 3. Slot heroes & set strategies
            $frontIndex = 0;
            $backIndex = 0;
            $role = $this->getEconomicRole($team);

            foreach ($formation->getSlots() as $slot) {
                $position = $slot->getPosition();
                if (in_array($position, [FormationPosition::Front1, FormationPosition::Front2, FormationPosition::Front3], true)) {
                    $hero = $selectedFront[$frontIndex] ?? null;
                    $slot->setHero($hero);
                    ++$frontIndex;
                } else {
                    $hero = $selectedBack[$backIndex] ?? null;
                    $slot->setHero($hero);
                    ++$backIndex;
                }

                $slot->setStrategy($this->getStrategyForSlot($role, $position));
                $slot->setSpellPriorities($this->getSpellPrioritiesForSlot($role, $position));
            }

            $formation->setApproach($this->getApproachForRole($role));

            // 4. Auto-equip items to active lineup
            $activeLineup = array_merge($selectedFront, $selectedBack);
            $this->autoEquipItems($team, $activeLineup);
        }

        $this->em->flush();
    }

    /**
     * Run training simulation: assign trainers and trainees before the weekly training tick.
     */
    public function simulateTraining(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
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
            $teamHeroes = $this->em->getRepository(Hero::class)->findBy([
                'team' => $team,
            ]);

            if (count($teamHeroes) < 2) {
                continue;
            }

            $trainers = [];
            $trainees = [];
            foreach ($teamHeroes as $h) {
                if ($h->isTrainer()) {
                    $trainers[] = $h;
                } else {
                    $trainees[] = $h;
                }
            }

            // Find active formation hero IDs to avoid promoting combatants in active formations
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

            // Filter available, non-formation combatants for potential promotion
            $promotionCandidates = [];
            foreach ($trainees as $h) {
                $hId = $h->getId();
                if (null === $hId) {
                    continue;
                }
                if (HeroStatus::Available !== $h->getStatus()) {
                    continue;
                }
                if (isset($activeHeroIds[$hId])) {
                    continue;
                }
                $promotionCandidates[] = $h;
            }

            // Sort candidates by age and level descending
            usort($promotionCandidates, function (Hero $a, Hero $b): int {
                $ageCompare = $b->getAgeRaw() <=> $a->getAgeRaw();
                if (0 !== $ageCompare) {
                    return $ageCompare;
                }

                return $b->getLevel() <=> $a->getLevel();
            });

            // Exclude purely negative-trait heroes from trainer promotion:
            // They have no place as trainers either (Slacker/Volatile/Fragile/GlassJaw)
            // unless the team is desperate (no other candidates).
            $promotionCandidatesPositive = array_filter(
                $promotionCandidates,
                fn (Hero $h) => !$this->isPurelyNegativeTrait($h->getTrait())
            );
            if (count($promotionCandidatesPositive) >= 1) {
                $promotionCandidates = array_values($promotionCandidatesPositive);
            }
            // else: fall back to all candidates (better a Slacker trainer than no trainer)

            $trainerLimit = $this->trainingService->getTrainerLimit($team);
            $needed = $trainerLimit - count($trainers);
            for ($i = 0; $i < $needed && $i < count($promotionCandidates); ++$i) {
                $candidate = $promotionCandidates[$i];
                $candidate->setRole(HeroRole::Trainer);
                $trainers[] = $candidate;

                // Remove from trainees list
                $idx = array_search($candidate, $trainees, true);
                if (false !== $idx) {
                    unset($trainees[$idx]);
                }
            }
            $trainees = array_values($trainees);

            // Reset existing configurations and trainees lists
            foreach ($trainers as $trainer) {
                $trainer->setTrainingType(null);
                $trainer->setTargetAttribute(null);
                $trainer->getTrainees()->clear();
            }
            foreach ($trainees as $trainee) {
                $trainee->setTrainer(null);
            }

            // Sort trainers to ensure the best active trainers are configured/assigned
            usort($trainers, function (Hero $a, Hero $b): int {
                $ageCompare = $b->getAgeRaw() <=> $a->getAgeRaw();
                if (0 !== $ageCompare) {
                    return $ageCompare;
                }

                return $b->getLevel() <=> $a->getLevel();
            });

            $activeTrainers = array_slice($trainers, 0, $trainerLimit);

            $role = $this->getEconomicRole($team);
            $trainingConfigs = $this->getTrainingConfigsForRole($role);

            // Configure active trainers
            foreach ($activeTrainers as $idx => $trainer) {
                $config = $trainingConfigs[$idx % count($trainingConfigs)];
                $trainer->setTrainingType($config['type']);
                $trainer->setTargetAttribute($config['attribute']);
            }

            // Assign trainees to active trainers up to their slot limits
            // Priority: QuickLearner heroes first (gain more from training), then rest
            usort($trainees, function (Hero $a, Hero $b): int {
                $aIsQuick = HeroTrait::QuickLearner === $a->getTrait() ? 0 : 1;
                $bIsQuick = HeroTrait::QuickLearner === $b->getTrait() ? 0 : 1;

                return $aIsQuick <=> $bIsQuick;
            });

            $traineeIndex = 0;
            foreach ($activeTrainers as $trainer) {
                $slotsLimit = $this->trainingService->getTrainerSlotsLimit($trainer);
                for ($j = 0; $j < $slotsLimit; ++$j) {
                    if ($traineeIndex >= count($trainees)) {
                        break;
                    }
                    $trainee = $trainees[$traineeIndex++];
                    // Only assign available, non-selling trainees (Combatants)
                    if (HeroStatus::Available === $trainee->getStatus()) {
                        $trainee->setTrainer($trainer);
                        $trainer->addTrainee($trainee);
                    }
                }
            }
        }

        $this->em->flush();
    }

    /**
     * Run management and economy simulation: summoning, HQ upgrades, and marketplace buy/sell behavior.
     */
    public function simulateManagementAndEconomy(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            $teams = [$team];
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy([
                'kingdom' => $kingdom,
                'isNpc' => true,
            ]);
        }

        foreach ($teams as $team) {
            $role = $this->getEconomicRole($team);

            // Fetch alive heroes
            $aliveHeroes = $this->em->getRepository(Hero::class)->createQueryBuilder('h')
                ->where('h.team = :team')
                ->andWhere('h.status NOT IN (:deadStatuses)')
                ->setParameter('team', $team)
                ->setParameter('deadStatuses', [HeroStatus::Dead, HeroStatus::Retired])
                ->getQuery()
                ->getResult();

            $heroCount = count($aliveHeroes);
            $rosterLimit = $this->hqService->getRosterLimit($team);

            // Calculate selling count
            $sellingCount = 0;
            foreach ($aliveHeroes as $h) {
                if (HeroStatus::Selling === $h->getStatus()) {
                    ++$sellingCount;
                }
            }

            // 0a. Trait-aware dismissal:
            // Heroes with a purely negative trait are unlikely to sell on the marketplace
            // and degrade team performance. Dismiss them unless they qualify as trainer candidates
            // (high level or age — someone may want them for training despite the trait).
            if (!($heroCount >= $rosterLimit && $sellingCount >= 1)) {
                // Only run proactive dismissal when the roster is NOT already in recycle mode
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

                    // Keep if high-level — could still become a trainer at some point
                    if ($hero->getLevel() >= 4) {
                        continue;
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

                    try {
                        $this->dismissalService->dismiss($team, $hero);
                        $aliveHeroes = array_filter($aliveHeroes, static fn (Hero $h) => $h->getId() !== $heroId);
                        $heroCount = count($aliveHeroes);
                        break; // Dismiss at most one negative-trait hero per tick
                    } catch (\Throwable) {
                        // Dismissal failed, continue
                    }
                }
            }

            // 0b. Recycle Roster: If roster is full and we have at least 1 hero listed for sale,
            // dismiss the worst available combatant to make room for a new summon.
            if ($heroCount >= $rosterLimit && $sellingCount >= 1) {
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

                    if (count($dismissalCandidates) > 0) {
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
                            $heroCount = count($aliveHeroes);
                        } catch (\Throwable) {
                            // Dismissal failed, ignore
                        }
                    }
                }
            }

            // 1. Summoning
            // NPC teams keep summoning to train and sell up to roster limit whenever they have space
            $summonThreshold = $rosterLimit - 1;

            if ($heroCount <= $summonThreshold) {
                $summonStatus = $this->summoningService->getStatus($team);
                if ($summonStatus['available'] && $team->getGold() >= ($summonStatus['gold_cost'] + 150)) {
                    try {
                        $this->summoningService->summon($team);
                        ++$heroCount;
                    } catch (\Throwable) {
                        // Summon failed, ignore
                    }
                }
            }

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

                // Sort candidates: first by level ascending (balance levels), then by priorityIndex ascending
                usort($upgradeCandidates, static function (array $a, array $b): int {
                    if ($a['level'] !== $b['level']) {
                        return $a['level'] <=> $b['level'];
                    }

                    return $a['priorityIndex'] <=> $b['priorityIndex'];
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

            // 3. Marketplace - Selling (Item and Hero/Trainer)
            // Limit to at most 1 item and 1 hero/trainer listing per week to avoid spam
            $unequippedItems = $this->em->getRepository(Item::class)->findBy([
                'ownerTeam' => $team,
                'status' => ItemStatus::Available,
                'equippedHero' => null,
            ]);

            if (count($unequippedItems) > 0) {
                $itemToList = $unequippedItems[0];
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
                    } catch (\Throwable) {
                        // Ignore
                    }
                }
            }

            // Selling surplus/trained heroes or redundant trainers
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

                $sellingCandidate = null;
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

                    if (self::ROLE_MERCENARY_ACADEMY === $role) {
                        if (!$hero->isTrainer() && $hero->getLevel() >= 1) {
                            $sellingCandidate = $hero;
                            break;
                        }
                    } elseif (self::ROLE_VETERAN_GUILD === $role) {
                        if ($hero->isTrainer()) {
                            $sellingCandidate = $hero;
                            break;
                        }
                    } else {
                        if ($hero->getLevel() >= 1) {
                            $sellingCandidate = $hero;
                            break;
                        }
                    }
                }

                if (null !== $sellingCandidate) {
                    $sellingCandidateId = $sellingCandidate->getId();
                    if (null !== $sellingCandidateId) {
                        $price = $this->calculateHeroMarketPrice($sellingCandidate);
                        $listingType = $sellingCandidate->isTrainer() ? ListingType::Trainer->value : ListingType::Hero->value;
                        try {
                            $this->marketplaceService->createListing(
                                $team,
                                $listingType,
                                $sellingCandidateId,
                                $price,
                                $price,
                                ListingMode::BuyNow->value,
                                7,
                                $now
                            );
                        } catch (\Throwable) {
                            // Ignore
                        }
                    }
                }
            }

            // 4. Marketplace - Buying
            // Scan and buy player listings in the same Kingdom if budget allows
            $listings = $this->em->getRepository(MarketplaceListing::class)->findBy([
                'kingdom' => $kingdom,
                'status' => ListingStatus::Active,
            ], ['id' => 'ASC'], 20);

            foreach ($listings as $listing) {
                if ($listing->getSellerTeam()->getId() === $team->getId()) {
                    continue;
                }

                $price = $listing->getBuyoutPriceGold() ?? $listing->getPriceGold();
                if ($team->getGold() < ($safetyReserve + $price)) {
                    continue;
                }

                $shouldBuy = false;

                if (self::ROLE_ROYAL_COLLECTOR === $role) {
                    // Rich collectors buy high-end items and heroes
                    if (ListingType::Item === $listing->getListingType()) {
                        $item = $listing->getItem();
                        if (null !== $item && in_array($item->getRarity(), [ItemRarity::Epic, ItemRarity::Legendary, ItemRarity::Mythic], true)) {
                            $shouldBuy = true;
                        }
                    } elseif (in_array($listing->getListingType(), [ListingType::Hero, ListingType::Trainer], true)) {
                        $hero = $listing->getHero();
                        if (null !== $hero && $hero->getLevel() >= 1) {
                            $shouldBuy = true;
                        }
                    }
                } elseif (self::ROLE_SCAVENGER_CLAN === $role) {
                    // Scavengers buy cheap items to dismantle
                    if (ListingType::Item === $listing->getListingType() && $price < 150) {
                        $shouldBuy = true;
                    }
                } elseif (self::ROLE_MERCENARY_ACADEMY === $role) {
                    // Mercenary Academies buy physical/combat heroes
                    if (ListingType::Hero === $listing->getListingType()) {
                        $hero = $listing->getHero();
                        if (null !== $hero && $hero->getLevel() >= 1 && in_array($hero->getRace(), [Race::Orc, Race::Dwarf, Race::Human], true)) {
                            $shouldBuy = true;
                        }
                    }
                } elseif (self::ROLE_VETERAN_GUILD === $role) {
                    // Veteran Guilds buy trainers
                    if (ListingType::Trainer === $listing->getListingType()) {
                        $hero = $listing->getHero();
                        if (null !== $hero && $hero->getLevel() >= 1) {
                            $shouldBuy = true;
                        }
                    }
                }

                if ($shouldBuy) {
                    $listingId = $listing->getId();
                    if (null !== $listingId) {
                        try {
                            $this->marketplaceService->buyListing($team, $listingId, $now);
                            break; // Buy at most one listing per week to maintain budget
                        } catch (\Throwable) {
                            // Ignore buy error
                        }
                    }
                }
            }
        }

        $this->em->flush();
    }

    /**
     * Auto-equip best available items from inventory to active heroes, considering masteries
     * and buying basic items as a fallback.
     *
     * @param array<Hero> $heroes
     */
    private function autoEquipItems(Team $team, array $heroes): void
    {
        $unequippedItems = $this->em->getRepository(Item::class)->findBy([
            'ownerTeam' => $team,
            'status' => ItemStatus::Available,
            'equippedHero' => null,
        ]);

        // Sort unequipped items by rarity descending to prioritize equipping better quality gear first
        $rarityValues = [
            'mythic' => 6,
            'legendary' => 5,
            'epic' => 4,
            'rare' => 3,
            'uncommon' => 2,
            'common' => 1,
        ];
        usort($unequippedItems, fn ($a, $b) => $rarityValues[$b->getRarity()->value] <=> $rarityValues[$a->getRarity()->value]);

        // Define a list of slots that we manage with masteries/basic items
        $managedSlots = [
            ItemSlotType::MainHand,
            ItemSlotType::OffHand,
            ItemSlotType::Head,
            ItemSlotType::Body,
            ItemSlotType::Hands,
            ItemSlotType::Feet,
        ];

        // 1. Optimal Mastery Pass
        foreach ($heroes as $hero) {
            $prefWeapon = $this->getPreferredWeaponSubType($hero);
            $prefArmor = $this->getPreferredArmorSubType($hero);
            $prefOffHand = $this->getPreferredOffHandSubType($hero, $prefWeapon);

            foreach ($managedSlots as $slot) {
                // Get the preferred sub-type for this slot
                $prefSubType = match ($slot) {
                    ItemSlotType::MainHand => $prefWeapon,
                    ItemSlotType::OffHand => $prefOffHand,
                    default => $prefArmor,
                };

                // Find currently equipped item in this slot
                $equipped = $this->em->getRepository(Item::class)->findOneBy([
                    'equippedHero' => $hero,
                    'equippedSlot' => $slot,
                ]);

                if (null === $prefSubType) {
                    // Two-handed weapon equipped, offhand must be empty
                    if (null !== $equipped) {
                        try {
                            $this->itemService->unequip($equipped);
                        } catch (\Throwable) {
                        }
                    }
                    continue;
                }

                // If currently equipped item matches preferred subtype, keep it
                if (null !== $equipped && $equipped->getSubType() === $prefSubType) {
                    continue;
                }

                // Try to find a matching item in the inventory
                $foundInInventory = false;
                foreach ($unequippedItems as $idx => $item) {
                    if ($item->getSlotType() === $slot && $item->getSubType() === $prefSubType) {
                        try {
                            $this->itemService->equip($item, $hero, $slot);
                            unset($unequippedItems[$idx]);
                            $foundInInventory = true;
                            break;
                        } catch (\Throwable) {
                        }
                    }
                }

                if ($foundInInventory) {
                    continue;
                }

                // Fallback: If no matching item in inventory, check if we should purchase the basic item.
                // Only purchase if nothing is equipped or the equipped item is common (and doesn't match).
                if (null === $equipped || ItemRarity::Common === $equipped->getRarity()) {
                    // Determine the basic item key
                    $itemKey = null;
                    if (in_array($slot, [ItemSlotType::Head, ItemSlotType::Body, ItemSlotType::Hands, ItemSlotType::Feet], true)) {
                        $itemKey = self::ARMOR_TO_BASIC_ITEM[$prefArmor->value][$slot->value] ?? null;
                    } else {
                        $itemKey = self::SUBTYPE_TO_BASIC_ITEM[$prefSubType->value] ?? null;
                    }

                    if (null !== $itemKey) {
                        $cost = ItemService::BASIC_EQUIPMENT[$itemKey]['cost'];
                        // Keep a minimum reserve of 150 gold so NPC teams don't fully go broke on basic gear
                        if ($team->getGold() - $cost >= 150) {
                            try {
                                $newItem = $this->itemService->purchaseBasicItem($team, $itemKey);
                                $this->itemService->equip($newItem, $hero, $slot);
                            } catch (\Throwable) {
                            }
                        }
                    }
                }
            }
        }

        // Re-filter remaining unequipped items for fallback slots (Rings, Amulets, and leftovers)
        $unequippedItems = array_values($unequippedItems);

        // 2. Fallback Empty-Slot Pass
        foreach ($heroes as $hero) {
            foreach (ItemSlotType::cases() as $slot) {
                // If a two-handed weapon is equipped, we must skip OffHand
                if (ItemSlotType::OffHand === $slot) {
                    $mainHand = $this->em->getRepository(Item::class)->findOneBy([
                        'equippedHero' => $hero,
                        'equippedSlot' => ItemSlotType::MainHand,
                    ]);
                    if (null !== $mainHand && null !== $mainHand->getSubType()) {
                        $twoHandedWeapons = [
                            ItemSubType::TwoHandedSword,
                            ItemSubType::TwoHandedAxe,
                            ItemSubType::TwoHandedMace,
                            ItemSubType::Bow,
                            ItemSubType::Crossbow,
                            ItemSubType::Staff,
                        ];
                        if (in_array($mainHand->getSubType(), $twoHandedWeapons, true)) {
                            continue;
                        }
                    }
                }

                $equipped = $this->em->getRepository(Item::class)->findOneBy([
                    'equippedHero' => $hero,
                    'equippedSlot' => $slot,
                ]);

                if (null !== $equipped) {
                    continue;
                }

                // Equip the first available item of correct slot type
                foreach ($unequippedItems as $idx => $item) {
                    if ($item->getSlotType() === $slot) {
                        try {
                            $this->itemService->equip($item, $hero, $slot);
                            unset($unequippedItems[$idx]);
                            $unequippedItems = array_values($unequippedItems);
                            break;
                        } catch (\Throwable) {
                        }
                    }
                }
            }
        }
    }

    /**
     * Get preferred weapon sub-type for a hero based on masteries or attributes.
     */
    private function getPreferredWeaponSubType(Hero $hero): ItemSubType
    {
        $weaponSubTypes = [
            ItemSubType::OneHandedSword,
            ItemSubType::TwoHandedSword,
            ItemSubType::OneHandedAxe,
            ItemSubType::TwoHandedAxe,
            ItemSubType::OneHandedMace,
            ItemSubType::TwoHandedMace,
            ItemSubType::Dagger,
            ItemSubType::Bow,
            ItemSubType::Crossbow,
            ItemSubType::Wand,
            ItemSubType::Staff,
        ];

        // Find highest weapon mastery
        $bestMastery = null;
        foreach ($hero->getWeaponMasteries() as $wm) {
            if (in_array($wm->getStyle(), $weaponSubTypes, true)) {
                if (null === $bestMastery) {
                    $bestMastery = $wm;
                } else {
                    $bestCompare = $bestMastery->getMasteryTier() <=> $wm->getMasteryTier();
                    if (0 === $bestCompare) {
                        $bestCompare = $bestMastery->getXp() <=> $wm->getXp();
                    }
                    if ($bestCompare < 0) {
                        $bestMastery = $wm;
                    }
                }
            }
        }

        if (null !== $bestMastery && ($bestMastery->getMasteryTier() > 1 || $bestMastery->getXp() > 0)) {
            return $bestMastery->getStyle();
        }

        // Default based on stats
        $str = $hero->getStr();
        $dex = $hero->getDex();
        $intel = $hero->getIntel();

        if ($intel > $str && $intel > $dex) {
            return ItemSubType::Staff;
        } elseif ($dex > $str) {
            return ItemSubType::Bow;
        }

        return ItemSubType::OneHandedSword;
    }

    /**
     * Get preferred armor sub-type for a hero based on masteries or attributes.
     */
    private function getPreferredArmorSubType(Hero $hero): ItemSubType
    {
        $armorSubTypes = [
            ItemSubType::LightArmor,
            ItemSubType::MediumArmor,
            ItemSubType::HeavyArmor,
        ];

        // Find highest armor mastery
        $bestMastery = null;
        foreach ($hero->getWeaponMasteries() as $wm) {
            if (in_array($wm->getStyle(), $armorSubTypes, true)) {
                if (null === $bestMastery) {
                    $bestMastery = $wm;
                } else {
                    $bestCompare = $bestMastery->getMasteryTier() <=> $wm->getMasteryTier();
                    if (0 === $bestCompare) {
                        $bestCompare = $bestMastery->getXp() <=> $wm->getXp();
                    }
                    if ($bestCompare < 0) {
                        $bestMastery = $wm;
                    }
                }
            }
        }

        if (null !== $bestMastery && ($bestMastery->getMasteryTier() > 1 || $bestMastery->getXp() > 0)) {
            return $bestMastery->getStyle();
        }

        // Default based on stats
        $str = $hero->getStr();
        $dex = $hero->getDex();
        $kon = $hero->getKon();
        $intel = $hero->getIntel();

        if ($str + $kon > $intel + $dex) {
            return ItemSubType::HeavyArmor;
        } elseif ($dex > $intel) {
            return ItemSubType::MediumArmor;
        }

        return ItemSubType::LightArmor;
    }

    /**
     * Get preferred off-hand sub-type for a hero.
     */
    private function getPreferredOffHandSubType(Hero $hero, ItemSubType $preferredWeapon): ?ItemSubType
    {
        $twoHandedWeapons = [
            ItemSubType::TwoHandedSword,
            ItemSubType::TwoHandedAxe,
            ItemSubType::TwoHandedMace,
            ItemSubType::Bow,
            ItemSubType::Crossbow,
            ItemSubType::Staff,
        ];

        if (in_array($preferredWeapon, $twoHandedWeapons, true)) {
            return null;
        }

        if (ItemSubType::Wand === $preferredWeapon || ($hero->getIntel() > $hero->getStr() && $hero->getIntel() > $hero->getDex())) {
            return ItemSubType::SpellAccelerator;
        }

        return ItemSubType::Shield;
    }

    /**
     * @return array<string, string>
     */
    private function getStrategyForSlot(string $role, FormationPosition $position): array
    {
        // Simple heuristic strategy based on role
        if (self::ROLE_MERCENARY_ACADEMY === $role) {
            return ['target' => 'weakest', 'action' => 'attack', 'aggression' => 'high'];
        } elseif (self::ROLE_VETERAN_GUILD === $role) {
            return ['target' => 'frontline', 'action' => 'defend', 'aggression' => 'low'];
        }

        return ['target' => 'balanced', 'action' => 'balanced', 'aggression' => 'medium'];
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpellPrioritiesForSlot(string $role, FormationPosition $position): array
    {
        if (self::ROLE_ROYAL_COLLECTOR === $role) {
            return ['heal_trigger_pct' => 50, 'prefer_magic' => true];
        }

        return ['heal_trigger_pct' => 30, 'prefer_magic' => false];
    }

    private function getApproachForRole(string $role): FormationApproach
    {
        return match ($role) {
            self::ROLE_MERCENARY_ACADEMY => FormationApproach::Aggressive,
            self::ROLE_VETERAN_GUILD => FormationApproach::Defensive,
            default => FormationApproach::Balanced,
        };
    }

    /**
     * @return array<array{type: TrainingType, attribute: string|null}>
     */
    private function getTrainingConfigsForRole(string $role): array
    {
        return match ($role) {
            self::ROLE_MERCENARY_ACADEMY => [
                ['type' => TrainingType::Attribute, 'attribute' => 'str'],
                ['type' => TrainingType::Attribute, 'attribute' => 'spd'],
            ],
            self::ROLE_VETERAN_GUILD => [
                ['type' => TrainingType::Attribute, 'attribute' => 'kon'],
                ['type' => TrainingType::Attribute, 'attribute' => 'wil'],
            ],
            self::ROLE_ROYAL_COLLECTOR => [
                ['type' => TrainingType::Attribute, 'attribute' => 'int'],
                ['type' => TrainingType::Magic, 'attribute' => null],
            ],
            self::ROLE_SCAVENGER_CLAN => [
                ['type' => TrainingType::Attribute, 'attribute' => 'spd'],
                ['type' => TrainingType::Attribute, 'attribute' => 'lck'],
            ],
            default => [
                ['type' => TrainingType::Attribute, 'attribute' => 'str'],
                ['type' => TrainingType::Attribute, 'attribute' => 'spd'],
            ],
        };
    }

    /**
     * @return array<FacilityType>
     */
    private function getFacilityPrioritiesForRole(string $role): array
    {
        return match ($role) {
            self::ROLE_MERCENARY_ACADEMY => [
                FacilityType::Training,
                FacilityType::SummoningChamber,
                FacilityType::Barracks,
            ],
            self::ROLE_VETERAN_GUILD => [
                FacilityType::Training,
                FacilityType::Treasury,
                FacilityType::Medical,
            ],
            self::ROLE_ROYAL_COLLECTOR => [
                FacilityType::Arena,
                FacilityType::Library,
                FacilityType::SummoningChamber,
            ],
            self::ROLE_SCAVENGER_CLAN => [
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

    private function calculateItemMarketPrice(Item $item): int
    {
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

    /**
     * Returns true if the trait is purely negative (no upside for combat, training, or economy).
     * Used to identify heroes that NPC teams should proactively dismiss or discount heavily.
     *
     * Purely negative: Volatile, Slacker, Fragile, GlassJaw.
     */
    private function isPurelyNegativeTrait(?HeroTrait $trait): bool
    {
        if (null === $trait) {
            return false;
        }

        return \in_array($trait, [
            HeroTrait::Volatile,
            HeroTrait::Slacker,
            HeroTrait::Fragile,
            HeroTrait::GlassJaw,
        ], true);
    }
}
