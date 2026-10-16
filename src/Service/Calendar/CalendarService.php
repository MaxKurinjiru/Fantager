<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Enum\TickType;
use App\Repository\FriendlyMatch\FriendlyMatchRepository;
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
        private readonly FriendlyMatchRepository $friendlyMatchRepository,
    ) {
    }

    /**
     * Aggregates recurring ticks (excluding league/friendly tick types), league fixtures, and the viewer's friendly matches.
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
            if (TickType::LeagueMatch === $type || TickType::FriendlyMatch === $type) {
                // League matches and friendly matches are added separately from their repositories
                continue;
            }

            $time = $occurrence['time'];
            $timeStr = $time->format(\DateTimeInterface::ATOM);

            $status = $this->resolveTickStatus($kingdom, $type, $time, $teamId);

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

        // 3. Friendly Matches — only for participating teams (team_only visibility)
        if (null !== $teamId) {
            $friendlyMatches = $this->friendlyMatchRepository->findForTeamInPeriod($teamId, $start, $end);
            foreach ($friendlyMatches as $match) {
                $homeId = $match->getHomeTeam()->getId();
                $awayId = $match->getAwayTeam()->getId();

                $feed[] = [
                    'id' => sprintf('friendly_match_%d', $match->getId()),
                    'type' => 'friendly_match',
                    'title' => sprintf('%s vs %s', $match->getHomeTeam()->getName(), $match->getAwayTeam()->getName()),
                    'description' => $this->userMessages->trans('calendar.friendly_match_desc', [], $locale),
                    'scheduledAt' => $match->getScheduledAt()->format(\DateTimeInterface::ATOM),
                    'visibility' => 'team_only',
                    'status' => $match->getStatus()->value,
                    'metadata' => [
                        'friendlyMatchId' => $match->getId(),
                        'homeTeam' => [
                            'id' => $homeId,
                            'name' => $match->getHomeTeam()->getName(),
                        ],
                        'awayTeam' => [
                            'id' => $awayId,
                            'name' => $match->getAwayTeam()->getName(),
                        ],
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

    /**
     * Resolve tick status without picking an arbitrary team's log.
     * Team-scoped ticks use the viewer's team when teamId is set; otherwise prefer
     * the kingdom-scoped companion row (e.g. WeeklyReset treasury) or stay scheduled.
     */
    private function resolveTickStatus(
        Kingdom $kingdom,
        TickType $type,
        \DateTimeImmutable $time,
        ?int $teamId,
    ): string {
        $criteria = [
            'kingdom' => $kingdom,
            'tickType' => $type,
            'scheduledAt' => $time,
        ];

        if ($type->createsTeamScopedLogs() && null !== $teamId) {
            $criteria['team'] = $teamId;
            /** @var \App\Entity\Kingdom\KingdomTickLog|null $log */
            $log = $this->tickLogRepository->findOneBy($criteria);

            return $log ? $log->getStatus() : 'scheduled';
        }

        if ($type->createsKingdomScopedLogs() || !$type->createsTeamScopedLogs()) {
            $criteria['team'] = null;
            /** @var \App\Entity\Kingdom\KingdomTickLog|null $log */
            $log = $this->tickLogRepository->findOneBy($criteria);

            return $log ? $log->getStatus() : 'scheduled';
        }

        // Team-scoped only, no teamId: do not guess from another team's log
        return 'scheduled';
    }
}
