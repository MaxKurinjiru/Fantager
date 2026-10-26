<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Repository\League\LeagueFixtureRepository;
use App\Service\Calendar\TickClock;

class CashflowForecastService
{
    public function __construct(
        private readonly LeagueFixtureRepository $fixtureRepository,
        private readonly ArenaRevenueService $arenaRevenueService,
        private readonly TickClock $tickClock,
    ) {
    }

    /**
     * Conservative weekly cashflow through the next Sunday weekly reset.
     * Arena gold is projected for remaining scheduled home matches. Kingdom treasury
     * share is omitted (balance is not player-facing) even though live ticks pay it
     * before Sunday costs. Marketplace and other player actions are omitted.
     *
     * @param array<string, mixed> $crisis Crisis status from FinancialCrisisService::getStatus()
     *
     * @return array{
     *     weekly_reset_at: \DateTimeImmutable,
     *     estimated_arena: int,
     *     home_match_count: int,
     *     home_matches: list<array{opponent_name: string, scheduled_at: \DateTimeImmutable, projected_gold: int}>,
     *     weekly_payroll: int,
     *     weekly_maintenance: int,
     *     weekly_expenses: int,
     *     estimated_net: int,
     *     gold_after_tick: int,
     *     debt_after_tick: int,
     *     covers_weekly_costs: bool
     * }
     */
    public function buildForecast(Team $team, array $crisis, ?\DateTimeImmutable $now = null): array
    {
        $now ??= $this->tickClock->getCurrentTime();
        $timezone = new \DateTimeZone($team->getKingdom()->getTimezone());
        $weeklyResetAt = $this->resolveNextWeeklyReset($timezone, $now);

        $fixtures = $this->fixtureRepository->findScheduledHomeFixturesUntil($team, $weeklyResetAt);
        $homeMatches = [];
        $estimatedArena = 0;

        foreach ($fixtures as $fixture) {
            $match = $this->mapHomeMatch($team, $fixture);
            $homeMatches[] = $match;
            $estimatedArena += $match['projected_gold'];
        }

        $gold = (int) $crisis['gold'];
        $debt = (int) $crisis['unpaid_debt'];
        $payroll = (int) $crisis['weekly_payroll'];
        $maintenance = (int) $crisis['weekly_maintenance'];
        $expenses = (int) $crisis['weekly_expenses'];

        $goldAtReset = $gold + $estimatedArena;
        $paidCosts = min($goldAtReset, $expenses);
        $goldAfterCosts = $goldAtReset - $paidCosts;
        $debtAfterCosts = $debt + ($expenses - $paidCosts);
        $repay = min($goldAfterCosts, $debtAfterCosts);

        return [
            'weekly_reset_at' => $weeklyResetAt,
            'estimated_arena' => $estimatedArena,
            'home_match_count' => count($homeMatches),
            'home_matches' => $homeMatches,
            'weekly_payroll' => $payroll,
            'weekly_maintenance' => $maintenance,
            'weekly_expenses' => $expenses,
            'estimated_net' => $estimatedArena - $expenses,
            'gold_after_tick' => $goldAfterCosts - $repay,
            'debt_after_tick' => $debtAfterCosts - $repay,
            'covers_weekly_costs' => $goldAtReset >= $expenses,
        ];
    }

    public function resolveNextWeeklyReset(\DateTimeZone $timezone, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $local = $now->setTimezone($timezone);
        $dayOfWeek = (int) $local->format('w');
        $daysUntilSunday = 0 === $dayOfWeek ? 0 : 7 - $dayOfWeek;
        $candidate = $local->setTime(23, 59, 0)->modify(sprintf('+%d days', $daysUntilSunday));

        if ($local >= $candidate) {
            return $candidate->modify('+7 days');
        }

        return $candidate;
    }

    /**
     * @return array{opponent_name: string, scheduled_at: \DateTimeImmutable, projected_gold: int}
     */
    private function mapHomeMatch(Team $team, LeagueFixture $fixture): array
    {
        $report = $this->arenaRevenueService->calculateMatchRevenue($team, $fixture->getAwayTeam());

        return [
            'opponent_name' => $fixture->getAwayTeam()->getName(),
            'scheduled_at' => $fixture->getScheduledAt(),
            'projected_gold' => (int) $report['gold_earned'],
        ];
    }
}
