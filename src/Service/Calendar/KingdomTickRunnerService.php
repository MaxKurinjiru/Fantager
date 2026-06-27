<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueSeason;
use App\Entity\Team\Team;
use App\Enum\LeagueSeasonStatus;
use App\Enum\TickType;
use App\Message\ExecuteSingleTickHandler;
use App\Message\ExecuteSingleTickMessage;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Schedules and optionally executes pending kingdom server ticks.
 *
 * Used by app:ticks:run (async via Messenger) and app:kingdom:initialize --catch-up-ticks (sync).
 */
class KingdomTickRunnerService
{
    public function __construct(
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
        private readonly TickScheduleCalculator $scheduleCalculator,
        private readonly ExecuteSingleTickHandler $singleTickHandler,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Returns a tick log that blocks further scheduling (stuck in processing or failed),
     * optionally scoped by team or fixture.
     */
    public function findBlockedTick(Kingdom $kingdom, ?Team $team = null, ?LeagueFixture $fixture = null): ?KingdomTickLog
    {
        // A kingdom-level blocked tick blocks everything in the kingdom
        $blockedKingdom = $this->tickLogRepository->findOneBy([
            'kingdom' => $kingdom,
            'status' => ['dispatched', 'processing', 'failed'],
            'team' => null,
            'fixture' => null,
        ]);
        if (null !== $blockedKingdom) {
            return $blockedKingdom;
        }

        if (null !== $team) {
            // A team-level blocked tick blocks this team
            return $this->tickLogRepository->findOneBy([
                'kingdom' => $kingdom,
                'status' => ['dispatched', 'processing', 'failed'],
                'team' => $team,
            ]);
        }

        if (null !== $fixture) {
            // A fixture-level blocked tick blocks this fixture
            return $this->tickLogRepository->findOneBy([
                'kingdom' => $kingdom,
                'status' => ['dispatched', 'processing', 'failed'],
                'fixture' => $fixture,
            ]);
        }

        // Check if there is ANY blocked tick of any scope in the kingdom
        return $this->tickLogRepository->findOneBy([
            'kingdom' => $kingdom,
            'status' => ['dispatched', 'processing', 'failed'],
        ]);
    }

    /**
     * Checks if there are any pending ticks for the kingdom.
     */
    public function hasPendingTicks(Kingdom $kingdom): bool
    {
        return $this->tickLogRepository->count([
            'kingdom' => $kingdom,
            'status' => 'pending',
        ]) > 0;
    }

    /**
     * Creates KingdomTickLog rows for every tick occurrence between the last completed
     * tick (or season start) and $until.
     *
     * @return int Number of newly scheduled tick log entries
     */
    private const TEAM_SCOPED_TICKS = [
        TickType::WeeklyTraining,
        TickType::WeeklyReset,
        TickType::RaceOptimization,
        TickType::FatigueRecovery,
        TickType::InactivePlayerCleanup,
        TickType::DailyReset,
    ];

    private const KINGDOM_SCOPED_TICKS = [
        TickType::SeasonTransition,
        TickType::InactiveRegistrationCleanup,
    ];

    public function schedulePendingTicks(Kingdom $kingdom, \DateTimeImmutable $until): int
    {
        $seasonStartDate = $this->resolveSeasonStartDate($kingdom);
        $ticksScheduled = 0;

        // Fetch active teams in the kingdom
        $teams = $this->em->getRepository(Team::class)->findBy(['kingdom' => $kingdom]);

        foreach (TickType::cases() as $tickType) {
            // Determine scopes for this tick type
            $scopes = [];

            if (in_array($tickType, self::TEAM_SCOPED_TICKS, true)) {
                foreach ($teams as $team) {
                    $scopes[] = ['team' => $team, 'fixture' => null];
                }
            }

            if (in_array($tickType, self::KINGDOM_SCOPED_TICKS, true) || TickType::WeeklyReset === $tickType) {
                // WeeklyReset has both team-scoped and kingdom-scoped logs
                $scopes[] = ['team' => null, 'fixture' => null];
            }

            // Loop over each team or kingdom scope and schedule occurrences
            foreach ($scopes as $scope) {
                $team = $scope['team'];
                $fixture = $scope['fixture'];

                // Check if this scope is blocked
                if (null !== $this->findBlockedTick($kingdom, $team, $fixture)) {
                    continue;
                }

                $latestTime = $this->tickLogRepository->getLatestCompletedTickTime($kingdom, $tickType, $team, $fixture);

                if (null === $latestTime) {
                    if (null !== $seasonStartDate) {
                        try {
                            $tz = new \DateTimeZone($kingdom->getTimezone());
                        } catch (\Exception) {
                            $tz = new \DateTimeZone('UTC');
                        }
                        $latestTime = new \DateTimeImmutable($seasonStartDate->format('Y-m-d').' 00:00:00', $tz);
                        $latestTime = $latestTime->setTimezone(new \DateTimeZone('UTC'));
                    } else {
                        $latestTime = $until->modify('-24 hours');
                    }
                }

                $occurrences = $this->scheduleCalculator->generateOccurrences(
                    $latestTime,
                    $until,
                    $kingdom->getTimezone(),
                    $seasonStartDate,
                );

                foreach ($occurrences as $occurrence) {
                    if ($occurrence['type'] !== $tickType) {
                        continue;
                    }

                    $existingLog = $this->tickLogRepository->findOneBy([
                        'kingdom' => $kingdom,
                        'tickType' => $occurrence['type'],
                        'scheduledAt' => $occurrence['time'],
                        'team' => $team,
                        'fixture' => $fixture,
                    ]);

                    if (null !== $existingLog) {
                        continue;
                    }

                    $log = new KingdomTickLog();
                    $log->setKingdom($kingdom);
                    $log->setTickType($occurrence['type']);
                    $log->setScheduledAt($occurrence['time']);
                    $log->setTeam($team);
                    $log->setFixture($fixture);
                    $log->setStatus('pending');

                    $this->em->persist($log);
                    ++$ticksScheduled;
                }
            }

            // Special handling for Match-Scoped (LeagueMatch)
            if (TickType::LeagueMatch === $tickType) {
                if (null !== $this->findBlockedTick($kingdom)) {
                    continue;
                }

                // We find the latest completed LeagueMatch time for the kingdom
                $latestTime = $this->tickLogRepository->getLatestCompletedTickTime($kingdom, TickType::LeagueMatch);
                if (null === $latestTime) {
                    if (null !== $seasonStartDate) {
                        try {
                            $tz = new \DateTimeZone($kingdom->getTimezone());
                        } catch (\Exception) {
                            $tz = new \DateTimeZone('UTC');
                        }
                        $latestTime = new \DateTimeImmutable($seasonStartDate->format('Y-m-d').' 00:00:00', $tz);
                        $latestTime = $latestTime->setTimezone(new \DateTimeZone('UTC'));
                    } else {
                        $latestTime = $until->modify('-24 hours');
                    }
                }

                $occurrences = $this->scheduleCalculator->generateOccurrences(
                    $latestTime,
                    $until,
                    $kingdom->getTimezone(),
                    $seasonStartDate,
                );

                foreach ($occurrences as $occurrence) {
                    if (TickType::LeagueMatch !== $occurrence['type']) {
                        continue;
                    }

                    // Find all fixtures scheduled at this time in the kingdom
                    $fixtures = $this->em->getRepository(LeagueFixture::class)
                        ->findScheduledFixturesAtTime($kingdom, $occurrence['time']);

                    foreach ($fixtures as $fixture) {
                        if (null !== $this->findBlockedTick($kingdom, null, $fixture)) {
                            continue;
                        }

                        $existingLog = $this->tickLogRepository->findOneBy([
                            'kingdom' => $kingdom,
                            'tickType' => TickType::LeagueMatch,
                            'scheduledAt' => $occurrence['time'],
                            'fixture' => $fixture,
                        ]);

                        if (null !== $existingLog) {
                            continue;
                        }

                        $log = new KingdomTickLog();
                        $log->setKingdom($kingdom);
                        $log->setTickType(TickType::LeagueMatch);
                        $log->setScheduledAt($occurrence['time']);
                        $log->setFixture($fixture);
                        $log->setStatus('pending');

                        $this->em->persist($log);
                        ++$ticksScheduled;
                    }
                }
            }
        }

        if ($ticksScheduled > 0) {
            $this->em->flush();
        }

        return $ticksScheduled;
    }

    /**
     * Runs all ticks currently marked as "pending" for the kingdom, in chronological and priority order.
     * Intended for bootstrap / dev catch-up where Messenger async transport is not required.
     */
    public function processPendingTicksSynchronously(Kingdom $kingdom): void
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            throw new \DomainException('Kingdom must be persisted before processing ticks.');
        }

        // Fetch all pending ticks
        /** @var list<KingdomTickLog> $logs */
        $logs = $this->tickLogRepository->findBy(
            ['kingdom' => $kingdom, 'status' => 'pending']
        );

        if (empty($logs)) {
            return;
        }

        // Sort them chronologically, then by logical priority
        usort($logs, function (KingdomTickLog $a, KingdomTickLog $b): int {
            $timeCompare = $a->getScheduledAt() <=> $b->getScheduledAt();
            if (0 !== $timeCompare) {
                return $timeCompare;
            }

            return $a->getTickType()->getPriority() <=> $b->getTickType()->getPriority();
        });

        // Execute them one by one synchronously. If one fails, it throws an exception,
        // which naturally halts synchronous processing.
        foreach ($logs as $log) {
            $logId = $log->getId();
            if (null !== $logId) {
                ($this->singleTickHandler)(new ExecuteSingleTickMessage($logId));

                // Check if the tick actually succeeded. If it failed, stop execution.
                $this->em->refresh($log);
                if ('failed' === $log->getStatus()) {
                    throw new \RuntimeException(sprintf('Synchronous tick execution failed for tick ID %d (%s): %s', $logId, $log->getTickType()->value, (string) $log->getErrorMessage()));
                }
            }
        }
    }

    private function resolveSeasonStartDate(Kingdom $kingdom): ?\DateTimeImmutable
    {
        /** @var LeagueSeason|null $season */
        $season = $this->seasonRepository->findOneBy([
            'kingdom' => $kingdom,
            'status' => LeagueSeasonStatus::Active,
        ]);
        if (null === $season) {
            /** @var LeagueSeason|null $season */
            $season = $this->seasonRepository->findOneBy(['kingdom' => $kingdom], ['seasonNumber' => 'DESC']);
        }

        return $season?->getStartDate();
    }
}
