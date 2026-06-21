<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueStanding;
use App\Entity\League\LeagueTier;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\LeagueSeasonStatus;
use App\Service\Economy\EconomyService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class SeasonTransitionService
{
    private const POSITION_MULTIPLIERS = [
        1 => 1.50,
        2 => 1.30,
        3 => 1.15,
        4 => 1.05,
        5 => 1.00,
        6 => 1.00,
        7 => 0.90,
        8 => 0.90,
        9 => 0.80,
        10 => 0.70,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LeagueFixtureScheduler $fixtureScheduler,
        private readonly EconomyService $economyService,
        private readonly TeamChronicleService $teamChronicleService,
    ) {
    }

    /**
     * Pre-creates the structure (Season, Tiers, Groups) for the next season.
     * Usually runs on Monday of Week 11.
     */
    public function prepareUpcomingSeason(Kingdom $kingdom): LeagueSeason
    {
        // Find the latest season in the kingdom to determine dates and season number
        /** @var LeagueSeason|null $latestSeason */
        $latestSeason = $this->em->getRepository(LeagueSeason::class)->findOneBy(
            ['kingdom' => $kingdom],
            ['seasonNumber' => 'DESC']
        );

        if (null !== $latestSeason && LeagueSeasonStatus::Scheduled === $latestSeason->getStatus()) {
            return $latestSeason;
        }

        $nextSeasonNumber = 1;
        $startDate = new \DateTimeImmutable('today')->modify('next monday')->setTime(0, 0, 0);

        if (null !== $latestSeason) {
            $nextSeasonNumber = $latestSeason->getSeasonNumber() + 1;
            // The next season starts on the day after the current one ends
            $startDate = $latestSeason->getEndDate()->modify('+1 day')->setTime(0, 0, 0);
        }

        // Align end date based on first Monday
        $prepMonday = (1 === (int) $startDate->format('N'))
            ? $startDate
            : $startDate->modify('next monday');
        $endDate = $prepMonday->modify(sprintf('+%d days -1 day', max(1, $kingdom->getSeasonLength())));

        $season = new LeagueSeason();
        $season->setKingdom($kingdom);
        $season->setSeasonNumber($nextSeasonNumber);
        $season->setStartDate($startDate);
        $season->setEndDate($endDate);
        $season->setStatus(LeagueSeasonStatus::Scheduled);

        $this->em->persist($season);

        $leagueConfig = $kingdom->getLeagueTiersConfig();
        /** @var list<array<string, mixed>> $tiers */
        $tiers = $leagueConfig['tiers'] ?? [];

        foreach ($tiers as $tierDef) {
            $tier = new LeagueTier();
            $tier->setSeason($season);
            $tier->setTierName((string) ($tierDef['name'] ?? 'Tier'));
            $tier->setPromotionSlots((int) ($tierDef['promotion_slots'] ?? 0));
            $tier->setRelegationSlots((int) ($tierDef['relegation_slots'] ?? 0));
            /** @var array<string, mixed> $rewards */
            $rewards = $tierDef['rewards'] ?? [];
            $tier->setRewards($rewards);
            $season->addTier($tier);
            $this->em->persist($tier);

            $groupsDef = $tierDef['groups'] ?? 0;
            $groupCount = is_array($groupsDef) ? count($groupsDef) : (int) $groupsDef;
            $tierName = (string) ($tierDef['name'] ?? 'Tier');
            for ($g = 1; $g <= $groupCount; ++$g) {
                $group = new LeagueGroup();
                $group->setTier($tier);
                if (is_array($groupsDef) && isset($groupsDef[$g - 1])) {
                    $group->setGroupName((string) $groupsDef[$g - 1]);
                } else {
                    $group->setGroupName(sprintf('%s-G%d', $tierName, $g));
                }
                $tier->addGroup($group);
                $this->em->persist($group);
            }
        }

        $this->em->flush();

        return $season;
    }

    /**
     * Finalizes current season, processes transitions, shuffles and seeds next season, then activates it.
     */
    public function executeTransition(Kingdom $kingdom): void
    {
        // 1. Fetch current active season
        /** @var LeagueSeason|null $currentSeason */
        $currentSeason = $this->em->getRepository(LeagueSeason::class)->findOneBy([
            'kingdom' => $kingdom,
            'status' => LeagueSeasonStatus::Active,
        ]);

        if (null === $currentSeason) {
            throw new \RuntimeException('No active season found for transition in Kingdom: '.$kingdom->getName());
        }

        // 2. Fetch or prepare upcoming scheduled season
        /** @var LeagueSeason|null $upcomingSeason */
        $upcomingSeason = $this->em->getRepository(LeagueSeason::class)->findOneBy([
            'kingdom' => $kingdom,
            'status' => LeagueSeasonStatus::Scheduled,
        ]);

        if (null === $upcomingSeason) {
            $upcomingSeason = $this->prepareUpcomingSeason($kingdom);
        }

        $currentSeasonNumber = $currentSeason->getSeasonNumber();
        $leagueConfig = $kingdom->getLeagueTiersConfig();
        $teamsPerGroup = max(1, (int) ($leagueConfig['teams_per_group'] ?? 10));

        // Sort both seasons' tiers to match them up (index 0 is highest tier, e.g., T1)
        $currentTiers = $this->getSortedTiers($currentSeason);
        $upcomingTiers = $this->getSortedTiers($upcomingSeason);

        if (count($currentTiers) !== count($upcomingTiers)) {
            throw new \RuntimeException('Mismatch between current and upcoming season tier counts.');
        }

        // Keep track of new tier assignment for each team ID
        // Maps team ID -> upcoming LeagueTier entity
        $teamToNewTier = [];
        $teamToOldTier = [];
        // Maps team ID -> standing details (old standing object and its position in sorted standings)
        /** @var array<int, array{standing: LeagueStanding, position: int, status: string}> $teamStandingDetails */
        $teamStandingDetails = [];

        // Determine all teams in the active season, sort their standings in each group, and initialize defaults
        foreach ($currentTiers as $idx => $currTier) {
            $upcomingTier = $upcomingTiers[$idx];
            foreach ($currTier->getGroups() as $group) {
                $standings = $group->getStandings()->toArray();
                usort($standings, [$this, 'compareStandings']);

                foreach ($standings as $posIdx => $standing) {
                    $team = $standing->getTeam();
                    $teamId = $team->getId();
                    if (null === $teamId) {
                        continue;
                    }
                    $teamToOldTier[$teamId] = $currTier;
                    $teamToNewTier[$teamId] = $upcomingTier; // default: stay in same tier
                    $teamStandingDetails[$teamId] = [
                        'standing' => $standing,
                        'position' => $posIdx + 1,
                        'status' => 'retained',
                    ];
                }
            }
        }

        // Determine promotions and relegations
        foreach ($currentTiers as $idx => $currTier) {
            // Promotion (into higher tier)
            if ($idx > 0) {
                $higherTierUpcoming = $upcomingTiers[$idx - 1];
                $promotedTeams = $this->selectTeamsForPromotion(
                    array_values($currTier->getGroups()->toArray()),
                    $currTier->getPromotionSlots()
                );
                foreach ($promotedTeams as $team) {
                    $teamId = $team->getId();
                    if (null !== $teamId && isset($teamStandingDetails[$teamId])) {
                        $teamToNewTier[$teamId] = $higherTierUpcoming;
                        $teamStandingDetails[$teamId]['status'] = 'promoted';
                    }
                }
            }

            // Relegation (into lower tier)
            if ($idx < count($currentTiers) - 1) {
                $lowerTierUpcoming = $upcomingTiers[$idx + 1];
                $relegatedTeams = $this->selectTeamsForRelegation(
                    array_values($currTier->getGroups()->toArray()),
                    $currTier->getRelegationSlots()
                );
                foreach ($relegatedTeams as $team) {
                    $teamId = $team->getId();
                    if (null !== $teamId && isset($teamStandingDetails[$teamId])) {
                        $teamToNewTier[$teamId] = $lowerTierUpcoming;
                        $teamStandingDetails[$teamId]['status'] = 'relegated';
                    }
                }
            }
        }

        // Calculate and distribute rewards and log activities
        foreach ($teamStandingDetails as $teamId => $details) {
            /** @var LeagueStanding $standing */
            $standing = $details['standing'];
            $team = $standing->getTeam();
            $position = (int) $details['position'];
            $status = (string) $details['status'];

            /** @var LeagueTier $oldTier */
            $oldTier = $teamToOldTier[$teamId];
            /** @var LeagueTier $newTier */
            $newTier = $teamToNewTier[$teamId];

            $baseRewards = $oldTier->getRewards();
            $baseGold = (int) ($baseRewards['gold'] ?? 0);
            // Multipliers
            $mPos = self::POSITION_MULTIPLIERS[$position] ?? 1.0;
            $mSeason = pow(1.05, $currentSeasonNumber - 1);
            $mStatus = 1.00;

            if ('promoted' === $status) {
                $newTierName = strtolower($newTier->getTierName());
                if (str_contains($newTierName, 't1')) {
                    $mStatus = 1.40;
                } elseif (str_contains($newTierName, 't2')) {
                    $mStatus = 1.20;
                } else {
                    $mStatus = 1.10; // general fallback
                }
            } elseif ('relegated' === $status) {
                $newTierName = strtolower($newTier->getTierName());
                if (str_contains($newTierName, 't2')) {
                    $mStatus = 0.85;
                } elseif (str_contains($newTierName, 't3')) {
                    $mStatus = 0.90;
                } else {
                    $mStatus = 0.95; // general fallback
                }
            }

            $goldGranted = (int) round($baseGold * $mPos * $mSeason * $mStatus);
            // 1. Credit team balances
            if ($goldGranted > 0) {
                $this->economyService->addGold(
                    $team,
                    $goldGranted,
                    FinancialRecordType::LeagueReward,
                    FinancialRecordActor::System,
                    ['season' => $currentSeasonNumber]
                );
            }

            // 2. Add Activity Log
            $this->teamChronicleService->recordSeasonEnded(
                $team,
                $currentSeasonNumber,
                $oldTier->getTierName(),
                $position,
                $status,
                $goldGranted,
            );
        }

        // Shuffle teams and seed them into groups of their new tiers for the upcoming season
        // Map new tier ID -> list of Teams
        $teamsInUpcomingTier = [];
        foreach ($teamToNewTier as $teamId => $newTier) {
            $newTierId = $newTier->getId();
            if (null === $newTierId) {
                continue;
            }
            if (!isset($teamsInUpcomingTier[$newTierId])) {
                $teamsInUpcomingTier[$newTierId] = [];
            }
            /** @var Team $team */
            $team = $this->em->getReference(Team::class, $teamId);
            $teamsInUpcomingTier[$newTierId][] = $team;
        }

        foreach ($upcomingTiers as $upTier) {
            $upTierId = $upTier->getId();
            if (null === $upTierId) {
                continue;
            }
            $teams = $teamsInUpcomingTier[$upTierId] ?? [];
            shuffle($teams); // Shuffling to prevent identical matchups every season

            $groups = $upTier->getGroups()->toArray();
            // Sort groups by ID or name to make seeding deterministic per groups
            usort($groups, fn (LeagueGroup $a, LeagueGroup $b) => $a->getId() <=> $b->getId());

            $chunks = array_chunk($teams, $teamsPerGroup);
            foreach ($groups as $gIdx => $group) {
                $chunk = $chunks[$gIdx] ?? [];
                foreach ($chunk as $team) {
                    $standing = new LeagueStanding();
                    $standing->setGroup($group);
                    $standing->setTeam($team);
                    $group->getStandings()->add($standing);
                    $this->em->persist($standing);
                }

                // Schedule fixtures for this newly seeded group
                $kingdom = $upcomingSeason->getKingdom();
                $this->fixtureScheduler->scheduleFixturesForGroup(
                    $group,
                    $upcomingSeason->getStartDate(),
                    $kingdom->getTimezone(),
                );
            }
        }

        // Transition statuses
        $currentSeason->setStatus(LeagueSeasonStatus::Completed);
        $upcomingSeason->setStatus(LeagueSeasonStatus::Active);

        $this->em->flush();
    }

    /**
     * @return list<LeagueTier>
     */
    private function getSortedTiers(LeagueSeason $season): array
    {
        $tiers = $season->getTiers()->toArray();
        usort($tiers, fn (LeagueTier $a, LeagueTier $b) => $a->getId() <=> $b->getId());

        return $tiers;
    }

    private function compareStandings(LeagueStanding $a, LeagueStanding $b): int
    {
        // 1. Points DESC
        if ($a->getPoints() !== $b->getPoints()) {
            return $b->getPoints() <=> $a->getPoints();
        }
        // 2. Goal Difference DESC
        if ($a->getGoalDifference() !== $b->getGoalDifference()) {
            return $b->getGoalDifference() <=> $a->getGoalDifference();
        }
        // 3. Wins DESC
        if ($a->getWins() !== $b->getWins()) {
            return $b->getWins() <=> $a->getWins();
        }
        // 4. Team Type (Real player (isNpc = false) comes first DESC, false (0) < true (1))
        $npcA = $a->getTeam()->isNpc();
        $npcB = $b->getTeam()->isNpc();
        if ($npcA !== $npcB) {
            return $npcA <=> $npcB; // 0 <=> 1 returns -1, so 0 (real player) ranked above 1 (NPC)
        }
        // 5. Team Reputation DESC
        if ($a->getTeam()->getReputation() !== $b->getTeam()->getReputation()) {
            return $b->getTeam()->getReputation() <=> $a->getTeam()->getReputation();
        }
        // 6. Team Chemistry DESC
        if ($a->getTeam()->getChemistry() !== $b->getTeam()->getChemistry()) {
            return $b->getTeam()->getChemistry() <=> $a->getTeam()->getChemistry();
        }

        // 7. Entity ID ASC
        return $a->getTeam()->getId() <=> $b->getTeam()->getId();
    }

    /**
     * @param list<LeagueGroup> $groups
     *
     * @return list<Team>
     */
    private function selectTeamsForPromotion(array $groups, int $P): array
    {
        if ($P <= 0 || empty($groups)) {
            return [];
        }
        $G = count($groups);

        $sortedGroupStandings = [];
        foreach ($groups as $group) {
            $standings = $group->getStandings()->toArray();
            usort($standings, [$this, 'compareStandings']);
            $sortedGroupStandings[] = $standings;
        }

        $promoted = [];
        $k = (int) ($P / $G);
        $remaining = $P % $G;

        // Promote top k from each group
        if ($k > 0) {
            foreach ($sortedGroupStandings as $standings) {
                for ($i = 0; $i < $k; ++$i) {
                    if (isset($standings[$i])) {
                        $promoted[] = $standings[$i]->getTeam();
                    }
                }
            }
        }

        // Compare (k+1)-th place teams for the remaining slots
        if ($remaining > 0) {
            $candidates = [];
            foreach ($sortedGroupStandings as $standings) {
                if (isset($standings[$k])) {
                    $candidates[] = $standings[$k];
                }
            }
            usort($candidates, [$this, 'compareStandings']);
            for ($i = 0; $i < $remaining; ++$i) {
                if (isset($candidates[$i])) {
                    $promoted[] = $candidates[$i]->getTeam();
                }
            }
        }

        return $promoted;
    }

    /**
     * @param list<LeagueGroup> $groups
     *
     * @return list<Team>
     */
    private function selectTeamsForRelegation(array $groups, int $R_slot): array
    {
        if ($R_slot <= 0 || empty($groups)) {
            return [];
        }
        $G = count($groups);

        $sortedGroupStandings = [];
        foreach ($groups as $group) {
            $standings = $group->getStandings()->toArray();
            usort($standings, [$this, 'compareStandings']);
            $sortedGroupStandings[] = $standings;
        }

        $relegated = [];
        $k = (int) ($R_slot / $G);
        $remaining = $R_slot % $G;

        // Relegate bottom k from each group
        if ($k > 0) {
            foreach ($sortedGroupStandings as $standings) {
                $count = count($standings);
                for ($i = 0; $i < $k; ++$i) {
                    $idx = $count - 1 - $i;
                    if (isset($standings[$idx])) {
                        $relegated[] = $standings[$idx]->getTeam();
                    }
                }
            }
        }

        // Compare (10-k)-th place teams for the remaining slots
        if ($remaining > 0) {
            $candidates = [];
            foreach ($sortedGroupStandings as $standings) {
                $count = count($standings);
                $idx = $count - 1 - $k;
                if (isset($standings[$idx])) {
                    $candidates[] = $standings[$idx];
                }
            }
            usort($candidates, [$this, 'compareStandings']);

            $countCand = count($candidates);
            for ($i = 0; $i < $remaining; ++$i) {
                $idx = $countCand - 1 - $i;
                if (isset($candidates[$idx])) {
                    $relegated[] = $candidates[$idx]->getTeam();
                }
            }
        }

        return $relegated;
    }
}
