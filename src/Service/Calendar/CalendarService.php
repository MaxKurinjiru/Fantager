<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Enum\TickType;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\Translation\UserMessageTranslator;

class CalendarService
{
    public function __construct(
        private readonly TickScheduleCalculator $scheduleCalculator,
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly LeagueFixtureRepository $leagueFixtureRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
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
            if (TickType::LeagueMatch === $type) {
                continue;
            }

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
                    $visibility = 'public';
                    break;
                case TickType::SeasonTransition:
                    $title = $this->userMessages->trans('calendar.tick.season_transition_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.season_transition_desc', [], $locale);
                    $visibility = 'public';
                    break;
                case TickType::WeeklyReset:
                    $title = $this->userMessages->trans('calendar.tick.weekly_reset_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.weekly_reset_desc', [], $locale);
                    $visibility = 'public';
                    break;
                case TickType::RaceOptimization:
                    $title = $this->userMessages->trans('calendar.tick.race_optimization_title', [], $locale);
                    $description = $this->userMessages->trans('calendar.tick.race_optimization_desc', [], $locale);
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

        // Sort entire feed chronologically
        usort($feed, static function (array $a, array $b): int {
            return strcmp($a['scheduledAt'], $b['scheduledAt']);
        });

        return $feed;
    }
}
