<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueStanding;
use App\Entity\League\SeasonReport;
use App\Entity\Team\Team;
use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleEventType;
use App\Enum\LeagueSeasonStatus;
use App\Repository\League\SeasonReportRepository;
use Doctrine\ORM\EntityManagerInterface;

class SeasonReportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SeasonReportRepository $reportRepository,
        private readonly SeasonReportBuilder $builder,
        private readonly SeasonYearbookPresenter $presenter,
        private readonly LeagueService $leagueService,
    ) {
    }

    /**
     * @param array<int, array{standing: LeagueStanding, position: int, status: string, gold?: int}>|null $teamDetails
     */
    public function recordForSeason(LeagueSeason $season, ?array $teamDetails = null, bool $force = false): SeasonReport
    {
        $existing = $this->reportRepository->findOneBySeason($season);
        if (null !== $existing && !$force) {
            return $existing;
        }

        $details = $teamDetails ?? $this->detailsFromArchive($season);
        $previous = $this->reportRepository->findOneByKingdomAndNumber(
            $season->getKingdom(),
            $season->getSeasonNumber() - 1,
        );
        $payload = $this->builder->build($season, $details, $previous?->getPayload());
        $scalars = $this->builder->extractIndexedScalars($payload);

        $report = $existing ?? new SeasonReport();
        $report->setKingdom($season->getKingdom());
        $report->setSeason($season);
        $report->setSeasonNumber($season->getSeasonNumber());
        $report->setGeneratedAt(new \DateTimeImmutable());
        $report->setMedianGold($scalars['median_gold']);
        $report->setMedianCombatantAge($scalars['median_combatant_age']);
        $report->setCombatDeathCount($scalars['combat_death_count']);
        $report->setMarketVolume($scalars['market_volume']);
        $report->setCrisisCount($scalars['crisis_count']);
        $report->setMeanKillScore($scalars['mean_kill_score']);
        $report->setPayload($payload);

        $this->em->persist($report);

        return $report;
    }

    public function findForSeason(LeagueSeason $season): ?SeasonReport
    {
        return $this->reportRepository->findOneBySeason($season);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function yearbookForTeam(LeagueSeason $season, ?Team $team): ?array
    {
        $report = $this->findForSeason($season);
        if (null === $report) {
            return null;
        }

        return $this->presenter->presentForPlayer($report->getPayload(), $team?->getId());
    }

    /**
     * Rebuild missing reports for completed seasons in a kingdom.
     *
     * @return list<SeasonReport>
     */
    public function backfillKingdom(Kingdom $kingdom, bool $force = false): array
    {
        /** @var list<LeagueSeason> $seasons */
        $seasons = $this->em->getRepository(LeagueSeason::class)->findBy(
            ['kingdom' => $kingdom, 'status' => LeagueSeasonStatus::Completed],
            ['seasonNumber' => 'ASC'],
        );

        $written = [];
        foreach ($seasons as $season) {
            $written[] = $this->recordForSeason($season, null, $force);
        }

        return $written;
    }

    /**
     * @return array<int, array{standing: LeagueStanding, position: int, status: string, gold?: int}>
     */
    private function detailsFromArchive(LeagueSeason $season): array
    {
        $groups = $this->leagueService->getGroupsForSeason($season);
        $details = [];
        foreach ($groups as $group) {
            $sorted = $this->leagueService->getSortedStandings($group);
            foreach ($sorted as $index => $standing) {
                $teamId = $standing->getTeam()->getId();
                if (null === $teamId) {
                    continue;
                }
                $chronicle = $this->seasonEndedChronicle($standing->getTeam(), $season->getSeasonNumber());
                $status = 'retained';
                $gold = 0;
                if (null !== $chronicle) {
                    $raw = $chronicle->getData()['status'] ?? null;
                    if (is_string($raw) && '' !== $raw) {
                        $status = $raw;
                    }
                    $gold = (int) ($chronicle->getData()['gold'] ?? 0);
                }
                $details[$teamId] = [
                    'standing' => $standing,
                    'position' => $index + 1,
                    'status' => $status,
                    'gold' => $gold,
                ];
            }
        }

        return $details;
    }

    private function seasonEndedChronicle(Team $team, int $seasonNumber): ?TeamChronicle
    {
        /** @var list<TeamChronicle> $events */
        $events = $this->em->getRepository(TeamChronicle::class)->findBy(
            ['team' => $team, 'type' => ChronicleEventType::SeasonEnded],
            ['createdAt' => 'DESC'],
        );
        foreach ($events as $event) {
            if ((int) ($event->getSubjectParams()['season'] ?? 0) === $seasonNumber) {
                return $event;
            }
        }

        return null;
    }
}
