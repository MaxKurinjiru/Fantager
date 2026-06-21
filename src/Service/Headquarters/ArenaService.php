<?php

declare(strict_types=1);

namespace App\Service\Headquarters;

use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\Team\FinancialRecordRepository;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Team\FanClubService;

class ArenaService
{
    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
        private readonly ArenaRevenueService $arenaRevenueService,
        private readonly FanClubService $fanClubService,
        private readonly FinancialRecordRepository $financialRecordRepository,
        private readonly LeagueFixtureRepository $fixtureRepository,
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

        if (null !== $nextHomeFixture) {
            $awayTeam = $nextHomeFixture->getAwayTeam();
            $matchReport = $this->arenaRevenueService->calculateMatchRevenue($team, $awayTeam);

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

        $records = $this->financialRecordRepository->findRecentByTeamAndType(
            $team,
            FinancialRecordType::ArenaRevenue->value,
            8
        );
        $recentRevenue = [];
        foreach ($records as $record) {
            $context = $record->getContext();
            $recentRevenue[] = [
                'amount' => $record->getGoldChange(),
                'created_at' => $record->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'attendance' => isset($context['attendance']) ? (int) $context['attendance'] : null,
                'capacity' => isset($context['capacity']) ? (int) $context['capacity'] : null,
                'opponent_name' => $context['away_team_name'] ?? null,
            ];
        }

        return [
            'arena_level' => $arenaLevel,
            'seating_capacity' => $capacity,
            'ticket_price' => ArenaRevenueService::TICKET_PRICE,
            'fan_base' => $team->getFanBase(),
            'target_fan_base' => $targetFanBase,
            'show_up_rate' => round($showUpRate, 3),
            'fan_appeal' => round($showUpRate, 3),
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
            'recent_revenue' => $recentRevenue,
        ];
    }
}
