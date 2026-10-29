<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueStanding;
use App\Entity\Marketplace\MarketplaceTransaction;
use App\Entity\Team\FinancialRecord;
use App\Entity\Team\TeamChronicle;
use App\Enum\BattleResult;
use App\Enum\ChronicleEventType;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\LeagueFixtureStatus;
use App\Enum\MemorialCause;
use App\Service\Config\RaceConfig;
use App\Service\Team\NpcSimulationService;
use App\Service\Team\TeamRosterService;
use Doctrine\ORM\EntityManagerInterface;

class SeasonReportBuilder
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RaceConfig $raceConfig,
        private readonly NpcSimulationService $npcSimulationService,
        private readonly SeasonHealthEvaluator $healthEvaluator,
    ) {
    }

    /**
     * @param array<int, array{standing: LeagueStanding, position: int, status: string, gold?: int}> $teamDetails
     * @param array<string, mixed>|null                                                              $previousPayload
     *
     * @return array<string, mixed>
     */
    public function build(LeagueSeason $season, array $teamDetails, ?array $previousPayload = null): array
    {
        $kingdom = $season->getKingdom();
        $from = $season->getStartDate()->setTime(0, 0, 0);
        $to = $season->getEndDate()->setTime(23, 59, 59);

        $heroesByTeam = $this->heroesByTeam($kingdom);
        $hqByTeam = $this->hqByTeam($kingdom);
        $ledgerByTeam = $this->ledgerByTeam($kingdom, $from, $to);
        $eventsByTeam = $this->eventsByTeam($kingdom, $from, $to);
        $deathsByTeam = $this->combatDeathsByTeam($kingdom, $from, $to);
        $market = $this->marketSummary($kingdom, $from, $to);
        $combat = $this->combatSummary($season);
        $previousTeams = is_array($previousPayload['teams'] ?? null) ? $previousPayload['teams'] : [];

        $teams = [];
        $goldValues = [];
        $ovrValues = [];
        $ageValues = [];
        $levelValues = [];
        $pointsValues = [];
        $crisisCount = 0;
        $yoYoCount = 0;
        $ageByRace = [];
        $npcByRole = [];
        $combatReadyShort = 0;
        $totalCombatDeaths = 0;

        foreach ($teamDetails as $teamId => $details) {
            $standing = $details['standing'];
            $team = $standing->getTeam();
            $group = $standing->getGroup();
            $heroes = $heroesByTeam[$teamId] ?? [];
            $roster = $this->rosterSnapshot($heroes, $ageByRace);
            $ledger = $ledgerByTeam[$teamId] ?? [];
            $events = $eventsByTeam[$teamId] ?? $this->emptyEvents();
            $events['combat_deaths'] = $deathsByTeam[$teamId] ?? 0;
            $totalCombatDeaths += $events['combat_deaths'];

            $goldEnd = $team->getGold();
            $goldValues[] = $goldEnd;
            $pointsValues[] = $standing->getPoints();
            if (null !== $roster['mean_ovr']) {
                $ovrValues[] = $roster['mean_ovr'];
            }
            if (null !== $roster['median_age']) {
                $ageValues[] = $roster['median_age'];
            }
            if (null !== $roster['mean_level']) {
                $levelValues[] = $roster['mean_level'];
            }

            $inCrisis = $team->getUnpaidDebt() > 0 || $team->getCrisisWeeks() > 0;
            if ($inCrisis) {
                ++$crisisCount;
            }

            $prevTeam = $this->findPreviousTeam($previousTeams, $teamId);
            $prevStatus = is_array($prevTeam) ? (string) ($prevTeam['status'] ?? '') : '';
            $yoYo = 'promoted' === $prevStatus && 'relegated' === $details['status'];
            if ($yoYo) {
                ++$yoYoCount;
            }

            $npcRole = $team->isNpc() ? $this->npcSimulationService->getEconomicRole($team) : null;
            if (null !== $npcRole) {
                $npcByRole[$npcRole] ??= ['count' => 0, 'points' => [], 'gold' => [], 'wins' => []];
                ++$npcByRole[$npcRole]['count'];
                $npcByRole[$npcRole]['points'][] = $standing->getPoints();
                $npcByRole[$npcRole]['gold'][] = $goldEnd;
                $npcByRole[$npcRole]['wins'][] = $standing->getWins();
            }

            if ($roster['combat_ready'] < TeamRosterService::MIN_COMBAT_READY_HEROES) {
                ++$combatReadyShort;
            }

            $goldOpen = is_array($prevTeam) && isset($prevTeam['gold']['end'])
                ? (int) $prevTeam['gold']['end']
                : null;

            $teams[] = [
                'id' => $teamId,
                'name' => $team->getName(),
                'is_npc' => $team->isNpc(),
                'npc_role' => $npcRole,
                'tier' => $group->getTier()->getTierName(),
                'group' => $group->getGroupName(),
                'group_id' => $group->getId(),
                'position' => $details['position'],
                'played' => $standing->getPlayed(),
                'wins' => $standing->getWins(),
                'draws' => $standing->getDraws(),
                'losses' => $standing->getLosses(),
                'points' => $standing->getPoints(),
                'goal_difference' => $standing->getGoalDifference(),
                'status' => $details['status'],
                'yo_yo' => $yoYo,
                'gold' => [
                    'open' => $goldOpen,
                    'end' => $goldEnd,
                    'reward' => (int) ($details['gold'] ?? 0),
                ],
                'ledger' => $ledger,
                'morale' => $team->getMorale(),
                'reputation' => $team->getReputation(),
                'chemistry' => $team->getChemistry(),
                'fan_base' => $team->getFanBase(),
                'in_crisis' => $inCrisis,
                'hq' => $this->hqSnapshot($hqByTeam[$teamId] ?? null),
                'roster' => $roster,
                'events' => $events,
            ];
        }

        $npcShare = 0.0;
        if ([] !== $teams) {
            $npcCount = count(array_filter($teams, static fn (array $row): bool => (bool) $row['is_npc']));
            $npcShare = $npcCount / count($teams);
        }

        $byTier = $this->groupAggregates($teams, 'tier');
        $byGroup = $this->groupAggregates($teams, 'group');

        $medianAgeByRace = [];
        foreach ($ageByRace as $race => $ages) {
            $medianAgeByRace[$race] = SeasonStats::median($ages);
        }

        $npcSummary = [];
        foreach ($npcByRole as $role => $row) {
            $npcSummary[$role] = [
                'count' => $row['count'],
                'mean_points' => SeasonStats::mean($row['points']),
                'mean_gold' => SeasonStats::mean($row['gold']),
                'mean_wins' => SeasonStats::mean($row['wins']),
            ];
        }

        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'kingdom' => [
                'id' => $kingdom->getId(),
                'name' => $kingdom->getName(),
                'pace_preset' => $kingdom->getPacePreset()->value,
                'team_count' => count($teams),
                'npc_share' => $npcShare,
                'royal_treasury_gold' => $kingdom->getRoyalTreasuryGold(),
            ],
            'season' => [
                'id' => $season->getId(),
                'number' => $season->getSeasonNumber(),
                'start' => $season->getStartDate()->format('Y-m-d'),
                'end' => $season->getEndDate()->format('Y-m-d'),
            ],
            'teams' => $teams,
            'aggregates' => [
                'kingdom' => [
                    'gold' => SeasonStats::spread($goldValues),
                    'gold_gini' => SeasonStats::gini($goldValues),
                    'ovr' => SeasonStats::spread($ovrValues),
                    'age' => SeasonStats::spread($ageValues),
                    'level' => SeasonStats::spread($levelValues),
                    'points' => SeasonStats::spread($pointsValues),
                    'crisis_count' => $crisisCount,
                    'combat_ready_short' => $combatReadyShort,
                ],
                'by_tier' => $byTier,
                'by_group' => $byGroup,
            ],
            'combat' => $combat,
            'market' => $market,
            'aging' => [
                'median_age_by_race' => $medianAgeByRace,
                'combat_deaths' => $totalCombatDeaths,
            ],
            'npc' => $npcSummary,
            'league' => [
                'yo_yo_count' => $yoYoCount,
            ],
        ];

        $payload['health'] = $this->healthEvaluator->evaluate($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     median_gold: int|null,
     *     median_combatant_age: float|null,
     *     combat_death_count: int,
     *     market_volume: int,
     *     crisis_count: int,
     *     mean_kill_score: float|null
     * }
     */
    public function extractIndexedScalars(array $payload): array
    {
        $gold = $payload['aggregates']['kingdom']['gold']['p50'] ?? null;
        $age = $payload['aggregates']['kingdom']['age']['p50'] ?? null;

        return [
            'median_gold' => is_numeric($gold) ? (int) round((float) $gold) : null,
            'median_combatant_age' => is_numeric($age) ? (float) $age : null,
            'combat_death_count' => (int) ($payload['aging']['combat_deaths'] ?? 0),
            'market_volume' => (int) ($payload['market']['volume_gold'] ?? 0),
            'crisis_count' => (int) ($payload['aggregates']['kingdom']['crisis_count'] ?? 0),
            'mean_kill_score' => isset($payload['combat']['mean_kill_score']) && is_numeric($payload['combat']['mean_kill_score'])
                ? (float) $payload['combat']['mean_kill_score']
                : null,
        ];
    }

    /**
     * @return array<int, list<Hero>>
     */
    private function heroesByTeam(Kingdom $kingdom): array
    {
        /** @var list<Hero> $heroes */
        $heroes = $this->em->createQueryBuilder()
            ->select('h')
            ->from(Hero::class, 'h')
            ->join('h.team', 't')
            ->where('t.kingdom = :kingdom')
            ->setParameter('kingdom', $kingdom)
            ->getQuery()
            ->getResult();

        $byTeam = [];
        foreach ($heroes as $hero) {
            $teamId = $hero->getTeam()->getId();
            if (null === $teamId) {
                continue;
            }
            $byTeam[$teamId][] = $hero;
        }

        return $byTeam;
    }

    /**
     * @return array<int, Headquarters>
     */
    private function hqByTeam(Kingdom $kingdom): array
    {
        /** @var \App\Repository\Headquarters\HeadquartersRepository $hqRepo */
        $hqRepo = $this->em->getRepository(Headquarters::class);
        /** @var list<Headquarters> $rows */
        $rows = $hqRepo->findByKingdom($kingdom);
        $map = [];
        foreach ($rows as $hq) {
            $teamId = $hq->getTeam()->getId();
            if (null !== $teamId) {
                $map[$teamId] = $hq;
            }
        }

        return $map;
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function ledgerByTeam(Kingdom $kingdom, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<FinancialRecord> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('f')
            ->from(FinancialRecord::class, 'f')
            ->join('f.team', 't')
            ->where('t.kingdom = :kingdom')
            ->andWhere('f.createdAt >= :from')
            ->andWhere('f.createdAt <= :to')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $byTeam = [];
        foreach ($rows as $row) {
            $teamId = $row->getTeam()->getId();
            if (null === $teamId) {
                continue;
            }
            $type = $row->getType()->value;
            $byTeam[$teamId][$type] = ($byTeam[$teamId][$type] ?? 0) + $row->getGoldChange();
        }

        return $byTeam;
    }

    /**
     * @return array<int, array<string, int>>
     */
    private function eventsByTeam(Kingdom $kingdom, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $types = [
            ChronicleEventType::SummonCompleted,
            ChronicleEventType::HeroPurchased,
            ChronicleEventType::HeroSold,
            ChronicleEventType::TrainerPurchased,
            ChronicleEventType::TrainerSold,
            ChronicleEventType::HeroDismissed,
            ChronicleEventType::TrainerDismissed,
            ChronicleEventType::FinancialCrisisState,
        ];

        /** @var list<TeamChronicle> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('c')
            ->from(TeamChronicle::class, 'c')
            ->join('c.team', 't')
            ->where('t.kingdom = :kingdom')
            ->andWhere('c.createdAt >= :from')
            ->andWhere('c.createdAt <= :to')
            ->andWhere('c.type IN (:types)')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('types', $types)
            ->getQuery()
            ->getResult();

        $byTeam = [];
        foreach ($rows as $row) {
            $teamId = $row->getTeam()->getId();
            if (null === $teamId) {
                continue;
            }
            $bucket = $byTeam[$teamId] ?? $this->emptyEvents();
            match ($row->getType()) {
                ChronicleEventType::SummonCompleted => ++$bucket['summons'],
                ChronicleEventType::HeroPurchased, ChronicleEventType::TrainerPurchased => ++$bucket['buys'],
                ChronicleEventType::HeroSold, ChronicleEventType::TrainerSold => ++$bucket['sells'],
                ChronicleEventType::HeroDismissed, ChronicleEventType::TrainerDismissed => ++$bucket['dismissals'],
                ChronicleEventType::FinancialCrisisState => ++$bucket['crisis_changes'],
                default => null,
            };
            $byTeam[$teamId] = $bucket;
        }

        return $byTeam;
    }

    /**
     * @return array<string, int>
     */
    private function emptyEvents(): array
    {
        return [
            'summons' => 0,
            'buys' => 0,
            'sells' => 0,
            'dismissals' => 0,
            'combat_deaths' => 0,
            'crisis_changes' => 0,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function combatDeathsByTeam(Kingdom $kingdom, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $fromDate = $from->setTime(0, 0, 0);
        $toDate = $to->setTime(0, 0, 0);

        /** @var list<GraveyardMemorial> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('m')
            ->from(GraveyardMemorial::class, 'm')
            ->join('m.team', 't')
            ->where('t.kingdom = :kingdom')
            ->andWhere('m.cause = :cause')
            ->andWhere('m.departedAt >= :from')
            ->andWhere('m.departedAt <= :to')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('cause', MemorialCause::CombatDeath)
            ->setParameter('from', $fromDate)
            ->setParameter('to', $toDate)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $teamId = $row->getTeam()->getId();
            if (null === $teamId) {
                continue;
            }
            $counts[$teamId] = ($counts[$teamId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array{transaction_count: int, volume_gold: int, fee_gold: int}
     */
    private function marketSummary(Kingdom $kingdom, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<MarketplaceTransaction> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select('tx')
            ->from(MarketplaceTransaction::class, 'tx')
            ->join('tx.buyerTeam', 'b')
            ->where('b.kingdom = :kingdom')
            ->andWhere('tx.createdAt >= :from')
            ->andWhere('tx.createdAt <= :to')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $volume = 0;
        $fees = 0;
        foreach ($rows as $row) {
            $volume += $row->getAmount();
            $fees += $row->getFeeAmount();
        }

        return [
            'transaction_count' => count($rows),
            'volume_gold' => $volume,
            'fee_gold' => $fees,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function combatSummary(LeagueSeason $season): array
    {
        /** @var list<LeagueFixture> $fixtures */
        $fixtures = $this->em->createQueryBuilder()
            ->select('f', 'b')
            ->from(LeagueFixture::class, 'f')
            ->leftJoin('f.battle', 'b')
            ->join('f.group', 'g')
            ->join('g.tier', 't')
            ->where('t.season = :season')
            ->andWhere('f.status = :status')
            ->setParameter('season', $season)
            ->setParameter('status', LeagueFixtureStatus::Completed)
            ->getQuery()
            ->getResult();

        $matches = count($fixtures);
        $killTotals = [];
        $homeWins = 0;
        $awayWins = 0;
        $draws = 0;
        $forfeits = 0;
        $round200 = 0;

        foreach ($fixtures as $fixture) {
            $battle = $fixture->getBattle();
            if (null === $battle) {
                continue;
            }
            $killTotals[] = $battle->getScoreA() + $battle->getScoreB();
            $log = $battle->getCombatLog();
            if (($log['simulator'] ?? null) === 'forfeit') {
                ++$forfeits;
            }
            if ($battle->getCurrentRound() >= 200) {
                ++$round200;
            }
            $result = $battle->getResult();
            if (BattleResult::WinA === $result) {
                ++$homeWins;
            } elseif (BattleResult::WinB === $result) {
                ++$awayWins;
            } elseif (BattleResult::Draw === $result) {
                ++$draws;
            }
        }

        $played = max(1, $matches);

        return [
            'matches' => $matches,
            'mean_kill_score' => SeasonStats::mean($killTotals),
            'round_200_share' => $matches > 0 ? $round200 / $played : null,
            'home_win_rate' => $matches > 0 ? $homeWins / $played : null,
            'away_win_rate' => $matches > 0 ? $awayWins / $played : null,
            'draw_rate' => $matches > 0 ? $draws / $played : null,
            'forfeit_rate' => $matches > 0 ? $forfeits / $played : null,
        ];
    }

    /**
     * @param list<Hero>               $heroes
     * @param array<string, list<int>> $ageByRace
     *
     * @return array<string, mixed>
     */
    private function rosterSnapshot(array $heroes, array &$ageByRace): array
    {
        $levels = [];
        $ovrs = [];
        $ages = [];
        $byRace = [];
        $combatReady = 0;
        $trainers = 0;
        $atMortality = 0;

        foreach ($heroes as $hero) {
            if (HeroStatus::Dead === $hero->getStatus() || $hero->getStatus()->isReserved()) {
                continue;
            }
            if (HeroRole::Trainer === $hero->getRole()) {
                ++$trainers;
                continue;
            }
            if (!$hero->isCombatant()) {
                continue;
            }
            if (HeroStatus::Available === $hero->getStatus()) {
                ++$combatReady;
            }
            $levels[] = $hero->getLevel();
            $ovrs[] = $hero->getBaseOvr();
            $age = $hero->getAge();
            $ages[] = $age;
            $race = $hero->getRace()->value;
            $byRace[$race] = ($byRace[$race] ?? 0) + 1;
            $ageByRace[$race][] = $age;
            if ($this->raceConfig->isAtOrAboveMortalityThreshold($hero->getRace(), $age)) {
                ++$atMortality;
            }
        }

        return [
            'combat_ready' => $combatReady,
            'trainers' => $trainers,
            'mean_level' => SeasonStats::mean($levels),
            'mean_ovr' => SeasonStats::mean($ovrs),
            'median_age' => SeasonStats::median($ages),
            'at_mortality' => $atMortality,
            'by_race' => $byRace,
        ];
    }

    /**
     * @return array{total_level: int, facilities: array<string, int>}
     */
    private function hqSnapshot(?Headquarters $hq): array
    {
        if (null === $hq) {
            return ['total_level' => 0, 'facilities' => []];
        }

        $facilities = [];
        foreach ($hq->getFacilities() as $facility) {
            $facilities[$facility->getType()->value] = $facility->getLevel();
        }

        return [
            'total_level' => $hq->getTotalLevel(),
            'facilities' => $facilities,
        ];
    }

    /**
     * @param list<array<string, mixed>> $teams
     *
     * @return array<string, array<string, mixed>>
     */
    private function groupAggregates(array $teams, string $key): array
    {
        $buckets = [];
        foreach ($teams as $team) {
            $label = (string) ($team[$key] ?? '');
            $buckets[$label][] = $team;
        }

        $out = [];
        foreach ($buckets as $label => $rows) {
            $gold = [];
            $ovr = [];
            $age = [];
            $level = [];
            $points = [];
            foreach ($rows as $row) {
                $gold[] = (int) $row['gold']['end'];
                $points[] = (int) $row['points'];
                if (is_numeric($row['roster']['mean_ovr'] ?? null)) {
                    $ovr[] = (float) $row['roster']['mean_ovr'];
                }
                if (is_numeric($row['roster']['median_age'] ?? null)) {
                    $age[] = (float) $row['roster']['median_age'];
                }
                if (is_numeric($row['roster']['mean_level'] ?? null)) {
                    $level[] = (float) $row['roster']['mean_level'];
                }
            }
            $out[$label] = [
                'team_count' => count($rows),
                'gold' => SeasonStats::spread($gold),
                'ovr' => SeasonStats::spread($ovr),
                'age' => SeasonStats::spread($age),
                'level' => SeasonStats::spread($level),
                'points' => SeasonStats::spread($points),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>>|array<string, mixed> $previousTeams
     *
     * @return array<string, mixed>|null
     */
    private function findPreviousTeam(array $previousTeams, int $teamId): ?array
    {
        foreach ($previousTeams as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['id'] ?? 0) === $teamId) {
                return $row;
            }
        }

        return null;
    }
}
