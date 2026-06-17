<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Enum\TickType;
use App\Repository\Hero\HeroRepository;
use App\Repository\Hero\HeroTrainingHistoryRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\Translation\UserMessageTranslator;

class CalendarService
{
    public function __construct(
        private readonly TickScheduleCalculator $scheduleCalculator,
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly HeroTrainingHistoryRepository $heroTrainingHistoryRepository,
        private readonly LeagueFixtureRepository $leagueFixtureRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
        private readonly HeroRepository $heroRepository,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    /**
     * Aggregates recurring ticks, league matches, and team-specific queues.
     *
     * @return list<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     description: string,
     *     scheduledAt: string,
     *     visibility: string,
     *     status: string,
     *     metadata: array<string, mixed>
     * }>
     */
    public function getCalendarFeed(
        Kingdom $kingdom,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $teamId = null,
        ?string $locale = null,
    ): array {
        /** @var list<array{id: string, type: string, title: string, description: string, scheduledAt: string, visibility: string, status: string, metadata: array<string, mixed>}> $feed */
        $feed = [];

        // 1. Aggregating Recurring Ticks
        /** @var \App\Entity\League\LeagueSeason|null $season */
        $season = $this->seasonRepository->findOneBy([
            'kingdom' => $kingdom,
            'status' => \App\Enum\LeagueSeasonStatus::Active,
        ]);
        if (null === $season) {
            /** @var \App\Entity\League\LeagueSeason|null $season */
            $season = $this->seasonRepository->findOneBy(['kingdom' => $kingdom], ['seasonNumber' => 'DESC']);
        }
        $occurrences = $this->scheduleCalculator->generateOccurrences(
            $start,
            $end,
            $kingdom->getTimezone(),
            $season?->getStartDate()
        );
        foreach ($occurrences as $occurrence) {
            $type = $occurrence['type'];
            $time = $occurrence['time'];
            $timeStr = $time->format(\DateTimeInterface::ATOM);

            // Fetch actual execution state if any log exists
            /** @var \App\Entity\Kingdom\KingdomTickLog|null $log */
            $log = $this->tickLogRepository->findOneBy([
                'kingdom' => $kingdom,
                'tickType' => $type,
                'scheduledAt' => $time,
            ]);

            $status = $log ? $log->getStatus() : 'scheduled';

            // Determine visibility & labels
            $visibility = 'public';
            $title = $type->value;
            $description = '';

            switch ($type) {
                case TickType::DailyReset:
                    $title = $this->userMessages->trans('calendar.tick.daily_reset_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.daily_reset_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
                case TickType::InactiveRegistrationCleanup:
                    $title = $this->userMessages->trans('calendar.tick.inactive_registration_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.inactive_registration_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
                case TickType::InactivePlayerCleanup:
                    $title = $this->userMessages->trans('calendar.tick.inactive_player_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.inactive_player_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
                case TickType::FatigueRecovery:
                    $title = $this->userMessages->trans('calendar.tick.fatigue_recovery_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.fatigue_recovery_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
                case TickType::WeeklyTraining:
                    $title = $this->userMessages->trans('calendar.tick.weekly_training_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.weekly_training_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
                case TickType::LeagueMatch:
                    $title = $this->userMessages->trans('calendar.tick.league_match_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.league_match_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
                case TickType::SeasonTransition:
                    $title = $this->userMessages->trans('calendar.tick.season_transition_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.season_transition_desc', [], $locale);
                    $visibility = 'public';
                    break;
                case TickType::WeeklyReset:
                    $title = $this->userMessages->trans('calendar.tick.weekly_reset_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.weekly_reset_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
                case TickType::RaceOptimization:
                    $title = $this->userMessages->trans('calendar.tick.race_optimization_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.race_optimization_desc', [], $locale);
                    $visibility = 'system_only';
                    break;
            }

            $feed[] = [
                'id' => sprintf('tick_%s_%s', $type->value, $time->format('YmdHis')),
                'type' => 'system_tick',
                'title' => $title,
                'description' => $description,
                'scheduledAt' => $timeStr,
                'visibility' => $visibility,
                'status' => $status,
                'metadata' => [
                    'tickType' => $type->value,
                ],
            ];
        }

        // 2. Aggregating League Fixtures
        $fixtures = $this->leagueFixtureRepository->findFixturesInPeriod($kingdom, $start, $end);

        foreach ($fixtures as $fixture) {
            $isOwnMatch = false;
            $homeId = $fixture->getHomeTeam()->getId();
            $awayId = $fixture->getAwayTeam()->getId();

            if (null !== $teamId && ($homeId === $teamId || $awayId === $teamId)) {
                $isOwnMatch = true;
            }

            $feed[] = [
                'id' => sprintf('league_match_%d', $fixture->getId()),
                'type' => 'league_match',
                'title' => sprintf('%s vs %s', $fixture->getHomeTeam()->getName(), $fixture->getAwayTeam()->getName()),
                'description' => $this->userMessages->trans('calendar.league_fixture_desc', ['%group%' => $fixture->getGroup()->getGroupName()], $locale),
                'scheduledAt' => $fixture->getScheduledAt()->format(\DateTimeInterface::ATOM),
                'visibility' => $isOwnMatch ? 'team_only' : 'public',
                'status' => $fixture->getStatus()->value,
                'metadata' => [
                    'fixtureId' => $fixture->getId(),
                    'homeTeam' => [
                        'id' => $homeId,
                        'name' => $fixture->getHomeTeam()->getName(),
                    ],
                    'awayTeam' => [
                        'id' => $awayId,
                        'name' => $fixture->getAwayTeam()->getName(),
                    ],
                    'groupName' => $fixture->getGroup()->getGroupName(),
                ],
            ];
        }

        // 3. Aggregating team training history completions
        if (null !== $teamId) {
            $historyEntries = $this->heroTrainingHistoryRepository->findInPeriodForTeam($teamId, $start, $end);

            foreach ($historyEntries as $entry) {
                $feed[] = [
                    'id' => sprintf('hero_training_history_%d', $entry->getId()),
                    'type' => 'hero_training_history',
                    'title' => $this->userMessages->trans('calendar.training_complete_title', ['%hero%' => $entry->getHero()->getName()], $locale),
                    'description' => sprintf(
                        'Scheduled training for %s (%s)',
                        $entry->getHero()->getName(),
                        $entry->getTrainingType()->value.($entry->getTargetAttribute() ? ': '.$entry->getTargetAttribute() : '')
                    ),
                    'scheduledAt' => $entry->getCompletedAt()->format(\DateTimeInterface::ATOM),
                    'visibility' => 'team_only',
                    'status' => 'completed',
                    'metadata' => [
                        'historyId' => $entry->getId(),
                        'heroId' => $entry->getHero()->getId(),
                        'trainingType' => $entry->getTrainingType()->value,
                        'attribute' => $entry->getTargetAttribute(),
                    ],
                ];
            }

            // Append virtual upcoming completed entries for currently assigned heroes
            $activeTrainees = $this->heroRepository->createQueryBuilder('h')
                ->where('h.team = :teamId')
                ->andWhere('h.trainer IS NOT NULL')
                ->setParameter('teamId', $teamId)
                ->getQuery()
                ->getResult();

            foreach ($activeTrainees as $hero) {
                /** @var \App\Entity\Hero\Hero $hero */
                $trainer = $hero->getTrainer();
                if (null === $trainer || null === $trainer->getTrainingType()) {
                    continue;
                }

                // For each WeeklyTraining tick in the period, add a scheduled entry
                foreach ($occurrences as $occ) {
                    if (TickType::WeeklyTraining === $occ['type']) {
                        $occTime = $occ['time'];
                        $feed[] = [
                            'id' => sprintf('active_training_%d_%s', $hero->getId(), $occTime->format('YmdHis')),
                            'type' => 'hero_training_history',
                            'title' => $this->userMessages->trans('calendar.training_complete_title', ['%hero%' => $hero->getName()], $locale),
                            'description' => sprintf(
                                'Scheduled training for %s (%s)',
                                $hero->getName(),
                                $trainer->getTrainingType()->value.($trainer->getTargetAttribute() ? ': '.$trainer->getTargetAttribute() : '')
                            ),
                            'scheduledAt' => $occTime->format(\DateTimeInterface::ATOM),
                            'visibility' => 'team_only',
                            'status' => 'scheduled',
                            'metadata' => [
                                'historyId' => null,
                                'heroId' => $hero->getId(),
                                'trainingType' => $trainer->getTrainingType()->value,
                                'attribute' => $trainer->getTargetAttribute(),
                            ],
                        ];
                    }
                }
            }
        }

        // Sort entire feed chronologically
        usort($feed, static function (array $a, array $b): int {
            return strcmp($a['scheduledAt'], $b['scheduledAt']);
        });

        return $feed;
    }
}
