<?php

declare(strict_types=1);

namespace App\Service\Headquarters;

use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\Team\FinancialRecordRepository;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Team\FanClubService;
use Doctrine\ORM\EntityManagerInterface;

class ArenaService
{
    /** Home-match payouts shown in the HQ analytics table, chart, and summary. */
    public const HISTORY_WINDOW = 18;

    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
        private readonly ArenaRevenueService $arenaRevenueService,
        private readonly FanClubService $fanClubService,
        private readonly FinancialRecordRepository $financialRecordRepository,
        private readonly LeagueFixtureRepository $fixtureRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getArenaStatus(Team $team): array
    {
        $bonuses = $this->arenaRevenueService->getFacilityBonuses($team);
        $capacity = $this->arenaRevenueService->getArenaCapacity($team);
        $showUpRate = $this->fanClubService->calculateShowUpRate($team);
        $targetFanBase = $this->fanClubService->calculateTargetFanBase($team);
        $ticketPrice = $team->getTicketPrice();
        $elasticity = $this->arenaRevenueService->calculatePriceElasticityMultiplier($ticketPrice);
        $arenaLevel = 0;

        /** @var Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null !== $hq) {
            foreach ($hq->getFacilities() as $facility) {
                if (FacilityType::Arena === $facility->getType()) {
                    $arenaLevel = $facility->getLevel();
                    break;
                }
            }
        }

        $nextHomeFixture = $this->fixtureRepository->findNextHomeFixtureForTeam($team);
        $nextHomeMatch = null;
        $projectedAttendance = null;
        $projectedRevenue = null;
        $projectedHomeAttendees = null;
        $projectedAwayAttendees = null;
        $projections = [];

        if (null !== $nextHomeFixture) {
            $awayTeam = $nextHomeFixture->getAwayTeam();
            $matchReport = $this->arenaRevenueService->calculateMatchRevenue($team, $awayTeam);
            $projections = $this->arenaRevenueService->generatePriceRevenueProjections($team, $awayTeam);

            $nextHomeMatch = [
                'id' => (int) $nextHomeFixture->getId(),
                'opponent' => $awayTeam->getName(),
                'opponent_id' => $awayTeam->getId(),
                'scheduled_at' => $nextHomeFixture->getScheduledAt()->format(\DateTimeInterface::ATOM),
                'opponent_fan_base' => $awayTeam->getFanBase(),
                'opponent_show_up_rate' => $matchReport['away_show_up_rate'],
            ];
            $projectedAttendance = $matchReport['attendance'];
            $projectedRevenue = $matchReport['gold_earned'];
            $projectedHomeAttendees = $matchReport['home_attendees'];
            $projectedAwayAttendees = $matchReport['away_attendees'];
        }

        $history = $this->buildRevenueHistory($team);

        return [
            'arena_level' => $arenaLevel,
            'seating_capacity' => $capacity,
            'ticket_price' => $ticketPrice,
            'elasticity_multiplier' => round($elasticity, 3),
            'fan_base' => $team->getFanBase(),
            'target_fan_base' => $targetFanBase,
            'show_up_rate' => round($showUpRate, 3),
            'fan_appeal' => round($showUpRate, 3),
            'effective_show_up_rate' => round($showUpRate * $elasticity, 3),
            'reputation' => $team->getReputation(),
            'morale' => $team->getMorale(),
            'chemistry' => $team->getChemistry(),
            'ticket_revenue_bonus_pct' => $bonuses['ticket_revenue_pct'],
            'arena_capacity_bonus_pct' => $bonuses['arena_capacity_pct'],
            'projected_attendance' => $projectedAttendance,
            'projected_revenue' => $projectedRevenue,
            'projected_home_attendees' => $projectedHomeAttendees,
            'projected_away_attendees' => $projectedAwayAttendees,
            'next_home_match' => $nextHomeMatch,
            'projections' => $projections,
            'recent_revenue' => $history['recent_revenue'],
            'analytics' => $history['analytics'],
        ];
    }

    /**
     * Last HISTORY_WINDOW home-match payouts plus summary totals for the same window.
     *
     * @return array{
     *     recent_revenue: list<array{
     *         amount: int,
     *         created_at: string,
     *         attendance: int|null,
     *         capacity: int|null,
     *         occupancy_pct: float|null,
     *         opponent_name: string|null,
     *         ticket_price: int|null
     *     }>,
     *     analytics: array{
     *         window: int,
     *         match_count: int,
     *         total_gold: int,
     *         average_attendance: int|null
     *     }
     * }
     */
    public function buildRevenueHistory(Team $team): array
    {
        $records = $this->financialRecordRepository->findRecentByTeamAndType(
            $team,
            FinancialRecordType::ArenaRevenue->value,
            self::HISTORY_WINDOW
        );

        $recentRevenue = [];
        foreach ($records as $record) {
            $recentRevenue[] = $this->mapRevenueRow($record);
        }

        $attendanceValues = [];
        foreach ($recentRevenue as $row) {
            if (null !== $row['attendance']) {
                $attendanceValues[] = $row['attendance'];
            }
        }

        $averageAttendance = [] === $attendanceValues
            ? null
            : (int) round(array_sum($attendanceValues) / count($attendanceValues));

        return [
            'recent_revenue' => $recentRevenue,
            'analytics' => [
                'window' => self::HISTORY_WINDOW,
                'match_count' => count($recentRevenue),
                'total_gold' => (int) array_sum(array_column($recentRevenue, 'amount')),
                'average_attendance' => $averageAttendance,
            ],
        ];
    }

    /**
     * @return array{
     *     amount: int,
     *     created_at: string,
     *     attendance: int|null,
     *     capacity: int|null,
     *     occupancy_pct: float|null,
     *     opponent_name: string|null,
     *     ticket_price: int|null
     * }
     */
    private function mapRevenueRow(FinancialRecord $record): array
    {
        $context = $record->getContext();
        $attendance = isset($context['attendance']) ? (int) $context['attendance'] : null;
        $rowCapacity = isset($context['capacity']) ? (int) $context['capacity'] : null;
        $occupancyPct = null;
        if (null !== $attendance && null !== $rowCapacity && $rowCapacity > 0) {
            $occupancyPct = round(($attendance / $rowCapacity) * 100.0, 1);
        }

        $opponentName = $context['away_team_name'] ?? null;

        return [
            'amount' => $record->getGoldChange(),
            'created_at' => $record->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'attendance' => $attendance,
            'capacity' => $rowCapacity,
            'occupancy_pct' => $occupancyPct,
            'opponent_name' => is_string($opponentName) ? $opponentName : null,
            'ticket_price' => isset($context['ticket_price']) ? (int) $context['ticket_price'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateTicketPrice(Team $team, int $price): array
    {
        $clampedPrice = max(1, min(50, $price));
        $team->setTicketPrice($clampedPrice);
        $this->entityManager->flush();

        return $this->getArenaStatus($team);
    }
}
