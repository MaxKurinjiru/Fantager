<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\RoyalTreasuryContributionSource;
use App\Repository\League\LeagueStandingRepository;
use App\Repository\Team\TeamRepository;

/**
 * Kingdom-level pool that collects fees and redistributes a capped share to teams.
 * Balance and allocation weights are never exposed to players.
 */
class RoyalTreasuryService
{
    /** Maximum share of the pool distributed each weekly reset (tunable later). */
    private const DISTRIBUTION_MAX_RATIO = 0.50;

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly LeagueStandingRepository $leagueStandingRepository,
        private readonly EconomyService $economyService,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function collectFee(
        Kingdom $kingdom,
        int $amount,
        RoyalTreasuryContributionSource $source,
        array $context = [],
    ): void {
        if ($amount <= 0) {
            return;
        }

        $kingdom->setRoyalTreasuryGold($kingdom->getRoyalTreasuryGold() + $amount);
    }

    /**
     * Distributes up to 50% of the treasury among all kingdom teams.
     *
     * @return array{distributed: int, teams_paid: int}
     */
    public function processWeeklyDistribution(Kingdom $kingdom): array
    {
        $balance = $kingdom->getRoyalTreasuryGold();
        if ($balance <= 0) {
            return ['distributed' => 0, 'teams_paid' => 0];
        }

        $pool = (int) floor($balance * self::DISTRIBUTION_MAX_RATIO);
        if ($pool <= 0) {
            return ['distributed' => 0, 'teams_paid' => 0];
        }

        /** @var list<Team> $teams */
        $teams = $this->teamRepository->findBy(['kingdom' => $kingdom]);
        if ([] === $teams) {
            return ['distributed' => 0, 'teams_paid' => 0];
        }

        /** @var array<int, LeagueStanding> $standingsByTeamId */
        $standingsByTeamId = $this->leagueStandingRepository->findIndexedByTeamForActiveSeason($kingdom);

        $weights = [];
        foreach ($teams as $team) {
            $teamId = $team->getId();
            if (null === $teamId) {
                continue;
            }

            $standing = $standingsByTeamId[$teamId] ?? null;
            $weights[$teamId] = $this->calculateTeamWeight($team, $standing);
        }

        if ([] === $weights) {
            return ['distributed' => 0, 'teams_paid' => 0];
        }

        $allocations = $this->allocateProportionally($pool, $weights);
        $teamsById = [];
        foreach ($teams as $team) {
            $teamId = $team->getId();
            if (null !== $teamId) {
                $teamsById[$teamId] = $team;
            }
        }

        $distributed = 0;
        $teamsPaid = 0;
        foreach ($allocations as $teamId => $amount) {
            if ($amount <= 0 || !isset($teamsById[$teamId])) {
                continue;
            }

            $this->economyService->addGold(
                $teamsById[$teamId],
                $amount,
                FinancialRecordType::KingdomReward,
                FinancialRecordActor::System,
            );
            $distributed += $amount;
            ++$teamsPaid;
        }

        if ($distributed > 0) {
            $kingdom->setRoyalTreasuryGold($balance - $distributed);
        }

        return ['distributed' => $distributed, 'teams_paid' => $teamsPaid];
    }

    private function calculateTeamWeight(Team $team, ?LeagueStanding $standing): float
    {
        $tierWeight = 1.0;
        $leaguePoints = 0;

        if (null !== $standing) {
            $tierWeight = $this->resolveTierWeight($standing->getGroup()->getTier()->getTierName());
            $leaguePoints = max(0, $standing->getPoints());
        }

        $reputationFactor = 1.0 + min(max(0, $team->getReputation()), 500) / 500.0;
        $leagueFactor = 1.0 + $leaguePoints / 100.0;

        return $tierWeight * $reputationFactor * $leagueFactor;
    }

    private function resolveTierWeight(string $tierName): float
    {
        if (preg_match('/t(\d+)/i', $tierName, $matches)) {
            $tierNumber = (int) $matches[1];

            return match ($tierNumber) {
                1 => 3.0,
                2 => 2.0,
                3 => 1.0,
                default => max(1.0, 4.0 - $tierNumber),
            };
        }

        return 1.0;
    }

    /**
     * @param array<int, float> $weights
     *
     * @return array<int, int>
     */
    private function allocateProportionally(int $pool, array $weights): array
    {
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0.0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $allocations = [];
        $fractions = [];
        $allocated = 0;

        foreach ($weights as $teamId => $weight) {
            $exact = ($pool * $weight) / $totalWeight;
            $floor = (int) floor($exact);
            $allocations[$teamId] = $floor;
            $allocated += $floor;
            $fractions[$teamId] = $exact - $floor;
        }

        $remaining = $pool - $allocated;
        if ($remaining > 0) {
            arsort($fractions, SORT_NUMERIC);
            foreach (array_keys($fractions) as $teamId) {
                if ($remaining <= 0) {
                    break;
                }

                ++$allocations[$teamId];
                --$remaining;
            }
        }

        return $allocations;
    }
}
