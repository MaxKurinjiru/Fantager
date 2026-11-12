<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueSeason;
use App\Enum\BattleStatus;
use App\Enum\LeagueSeasonStatus;
use App\Enum\TickType;
use App\Message\CombatWave;
use App\Message\ExecuteSingleTickMessage;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Service\League\SeasonTransitionService;
use App\Service\Marketplace\MarketplaceService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class KingdomTickOrchestrator
{
    private const MARKETPLACE_BATCH_SIZE = 25;

    public function __construct(
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly SeasonTransitionService $seasonTransitionService,
        private readonly MarketplaceService $marketplaceService,
        private readonly TickClock $tickClock,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'monolog.logger.tick_metrics')]
        private readonly ?LoggerInterface $metricsLogger = null,
    ) {
    }

    /**
     * Safe, database-locked orchestration entry point.
     * Evaluates the kingdom's tick state and dispatches the next eligible group of parallel ticks.
     */
    public function orchestrate(Kingdom $kingdom): void
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            return;
        }

        $this->em->beginTransaction();
        try {
            // Acquire an exclusive pessimistic write lock on the Kingdom to prevent race conditions during state transitions
            $this->em->find(Kingdom::class, $kingdomId, LockMode::PESSIMISTIC_WRITE);

            $threshold = new \DateTimeImmutable('-5 minutes', new \DateTimeZone('UTC'));
            $recovered = $this->tickLogRepository->recoverStaleTicks($kingdom, $threshold);
            if ($recovered > 0) {
                $this->logger->info(sprintf(
                    'KingdomTickOrchestrator: Recovered %d stale/timed out tick(s) for Kingdom ID %d.',
                    $recovered,
                    $kingdomId
                ));
            }

            // 1. Check if the pipeline is blocked by any failed ticks in the kingdom
            if ($this->tickLogRepository->hasFailedTicks($kingdom)) {
                $this->logger->warning(sprintf(
                    'KingdomTickOrchestrator: Kingdom ID %d is blocked by a failed tick. Halting execution.',
                    $kingdomId
                ));
                $this->em->commit();

                return;
            }

            // 2. Check if any ticks are currently processing. If so, we must wait for them to finish.
            if ($this->tickLogRepository->hasProcessingTicks($kingdom)) {
                $this->logger->debug(sprintf(
                    'KingdomTickOrchestrator: Kingdom ID %d has ticks currently processing. Waiting for them to complete.',
                    $kingdomId
                ));
                $this->em->commit();

                return;
            }

            $awaitingCombat = $this->tickLogRepository->findOldestAwaitingCombat($kingdom);
            $oldestPendingTime = $this->tickLogRepository->getOldestPendingTime($kingdom);
            if (null !== $awaitingCombat) {
                $combatTime = $awaitingCombat->getScheduledAt();
                $pendingPriority = null !== $oldestPendingTime && $oldestPendingTime == $combatTime
                    ? $this->tickLogRepository->getHighestPendingPriorityAt($kingdom, $oldestPendingTime)
                    : null;
                if (null === $oldestPendingTime || $oldestPendingTime > $combatTime
                    || ($oldestPendingTime == $combatTime && (null === $pendingPriority || $pendingPriority > $awaitingCombat->getTickType()->getPriority()))) {
                    foreach ($this->tickLogRepository->findAwaitingCombatAt($kingdom, $combatTime) as $tick) {
                        $tick->setStatus('processing');
                        $tick->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                    }
                    $this->em->flush();
                    $this->messageBus->dispatch(new CombatWave($kingdomId, $combatTime, 1));
                    $this->em->commit();

                    return;
                }
            }

            // 3. Find the oldest scheduled timestamp with pending ticks
            if (null === $oldestPendingTime) {
                $this->logger->info(sprintf(
                    'KingdomTickOrchestrator: Kingdom %s (ID: %d) has no more pending ticks. Pipeline completed.',
                    $kingdom->getName(),
                    $kingdomId
                ));
                $this->em->commit();

                return;
            }

            // 4. Find the highest priority group at that timestamp
            $highestPriority = $this->tickLogRepository->getHighestPendingPriorityAt($kingdom, $oldestPendingTime);
            if (null === $highestPriority) {
                $this->em->commit();

                return;
            }

            // 5. Get all pending ticks in this [scheduledAt, priority] group
            $ticksToDispatch = $this->tickLogRepository->findPendingTicksInGroup($kingdom, $oldestPendingTime, $highestPriority);
            $ticksToDispatch = $this->deferTeamDailyResetUntilKingdomFinishes($ticksToDispatch);
            $ticksToDispatch = $this->deferTeamWeeklyResetUntilTreasuryFinishes($ticksToDispatch);
            if (empty($ticksToDispatch)) {
                $this->em->commit();

                return;
            }

            $this->logger->info(sprintf(
                'KingdomTickOrchestrator: Dispatching %d ticks in parallel for Kingdom %s at %s UTC (priority %d).',
                count($ticksToDispatch),
                $kingdom->getName(),
                $oldestPendingTime->format('Y-m-d H:i:s'),
                $highestPriority
            ));

            // 6. Mark all ticks in the group as 'dispatched'
            foreach ($ticksToDispatch as $tick) {
                $tick->setStatus('dispatched');
                $tick->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            }

            $this->em->flush();
            foreach ($ticksToDispatch as $tick) {
                $tickId = $tick->getId();
                if (null !== $tickId) {
                    $this->messageBus->dispatch(new ExecuteSingleTickMessage($tickId));
                }
            }
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function processDailyKingdomPhase(Kingdom $kingdom, KingdomTickLog $tick): void
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            throw new \DomainException('Kingdom must be persisted before processing ticks.');
        }

        $this->em->beginTransaction();
        try {
            $this->em->find(Kingdom::class, $kingdomId, LockMode::PESSIMISTIC_WRITE);
            $this->executeDailyKingdomPhase($kingdom, $tick);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $exception) {
            $this->em->rollback();
            throw $exception;
        }
    }

    public function resumeCombat(Kingdom $kingdom, int $tickId): void
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            throw new \DomainException('Kingdom must be persisted before resuming combat.');
        }

        $this->em->beginTransaction();
        try {
            $this->em->find(Kingdom::class, $kingdomId, LockMode::PESSIMISTIC_WRITE);
            $tick = $this->tickLogRepository->findOneBy(['id' => $tickId, 'kingdom' => $kingdom, 'status' => 'processing']);
            $battle = $tick?->getFixture()?->getBattle() ?? $tick?->getFriendlyMatch()?->getBattle();
            if (null === $tick || null === $battle || $battle->getScheduledAt() != $tick->getScheduledAt()
                || !in_array($battle->getStatus(), [BattleStatus::Simulating, BattleStatus::Stalled], true)) {
                throw new \DomainException('The tick must reference a simulating or stalled battle in its combat cohort.');
            }

            if (BattleStatus::Stalled === $battle->getStatus()) {
                $battle->setStatus(BattleStatus::Simulating);
            }
            $tick->resetCombatRecovery();
            $this->em->flush();
            $this->messageBus->dispatch(new CombatWave($kingdomId, $tick->getScheduledAt(), $battle->getCurrentRound() + 1));
            $this->em->commit();
        } catch (\Throwable $exception) {
            $this->em->rollback();
            throw $exception;
        }
    }

    public function recoverCombatContinuation(Kingdom $kingdom): bool
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            return false;
        }

        $this->em->beginTransaction();
        $lockName = null;
        try {
            $this->em->find(Kingdom::class, $kingdomId, LockMode::PESSIMISTIC_WRITE);
            if ($this->tickLogRepository->hasFailedTicks($kingdom)) {
                $this->em->commit();

                return false;
            }

            $ticks = $this->tickLogRepository->findBy(['kingdom' => $kingdom, 'status' => ['dispatched', 'processing', 'awaiting_combat']]);
            $scheduledAt = null;
            $simulating = [];
            $threshold = new \DateTimeImmutable('-5 minutes', new \DateTimeZone('UTC'));
            foreach ($ticks as $tick) {
                $this->em->refresh($tick);
                $battle = $tick->getFixture()?->getBattle() ?? $tick->getFriendlyMatch()?->getBattle();
                if ('processing' !== $tick->getStatus() || null === $battle || $battle->getScheduledAt() != $tick->getScheduledAt()
                    || !in_array($battle->getStatus(), [BattleStatus::Simulating, BattleStatus::Stalled], true)
                    || (null !== $scheduledAt && $scheduledAt != $tick->getScheduledAt())) {
                    $this->em->commit();

                    return false;
                }
                $scheduledAt = $tick->getScheduledAt();
                if (BattleStatus::Simulating === $battle->getStatus()) {
                    $simulating[] = [$tick, $battle];
                }
            }

            if ([] === $simulating || !array_any($simulating, static fn (array $entry): bool => $entry[0]->getExecutedAt() < $threshold)) {
                $this->em->commit();

                return false;
            }

            $message = new CombatWave($kingdomId, $scheduledAt, 1);
            $connection = $this->em->getConnection();
            $acquired = $connection->fetchOne('SELECT GET_LOCK(?, 0)', [$message->getLockName()]);
            if (0 === (int) $acquired && null !== $acquired && false !== $acquired) {
                $this->em->commit();

                return false;
            }
            if (1 !== (int) $acquired) {
                throw new \RuntimeException('Unable to acquire the combat cohort lock for recovery.');
            }
            $lockName = $message->getLockName();

            foreach ($ticks as $tick) {
                $this->em->refresh($tick, LockMode::PESSIMISTIC_WRITE);
                $battle = $tick->getFixture()?->getBattle() ?? $tick->getFriendlyMatch()?->getBattle();
                if ('processing' !== $tick->getStatus() || null === $battle || $tick->getScheduledAt() != $scheduledAt) {
                    $this->em->commit();

                    return false;
                }
                $this->em->refresh($battle, LockMode::PESSIMISTIC_WRITE);
                if ($battle->getScheduledAt() != $scheduledAt || !in_array($battle->getStatus(), [BattleStatus::Simulating, BattleStatus::Stalled], true)) {
                    $this->em->commit();

                    return false;
                }
            }
            $simulating = array_values(array_filter($simulating, static fn (array $entry): bool => BattleStatus::Simulating === $entry[1]->getStatus()));
            if ([] === $simulating || !array_any($simulating, static fn (array $entry): bool => $entry[0]->getExecutedAt() < $threshold)) {
                $this->em->commit();

                return false;
            }
            $message = new CombatWave($kingdomId, $scheduledAt, min(array_map(static fn (array $entry): int => $entry[1]->getCurrentRound(), $simulating)) + 1);
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            foreach ($simulating as [$tick, $battle]) {
                $tick->setExecutedAt($now);
                $tick->recordCombatRecovery($battle->getCurrentRound());
                if ($tick->getCombatRecoveryCount() >= 3) {
                    ($this->metricsLogger ?? $this->logger)->warning('Combat continuation repeatedly recovered without checkpoint progress.', [
                        'kingdom_id' => $kingdomId,
                        'tick_id' => $tick->getId(),
                        'battle_id' => $battle->getId(),
                        'round' => $battle->getCurrentRound(),
                        'recoveries' => $tick->getCombatRecoveryCount(),
                    ]);
                }
            }
            $this->em->flush();
            $this->messageBus->dispatch($message);
            $this->em->commit();

            return true;
        } catch (\Throwable $exception) {
            $this->em->rollback();
            throw $exception;
        } finally {
            if (null !== $lockName) {
                $this->em->getConnection()->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    public function recoverInterruptedTicks(Kingdom $kingdom): int
    {
        $kingdomId = $kingdom->getId();
        if (null === $kingdomId) {
            return 0;
        }

        $connection = $this->em->getConnection();
        $threshold = gmdate('Y-m-d H:i:s', time() - 300);
        $ids = $connection->fetchFirstColumn(
            'SELECT phase.id FROM kingdom_tick_log phase INNER JOIN kingdom_tick_event event ON event.id = phase.event_id '
            .'WHERE event.kingdom_id = ? AND phase.status = ? AND phase.executed_at < ? AND event.tick_type NOT IN (?, ?)',
            [$kingdomId, 'processing', $threshold, TickType::LeagueMatch->value, TickType::FriendlyMatch->value],
        );
        $recovered = 0;
        foreach ($ids as $id) {
            $message = new ExecuteSingleTickMessage((int) $id);
            $acquired = $connection->fetchOne('SELECT GET_LOCK(?, 0)', [$message->getLockName()]);
            if (0 === (int) $acquired && null !== $acquired && false !== $acquired) {
                continue;
            }
            if (1 !== (int) $acquired) {
                throw new \RuntimeException('Unable to acquire the tick recovery lock.');
            }
            try {
                $recovered += $connection->executeStatement(
                    "UPDATE kingdom_tick_log SET status = CASE WHEN retry_count >= 2 THEN 'failed' ELSE 'pending' END, "
                    ."error_message = CASE WHEN retry_count >= 2 THEN 'Interrupted tick. Maximum retries reached (3).' ELSE 'Interrupted tick automatically rescheduled.' END, "
                    .'executed_at = ?, retry_count = retry_count + 1 WHERE id = ? AND status = ? AND executed_at < ?',
                    [gmdate('Y-m-d H:i:s'), $id, 'processing', $threshold],
                );
            } finally {
                $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$message->getLockName()]);
            }
        }

        return $recovered;
    }

    public function retryOptimisticConflict(int $tickId): bool
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $updated = $connection->executeStatement(
                'UPDATE kingdom_tick_log SET status = ?, executed_at = ?, retry_count = retry_count + 1, error_message = ? WHERE id = ? AND status = ? AND retry_count < 3',
                ['dispatched', gmdate('Y-m-d H:i:s'), 'Automatic retry after optimistic-lock conflict.', $tickId, 'processing'],
            );
            if (1 === $updated) {
                $this->messageBus->dispatch(new ExecuteSingleTickMessage($tickId));
            }
            $connection->commit();

            return 1 === $updated;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function executeDailyKingdomPhase(Kingdom $kingdom, KingdomTickLog $tick): void
    {
        $scheduledAt = $tick->getScheduledAt();
        $previousTime = $this->tickClock->getCustomTime();
        $this->tickClock->setCustomTime($scheduledAt);
        try {
            $batch = $tick->getMarketplaceBatch();
            if (null === $batch) {
                $this->prepareUpcomingSeason($kingdom, $scheduledAt);
            }
            $maximumListingId = $tick->getMarketplaceMaxListingId() ?? $this->marketplaceService->getMaximumListingId($kingdom);
            $settled = $maximumListingId > 0
                ? $this->marketplaceService->processExpiredListingsBatch($kingdom, $scheduledAt, $maximumListingId, self::MARKETPLACE_BATCH_SIZE)
                : 0;
            $this->metricsLogger?->info('Marketplace settlement batch metrics.', [
                'tick_id' => $tick->getId(),
                'kingdom_id' => $kingdom->getId(),
                'batch' => $batch ?? 0,
                'listing_count' => $settled,
                'maximum_listing_id' => $maximumListingId,
            ]);
            if (self::MARKETPLACE_BATCH_SIZE === $settled) {
                $event = $tick->getEvent();
                if (null === $event) {
                    throw new \LogicException('A marketplace batch must belong to a tick event.');
                }
                $next = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
                    ->setScheduledAt($scheduledAt)->setMarketplaceBatch(($batch ?? 0) + 1, $maximumListingId)->setEvent($event);
                $this->em->persist($next);
            }
        } finally {
            $this->tickClock->setCustomTime($previousTime);
        }
    }

    /**
     * @param list<KingdomTickLog> $ticks
     *
     * @return list<KingdomTickLog>
     */
    public function deferTeamDailyResetUntilKingdomFinishes(array $ticks): array
    {
        foreach ($ticks as $tick) {
            if (TickType::DailyReset === $tick->getTickType() && null === $tick->getTeam()) {
                return array_values(array_filter($ticks, static fn (KingdomTickLog $candidate): bool => TickType::DailyReset !== $candidate->getTickType() || null === $candidate->getTeam()));
            }
        }

        return $ticks;
    }

    private function prepareUpcomingSeason(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): void
    {
        $localTime = $scheduledAt->setTimezone(new \DateTimeZone($kingdom->getTimezone()));
        if (1 !== (int) $localTime->format('N')) {
            return;
        }

        $activeSeason = $this->em->getRepository(LeagueSeason::class)->findOneBy([
            'kingdom' => $kingdom,
            'status' => LeagueSeasonStatus::Active,
        ]);
        if (!$activeSeason instanceof LeagueSeason) {
            return;
        }

        $startDate = new \DateTimeImmutable($activeSeason->getStartDate()->format('Y-m-d').' 00:00:00', $localTime->getTimezone());
        $prepMonday = 1 === (int) $startDate->format('N') ? $startDate : $startDate->modify('next monday');
        if ($localTime >= $prepMonday->modify('+10 weeks')) {
            $this->seasonTransitionService->prepareUpcomingSeason($kingdom);
        }
    }

    /**
     * Kingdom-scoped WeeklyReset (Royal Treasury) must finish before team-scoped
     * WeeklyReset (maintenance, payroll, crisis), even though they share priority 6.
     *
     * @param list<KingdomTickLog> $ticks
     *
     * @return list<KingdomTickLog>
     */
    public function deferTeamWeeklyResetUntilTreasuryFinishes(array $ticks): array
    {
        $hasTeamWeeklyReset = false;
        $hasKingdomWeeklyReset = false;
        foreach ($ticks as $tick) {
            if (TickType::WeeklyReset !== $tick->getTickType()) {
                continue;
            }
            if (null !== $tick->getTeam()) {
                $hasTeamWeeklyReset = true;
            } else {
                $hasKingdomWeeklyReset = true;
            }
        }

        if (!$hasTeamWeeklyReset) {
            return $ticks;
        }

        $selectedTeam = false;

        return array_values(array_filter($ticks, static function (KingdomTickLog $tick) use ($hasKingdomWeeklyReset, &$selectedTeam): bool {
            if (TickType::WeeklyReset !== $tick->getTickType() || null === $tick->getTeam()) {
                return true;
            }
            if ($hasKingdomWeeklyReset || $selectedTeam) {
                return false;
            }
            $selectedTeam = true;

            return true;
        }));
    }
}
