<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Kingdom\Kingdom;
use App\Message\CombatWave;
use App\Repository\Combat\BattleRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
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
    ) {
        if ($roundsPerBatch < 1 || $timeBudgetSeconds < 0) {
            throw new \InvalidArgumentException('Combat batch limits must be non-negative with at least one round.');
        }
    }

    public function process(CombatWave $message): void
    {
        $connection = $this->em->getConnection();
        $lockName = sprintf('combat_wave_%d_%s', $message->getKingdomId(), $message->getScheduledAt()->format('U.u'));
        $acquired = $connection->fetchOne('SELECT GET_LOCK(?, 0)', [$lockName]);
        if (0 === (int) $acquired && null !== $acquired && false !== $acquired) {
            return;
        }
        if (1 !== (int) $acquired) {
            throw new \RuntimeException('Unable to acquire the combat cohort lock.');
        }

        try {
            $startedAt = hrtime(true);
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

            $kingdom = $this->em->find(Kingdom::class, $message->getKingdomId());
            if (null !== $kingdom) {
                $this->orchestrator->orchestrate($kingdom);
            }
        } finally {
            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]);
        }
    }
}
