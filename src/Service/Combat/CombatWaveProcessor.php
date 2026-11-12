<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Kingdom\Kingdom;
use App\Message\CombatWave;
use App\Repository\Combat\BattleRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CombatWaveProcessor
{
    public function __construct(
        private readonly BattleRepository $battleRepository,
        private readonly CombatRoundProcessor $roundProcessor,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
        private readonly KingdomTickOrchestrator $orchestrator,
        private readonly int $roundsPerBatch = 10,
        private readonly float $timeBudgetSeconds = 2.0,
        private readonly ?LoggerInterface $logger = null,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'monolog.logger.tick_metrics')]
        private readonly ?LoggerInterface $metricsLogger = null,
    ) {
        if ($roundsPerBatch < 1 || $timeBudgetSeconds < 0) {
            throw new \InvalidArgumentException('Combat batch limits must be non-negative with at least one round.');
        }
    }

    public function process(CombatWave $message): void
    {
        $connection = $this->em->getConnection();
        $lockName = $message->getLockName();
        $acquired = $connection->fetchOne('SELECT GET_LOCK(?, 0)', [$lockName]);
        if (0 === (int) $acquired && null !== $acquired && false !== $acquired) {
            throw new \Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException('Combat cohort is already in progress.', retryDelay: 1000);
        }
        if (1 !== (int) $acquired) {
            throw new \RuntimeException('Unable to acquire the combat cohort lock.');
        }

        $startedAt = hrtime(true);
        $memoryAtStart = memory_get_usage();
        $roundAttempts = 0;
        $battleIds = [];
        $outcome = 'completed';
        try {
            $connection->beginTransaction();
            try {
                for ($wave = 0; $wave < $this->roundsPerBatch; ++$wave) {
                    $battles = $this->battleRepository->findSimulatingCohortRounds($message->getKingdomId(), $message->getScheduledAt());
                    if ([] === $battles) {
                        break;
                    }

                    $round = min(array_column($battles, 'currentRound')) + 1;
                    foreach ($battles as $battle) {
                        if ($battle['currentRound'] + 1 !== $round) {
                            continue;
                        }

                        ++$roundAttempts;
                        $battleIds[$battle['id']] = true;
                        $this->roundProcessor->process($battle['id'], $round);
                        if ((hrtime(true) - $startedAt) / 1_000_000_000 >= $this->timeBudgetSeconds) {
                            break 2;
                        }
                    }
                }

                $battles = $this->battleRepository->findSimulatingCohortRounds($message->getKingdomId(), $message->getScheduledAt());
                if ([] !== $battles) {
                    $this->messageBus->dispatch(new CombatWave(
                        $message->getKingdomId(),
                        $message->getScheduledAt(),
                        min(array_column($battles, 'currentRound')) + 1,
                    ));
                }
                $connection->commit();
            } catch (\Throwable $exception) {
                $connection->rollBack();
                throw $exception;
            }

            $kingdom = $this->em->find(Kingdom::class, $message->getKingdomId());
            if (null !== $kingdom) {
                $this->orchestrator->orchestrate($kingdom);
            }
        } catch (\Throwable $exception) {
            $outcome = 'failed';
            $this->logger?->error('Combat wave failed.', [
                'kingdom_id' => $message->getKingdomId(),
                'scheduled_at' => $message->getScheduledAt()->format(\DateTimeInterface::ATOM),
                'round_attempts' => $roundAttempts,
                'exception' => $exception,
            ]);
            throw $exception;
        } finally {
            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
            $this->metricsLogger?->info('Combat wave metrics.', [
                'kingdom_id' => $message->getKingdomId(),
                'scheduled_at' => $message->getScheduledAt()->format(\DateTimeInterface::ATOM),
                'outcome' => $outcome,
                'round_attempts' => $roundAttempts,
                'battle_count' => count($battleIds),
                'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
                'memory_delta_bytes' => memory_get_usage() - $memoryAtStart,
                'process_peak_memory_bytes' => memory_get_peak_usage(),
            ]);
        }
    }
}
