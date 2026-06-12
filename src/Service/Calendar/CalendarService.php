<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Enum\TickType;
use App\Repository\Event\EventRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Repository\Training\TrainingQueueRepository;

class CalendarService
{
    public function __construct(
        private readonly TickScheduleCalculator $scheduleCalculator,
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly TrainingQueueRepository $trainingQueueRepository,
        private readonly LeagueFixtureRepository $leagueFixtureRepository,
        private readonly EventRepository $eventRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
    ) {
    }

    /**
     * Aggregates recurring ticks, world events, league matches, and team-specific queues.
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
                    $title = 'Daily Reset & Maintenance';
                    $description = 'System cleanup, quest expiration, hero aging reset';
                    $visibility = 'system_only';
                    break;
                case TickType::InactiveRegistrationCleanup:
                    $title = 'Inactive Registration Cleanup';
                    $description = 'Remove team assignments and delete unverified accounts older than 1 day';
                    $visibility = 'system_only';
                    break;
                case TickType::FatigueRecovery:
                    $title = 'Fatigue & Form Recovery';
                    $description = 'Passive restoration of hero fatigue and condition';
                    $visibility = 'system_only';
                    break;
                case TickType::WeeklyTraining:
                    $title = 'Weekly Training Process';
                    $description = 'Calculate queued hero stat increases and finalize jobs';
                    $visibility = 'public';
                    break;
                case TickType::LeagueMatch:
                    $title = 'League Match Tick';
                    $description = 'Trigger scheduled league fixtures simulation';
                    $visibility = 'public';
                    break;
                case TickType::SeasonTransition:
                    $title = 'Season Transition Tick';
                    $description = 'Resolve promotions, relegations, rewards, and initialize next season';
                    $visibility = 'public';
                    break;
                case TickType::WeeklyReset:
                    $title = 'Weekly Reset & Arena Payouts';
                    $description = 'Reset chambers and distribute weekly Arena seat revenues';
                    $visibility = 'public';
                    break;
                case TickType::RaceOptimization:
                    $title = 'Race Optimization Update';
                    $description = 'Apply pending race optimization settings and update lock states';
                    $visibility = 'public';
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
                'description' => sprintf('League Fixture - Group %s', $fixture->getGroup()->getGroupName()),
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

        // 3. Aggregating World Events
        $events = $this->eventRepository->findEventsInPeriod($kingdom, $start, $end);

        foreach ($events as $event) {
            $feed[] = [
                'id' => sprintf('world_event_%d', $event->getId()),
                'type' => 'world_event',
                'title' => $event->getName(),
                'description' => $event->getDescription(),
                'scheduledAt' => $event->getStartAt()->format(\DateTimeInterface::ATOM),
                'visibility' => 'public',
                'status' => $event->getStatus()->value,
                'metadata' => [
                    'eventId' => $event->getId(),
                    'endAt' => $event->getEndAt()->format(\DateTimeInterface::ATOM),
                    'eventType' => $event->getType()->value,
                ],
            ];
        }

        // 4. Aggregating Team-Specific Training Queue Completions
        if (null !== $teamId) {
            $jobs = $this->trainingQueueRepository->findJobsInPeriodForTeam($teamId, $start, $end);

            foreach ($jobs as $job) {
                $feed[] = [
                    'id' => sprintf('training_queue_%d', $job->getId()),
                    'type' => 'training_queue',
                    'title' => sprintf('Training complete: %s', $job->getHero()->getName()),
                    'description' => sprintf(
                        'Scheduled training for %s (%s)',
                        $job->getHero()->getName(),
                        $job->getTrainingType()->value.($job->getTargetAttribute() ? ': '.$job->getTargetAttribute() : '')
                    ),
                    'scheduledAt' => $job->getExecuteAt()->format(\DateTimeInterface::ATOM),
                    'visibility' => 'team_only',
                    'status' => $job->getStatus()->value,
                    'metadata' => [
                        'queueId' => $job->getId(),
                        'heroId' => $job->getHero()->getId(),
                        'trainingType' => $job->getTrainingType()->value,
                        'attribute' => $job->getTargetAttribute(),
                    ],
                ];
            }
        }

        // Sort entire feed chronologically
        usort($feed, static function (array $a, array $b): int {
            return strcmp($a['scheduledAt'], $b['scheduledAt']);
        });

        return $feed;
    }
}
