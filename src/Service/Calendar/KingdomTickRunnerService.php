<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueSeason;
use App\Enum\LeagueSeasonStatus;
use App\Enum\TickType;
use App\Message\ProcessKingdomTicksHandler;
use App\Message\ProcessKingdomTicksMessage;
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
        private readonly ProcessKingdomTicksHandler $ticksHandler,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Returns a tick log that blocks further scheduling (stuck in processing or failed).
     */
    public function findBlockedTick(Kingdom $kingdom): ?KingdomTickLog
    {
        return $this->tickLogRepository->findOneBy([
            'kingdom' => $kingdom,
            'status' => ['processing', 'failed'],
        ]);
    }

    /**
     * Creates KingdomTickLog rows for every tick occurrence between the last completed
     * tick (or season start) and $until.
     *
     * @return int Number of newly scheduled tick log entries
     */
    public function schedulePendingTicks(Kingdom $kingdom, \DateTimeImmutable $until): int
    {
        if (null !== $this->findBlockedTick($kingdom)) {
            return 0;
        }

        $seasonStartDate = $this->resolveSeasonStartDate($kingdom);
        $ticksScheduled = 0;

        foreach (TickType::cases() as $tickType) {
            $latestTime = $this->tickLogRepository->getLatestCompletedTickTime($kingdom, $tickType);

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
                ]);

                if (null !== $existingLog) {
                    continue;
                }

                $log = new KingdomTickLog();
                $log->setKingdom($kingdom);
                $log->setTickType($occurrence['type']);
                $log->setScheduledAt($occurrence['time']);
                $log->setStatus('processing');

                $this->em->persist($log);
                ++$ticksScheduled;
            }
        }

        if ($ticksScheduled > 0) {
            $this->em->flush();
        }

        return $ticksScheduled;
    }

    /**
     * Runs all ticks currently marked as "processing" for the kingdom, in chronological order.
     * Intended for bootstrap / dev catch-up where Messenger async transport is not required.
     */
    public function processPendingTicksSynchronously(Kingdom $kingdom): void
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            throw new \DomainException('Kingdom must be persisted before processing ticks.');
        }

        ($this->ticksHandler)(new ProcessKingdomTicksMessage($kingdomId));
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
