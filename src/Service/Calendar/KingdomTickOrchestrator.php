<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Message\ExecuteSingleTickMessage;
use App\Repository\Kingdom\KingdomTickLogRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class KingdomTickOrchestrator
{
    public function __construct(
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
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

            // 3. Find the oldest scheduled timestamp with pending ticks
            $oldestPendingTime = $this->tickLogRepository->getOldestPendingTime($kingdom);
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
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        // 7. Dispatch all ticks in the group in parallel (outside transaction to avoid worker race conditions)
        foreach ($ticksToDispatch as $tick) {
            $tickId = $tick->getId();
            if (null !== $tickId) {
                $this->messageBus->dispatch(new ExecuteSingleTickMessage($tickId));
            }
        }
    }
}
