<?php

declare(strict_types=1);

namespace App\Service\Economy;

use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Team\TeamRepository;

class ArenaRevenueService
{
    private const BASE_SEATING_CAPACITY = 500;
    private const BASE_TICKET_PRICE = 5;

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly HeadquartersRepository $hqRepository,
        private readonly EconomyService $economyService,
    ) {
    }

    /**
     * Distribute weekly arena ticket revenue to all active non-NPC teams.
     *
     * @return array<int, array{team_id: int, team_name: string, capacity: int, attendance: int, gold_earned: int}>
     */
    public function distributeWeeklyRevenue(?\App\Entity\Kingdom\Kingdom $kingdom = null): array
    {
        $criteria = ['isNpc' => false];
        if (null !== $kingdom) {
            $criteria['kingdom'] = $kingdom;
        }

        /** @var list<Team> $teams */
        $teams = $this->teamRepository->findBy($criteria);
        $results = [];

        foreach ($teams as $team) {
            $report = $this->processTeamRevenue($team);
            if ($report['gold_earned'] > 0) {
                $this->economyService->addGold(
                    $team,
                    $report['gold_earned'],
                    FinancialRecordType::ArenaRevenue,
                    FinancialRecordActor::System,
                    [
                        'capacity' => $report['capacity'],
                        'attendance' => $report['attendance'],
                        'reputation' => $team->getReputation(),
                    ]
                );
            }
            $results[] = $report;
        }

        $this->economyService->flush();

        return $results;
    }

    /**
     * Calculate arena capacity, attendance, and revenue for a team.
     *
     * @return array{team_id: int, team_name: string, capacity: int, attendance: int, gold_earned: int}
     */
    public function calculateTeamRevenue(Team $team): array
    {
        /** @var Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);

        $arenaCapacityBonusPct = 0.0;
        $ticketRevenueBonusPct = 0.0;
        $goldIncomeBonusPct = 0.0;

        if (null !== $hq) {
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

        // 1. Calculate Seating Capacity
        $capacity = (int) round(self::BASE_SEATING_CAPACITY * (1.0 + $arenaCapacityBonusPct / 100.0));

        // 2. Calculate Attendance based on Reputation
        $reputation = $team->getReputation();
        $attendanceRatio = 0.4 + 0.6 * ($reputation / ($reputation + 100.0));
        $attendance = (int) round($capacity * $attendanceRatio);

        // Cap attendance at capacity
        if ($attendance > $capacity) {
            $attendance = $capacity;
        }

        // 3. Base Revenue
        $baseRevenue = $attendance * self::BASE_TICKET_PRICE;

        // 4. Adjusted Revenue (apply Arena and Treasury multipliers)
        $adjustedRevenue = $baseRevenue * (1.0 + $ticketRevenueBonusPct / 100.0) * (1.0 + $goldIncomeBonusPct / 100.0);
        $goldEarned = (int) round($adjustedRevenue);

        return [
            'team_id' => (int) $team->getId(),
            'team_name' => $team->getName(),
            'capacity' => $capacity,
            'attendance' => $attendance,
            'gold_earned' => $goldEarned,
        ];
    }

    /**
     * @return array{team_id: int, team_name: string, capacity: int, attendance: int, gold_earned: int}
     */
    private function processTeamRevenue(Team $team): array
    {
        return $this->calculateTeamRevenue($team);
    }
}
