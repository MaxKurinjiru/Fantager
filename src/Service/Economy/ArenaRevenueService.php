<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Service\Team\FanClubService;

class ArenaRevenueService
{
    public const BASE_SEATING_CAPACITY = 500;
    public const TICKET_PRICE = 5;
    public const DEFAULT_TICKET_PRICE = 5;

    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
        private readonly LeagueFixtureRepository $fixtureRepository,
        private readonly EconomyService $economyService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly FanClubService $fanClubService,
    ) {
    }

    /**
     * Calculate demand elasticity multiplier based on ticket price relative to default base price (5 gold).
     * Elasticity formula: (Default Price / Ticket Price)^0.75 clamped to range [0.10, 2.00].
     */
    public function calculatePriceElasticityMultiplier(int $ticketPrice): float
    {
        $price = max(1, $ticketPrice);
        $multiplier = (self::DEFAULT_TICKET_PRICE / $price) ** 0.75;

        return max(0.10, min(2.00, $multiplier));
    }

    /**
     * Process arena ticket revenue for league fixtures scheduled at the given tick time.
     * Revenue is paid to the home team only.
     *
     * @return list<array<string, mixed>>
     */
    public function processLeagueMatchTick(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): array
    {
        $fixtures = $this->fixtureRepository->findScheduledFixturesAtTime($kingdom, $scheduledAt);
        $results = [];

        foreach ($fixtures as $fixture) {
            $results[] = $this->payFixtureRevenue($fixture);
        }

        if ([] !== $results) {
            $this->economyService->flush();
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function payFixtureRevenue(LeagueFixture $fixture): array
    {
        $homeTeam = $fixture->getHomeTeam();
        $awayTeam = $fixture->getAwayTeam();
        $report = $this->calculateMatchRevenue($homeTeam, $awayTeam);
        $report['fixture_id'] = $fixture->getId();

        if ($report['gold_earned'] > 0) {
            $this->economyService->addGold(
                $homeTeam,
                $report['gold_earned'],
                FinancialRecordType::ArenaRevenue,
                FinancialRecordActor::System,
                [
                    'fixture_id' => $fixture->getId(),
                    'away_team_id' => $awayTeam->getId(),
                    'away_team_name' => $awayTeam->getName(),
                    'capacity' => $report['capacity'],
                    'attendance' => $report['attendance'],
                    'home_attendees' => $report['home_attendees'],
                    'away_attendees' => $report['away_attendees'],
                    'ticket_price' => $report['ticket_price'],
                    'elasticity_multiplier' => $report['elasticity_multiplier'],
                ]
            );
        }

        return $report;
    }

    /**
     * Backward-compatible alias for FanClubService::calculateShowUpRate().
     */
    public function calculateFanAppeal(Team $team): float
    {
        return $this->fanClubService->calculateShowUpRate($team);
    }

    public function getArenaCapacity(Team $homeTeam): int
    {
        $bonuses = $this->getFacilityBonuses($homeTeam);

        return (int) round(self::BASE_SEATING_CAPACITY * (1.0 + $bonuses['arena_capacity_pct'] / 100.0));
    }

    /**
     * @return array{
     *     home_team_id: int|null,
     *     away_team_id: int|null,
     *     capacity: int,
     *     attendance: int,
     *     home_attendees: int,
     *     away_attendees: int,
     *     ticket_price: int,
     *     elasticity_multiplier: float,
     *     gold_earned: int,
     *     home_fan_base: int,
     *     away_fan_base: int,
     *     home_show_up_rate: float,
     *     away_show_up_rate: float,
     *     home_fan_appeal: float,
     *     away_fan_appeal: float
     * }
     */
    public function calculateMatchRevenue(Team $homeTeam, Team $awayTeam, ?int $overrideTicketPrice = null): array
    {
        $ticketPrice = $overrideTicketPrice ?? $homeTeam->getTicketPrice();
        if ($ticketPrice < 1) {
            $ticketPrice = self::DEFAULT_TICKET_PRICE;
        }

        $elasticity = $this->calculatePriceElasticityMultiplier($ticketPrice);
        $capacity = $this->getArenaCapacity($homeTeam);
        $attendanceData = $this->fanClubService->calculateMatchAttendance($homeTeam, $awayTeam, $capacity, $elasticity);

        $bonuses = $this->getFacilityBonuses($homeTeam);
        $baseRevenue = $attendanceData['attendance'] * $ticketPrice;
        $speed = 1.0;
        try {
            $kingdom = $homeTeam->getKingdom();
            $speed = (float) $kingdom->getGameSpeed();
        } catch (\Throwable) {
            $speed = 1.0;
        }
        if ($speed <= 0.0) {
            $speed = 1.0;
        }

        $goldEarned = (int) round(
            $baseRevenue
            * (1.0 + $bonuses['ticket_revenue_pct'] / 100.0)
            * (1.0 + $bonuses['gold_income_pct'] / 100.0)
            * $speed
        );

        return [
            'home_team_id' => $homeTeam->getId(),
            'away_team_id' => $awayTeam->getId(),
            'capacity' => $capacity,
            'attendance' => $attendanceData['attendance'],
            'home_attendees' => $attendanceData['home_attendees'],
            'away_attendees' => $attendanceData['away_attendees'],
            'ticket_price' => $ticketPrice,
            'elasticity_multiplier' => round($elasticity, 3),
            'gold_earned' => $goldEarned,
            'home_fan_base' => $homeTeam->getFanBase(),
            'away_fan_base' => $awayTeam->getFanBase(),
            'home_show_up_rate' => $attendanceData['home_show_up_rate'],
            'away_show_up_rate' => $attendanceData['away_show_up_rate'],
            'home_fan_appeal' => $attendanceData['home_show_up_rate'],
            'away_fan_appeal' => $attendanceData['away_show_up_rate'],
        ];
    }

    /**
     * Generate revenue and attendance projections across a scale of ticket prices.
     *
     * @return list<array{ticket_price: int, attendance: int, capacity_pct: float, gold_earned: int, elasticity: float}>
     */
    public function generatePriceRevenueProjections(Team $homeTeam, Team $awayTeam): array
    {
        $testPrices = [1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20, 25, 30, 40, 50];
        $projections = [];

        foreach ($testPrices as $price) {
            $report = $this->calculateMatchRevenue($homeTeam, $awayTeam, $price);
            $capacity = max(1, $report['capacity']);
            $capacityPct = round(($report['attendance'] / $capacity) * 100.0, 1);

            $projections[] = [
                'ticket_price' => $price,
                'attendance' => $report['attendance'],
                'capacity_pct' => $capacityPct,
                'gold_earned' => $report['gold_earned'],
                'elasticity' => $report['elasticity_multiplier'],
            ];
        }

        return $projections;
    }

    /**
     * @return array{arena_capacity_pct: float, ticket_revenue_pct: float, gold_income_pct: float}
     */
    public function getFacilityBonuses(Team $team): array
    {
        /** @var \App\Entity\Headquarters\Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        $arenaCapacityBonusPct = 0.0;
        $ticketRevenueBonusPct = 0.0;
        $goldIncomeBonusPct = 0.0;

        if (null !== $hq) {
            if ($this->financialCrisisService->areHqBonusesActive($team)) {
                foreach ($hq->getFacilities() as $facility) {
                    if (FacilityType::Arena === $facility->getType()) {
                        $bonuses = $facility->getPassiveBonuses();
                        $arenaCapacityBonusPct = (float) ($bonuses['arena_capacity'] ?? 0.0);
                        $ticketRevenueBonusPct = (float) ($bonuses['ticket_revenue_pct'] ?? 0.0);
                    } elseif (FacilityType::Treasury === $facility->getType()) {
                        $bonuses = $facility->getPassiveBonuses();
                        $goldIncomeBonusPct = (float) ($bonuses['gold_income_pct'] ?? 0.0);
                    }
                }
            }
        }

        return [
            'arena_capacity_pct' => $arenaCapacityBonusPct,
            'ticket_revenue_pct' => $ticketRevenueBonusPct,
            'gold_income_pct' => $goldIncomeBonusPct,
        ];
    }
}
