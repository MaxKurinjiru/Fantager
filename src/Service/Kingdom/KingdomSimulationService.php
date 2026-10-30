<?php

declare(strict_types=1);

namespace App\Service\Kingdom;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueSeason;
use App\Enum\LeagueSeasonStatus;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Repository\League\SeasonReportRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Calendar\KingdomTickRunnerService;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Operator helper for multi-season NPC/balance runs on the live tick + Messenger pipeline.
 */
class KingdomSimulationService
{
    public const EXIT_IDLE = 0;
    public const EXIT_FAILED = 1;
    public const EXIT_BUSY = 2;

    public function __construct(
        private readonly KingdomTickRunnerService $tickRunnerService,
        private readonly KingdomTickOrchestrator $tickOrchestrator,
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
        private readonly SeasonReportRepository $seasonReportRepository,
        private readonly KingdomRepository $kingdomRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function requireKingdom(int $kingdomId): Kingdom
    {
        $kingdom = $this->kingdomRepository->find($kingdomId);
        if (null === $kingdom) {
            throw new \DomainException(sprintf('Kingdom %d not found.', $kingdomId));
        }

        return $kingdom;
    }

    public function stepInterval(string $stepName): \DateInterval
    {
        if (!in_array($stepName, ['week', 'season'], true)) {
            throw new \DomainException('--step must be week or season.');
        }

        return 'season' === $stepName
            ? \DateInterval::createFromDateString('+11 weeks')
            : \DateInterval::createFromDateString('+1 week');
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function pipelineExitCode(array $snapshot): int
    {
        if ($this->isFailed($snapshot)) {
            return self::EXIT_FAILED;
        }

        return $this->isIdle($snapshot) ? self::EXIT_IDLE : self::EXIT_BUSY;
    }

    /**
     * @return array{
     *     kingdom_id: int|null,
     *     name: string,
     *     blocked: array<string, mixed>|null,
     *     counts: array<string, int>,
     *     latest_completed_at: string|null,
     *     latest_completed_type: string|null,
     *     oldest_pending_at: string|null,
     *     failed: list<array<string, mixed>>,
     *     messenger_async_count: int,
     *     completed_seasons: int,
     *     open_ticks: int
     * }
     */
    public function getPipelineSnapshot(Kingdom $kingdom): array
    {
        $snapshot = $this->tickRunnerService->getStatusSnapshot($kingdom);
        $counts = $snapshot['counts'];
        $openTicks = $counts['pending'] + $counts['dispatched'] + $counts['processing'];
        $queue = $this->countMessengerMessages();

        $snapshot['messenger_async_count'] = $queue;
        $snapshot['completed_seasons'] = $this->countCompletedSeasons($kingdom);
        $snapshot['open_ticks'] = $openTicks;

        return $snapshot;
    }

    public function countCompletedSeasons(Kingdom $kingdom): int
    {
        return $this->seasonReportRepository->countByKingdom($kingdom);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function isIdle(array $snapshot): bool
    {
        return 0 === (int) ($snapshot['open_ticks'] ?? 0)
            && 0 === (int) ($snapshot['messenger_async_count'] ?? 0)
            && [] === ($snapshot['failed'] ?? [])
            && null === ($snapshot['blocked'] ?? null);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function isFailed(array $snapshot): bool
    {
        return [] !== ($snapshot['failed'] ?? []) || null !== ($snapshot['blocked'] ?? null);
    }

    public function nextHorizon(Kingdom $kingdom, \DateInterval $step): \DateTimeImmutable
    {
        $latest = $this->tickLogRepository->findLatestCompleted($kingdom);
        if (null !== $latest) {
            $from = $latest->getScheduledAt()->setTimezone(new \DateTimeZone('UTC'));
            $until = $from->add($step);
            if ($until <= $from) {
                throw new \DomainException('The simulation step interval must move time forward.');
            }

            return $until;
        }

        $seasonStart = $this->resolveSeasonStartDate($kingdom);
        if (null === $seasonStart) {
            throw new \DomainException('Kingdom has no league season start date; cannot schedule ticks.');
        }

        $until = $seasonStart->add($step);
        if ($until <= $seasonStart) {
            throw new \DomainException('The simulation step interval must move time forward.');
        }

        return $until;
    }

    /**
     * Schedule ticks through $until and kick the orchestrator. Does not consume Messenger.
     */
    public function scheduleThrough(Kingdom $kingdom, \DateTimeImmutable $until): int
    {
        $blocked = $this->tickRunnerService->findBlockedTick($kingdom);
        if (null !== $blocked) {
            throw new \DomainException(sprintf('Kingdom ID %d has a blocked tick (ID: %d, type: %s, status: %s). Use app:ticks:retry after fixing the cause.', (int) $kingdom->getId(), (int) $blocked->getId(), $blocked->getTickType()->value, $blocked->getStatus()));
        }

        $this->tickRunnerService->recoverStaleTicks($kingdom);
        $scheduled = $this->tickRunnerService->schedulePendingTicks($kingdom, $until);
        if ($scheduled > 0 || $this->tickRunnerService->hasPendingTicks($kingdom)) {
            $this->tickOrchestrator->orchestrate($kingdom);
        }

        return $scheduled;
    }

    /**
     * Resume dispatch if the queue is empty but pending ticks remain (lost-message recovery).
     */
    public function resumeOrchestration(Kingdom $kingdom): void
    {
        $this->tickOrchestrator->orchestrate($kingdom);
    }

    public function countMessengerMessages(): int
    {
        try {
            $count = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');

            return false === $count ? 0 : (int) $count;
        } catch (DbalException) {
            return 0;
        }
    }

    public function reloadKingdom(int $kingdomId): Kingdom
    {
        $this->em->clear();
        $kingdom = $this->em->find(Kingdom::class, $kingdomId);
        if (null === $kingdom) {
            throw new \DomainException(sprintf('Kingdom ID %d was not found after clearing the identity map.', $kingdomId));
        }

        return $kingdom;
    }

    public function resolveSeasonStartDate(Kingdom $kingdom): ?\DateTimeImmutable
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

        $start = $season?->getStartDate();
        if (null === $start) {
            return null;
        }

        return $start->setTimezone(new \DateTimeZone('UTC'));
    }
}
