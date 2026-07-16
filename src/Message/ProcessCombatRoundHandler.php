<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Combat\Battle;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueFixture;
use App\Enum\BattleStatus;
use App\Enum\TickType;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Combat\CombatEngine;
use App\Service\League\LeagueMatchResolutionService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ProcessCombatRoundHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CombatEngine $combatEngine,
        private readonly MessageBusInterface $messageBus,
        private readonly LeagueMatchResolutionService $leagueMatchResolutionService,
        private readonly KingdomTickOrchestrator $orchestrator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessCombatRound $message): void
    {
        $battleId = $message->getBattleId();
        $round = $message->getRound();

        $this->em->beginTransaction();
        try {
            /** @var Battle|null $battle */
            $battle = $this->em->find(Battle::class, $battleId, LockMode::PESSIMISTIC_WRITE);

            if (null === $battle) {
                $this->logger->error(sprintf('ProcessCombatRoundHandler: Battle ID %d not found.', $battleId));
                $this->em->rollback();

                return;
            }

            if (BattleStatus::Simulating !== $battle->getStatus() || $battle->getCurrentRound() >= $round) {
                // Already processed or not simulating
                $this->em->rollback();

                return;
            }

            $runState = $battle->getRunState() ?? [];

            try {
                $this->combatEngine->simulateRound($runState, $round);
                $battle->setCurrentRound($round);
                $battle->setRunState($runState);

                if ('completed' === $runState['status']) {
                    $battle->setStatus(BattleStatus::Completed);
                    $battle->setScoreA((int) $runState['scoreA']);
                    $battle->setScoreB((int) $runState['scoreB']);
                    $battle->setCombatLog((array) $runState['combatLog']);

                    // Fallback to determine battle result from scores
                    $battle->setResult($battle->getResult() ?? \App\Enum\BattleResult::fromScores($runState['scoreA'], $runState['scoreB']));
                    $battle->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

                    // Complete the battle and run all side effects
                    $this->leagueMatchResolutionService->completeBattle($battle);

                    // Complete the corresponding tick log for the fixture
                    $fixture = $this->em->getRepository(LeagueFixture::class)->findOneBy(['battle' => $battle]);
                    if (null !== $fixture) {
                        $tickLog = $this->em->getRepository(KingdomTickLog::class)->findOneBy([
                            'fixture' => $fixture,
                            'tickType' => TickType::LeagueMatch,
                            'status' => 'processing',
                        ]);
                        if (null !== $tickLog) {
                            $tickLog->setStatus('completed');
                            $tickLog->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                        }
                    }
                }

                $this->em->flush();
                $this->em->commit();
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('ProcessCombatRoundHandler: Battle ID %d failed in round %d: %s', $battleId, $round, $e->getMessage()), ['exception' => $e]);
                $this->em->rollback();

                // Start separate transaction to record the stall
                $this->em->beginTransaction();
                $battle = $this->em->find(Battle::class, $battleId, LockMode::PESSIMISTIC_WRITE);
                if (null !== $battle) {
                    $battle->setStatus(BattleStatus::Stalled);
                    $runState = $battle->getRunState() ?? [];
                    $runState['error'] = $e->getMessage()."\n".$e->getTraceAsString();
                    $battle->setRunState($runState);
                    $this->em->flush();
                }
                $this->em->commit();
            }
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('ProcessCombatRoundHandler: Outer lock error for Battle ID %d: %s', $battleId, $e->getMessage()), ['exception' => $e]);
            try {
                $this->em->rollback();
            } catch (\Throwable) {
            }

            return;
        }

        // Barrier Check: Check if all simulating battles in cohort have completed round $round
        $battle = $this->em->find(Battle::class, $battleId);
        if (null === $battle) {
            return;
        }

        $kingdom = $battle->getKingdom();
        $scheduledAt = $battle->getScheduledAt();
        if (null === $scheduledAt) {
            return;
        }

        // If this battle was completed, we trigger orchestrate
        if (BattleStatus::Completed === $battle->getStatus()) {
            $this->orchestrator->orchestrate($kingdom);
        }

        $simulatingCountWithLowerRound = $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Battle::class, 'b')
            ->where('b.kingdom = :kingdom')
            ->andWhere('b.scheduledAt = :scheduledAt')
            ->andWhere('b.status = :status')
            ->andWhere('b.currentRound < :round')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('scheduledAt', $scheduledAt)
            ->setParameter('status', BattleStatus::Simulating)
            ->setParameter('round', $round)
            ->getQuery()
            ->getSingleScalarResult();

        if (0 === (int) $simulatingCountWithLowerRound) {
            $simulatingCount = $this->em->createQueryBuilder()
                ->select('COUNT(b.id)')
                ->from(Battle::class, 'b')
                ->where('b.kingdom = :kingdom')
                ->andWhere('b.scheduledAt = :scheduledAt')
                ->andWhere('b.status = :status')
                ->setParameter('kingdom', $kingdom)
                ->setParameter('scheduledAt', $scheduledAt)
                ->setParameter('status', BattleStatus::Simulating)
                ->getQuery()
                ->getSingleScalarResult();

            if ((int) $simulatingCount > 0 && $round < 200) {
                $this->messageBus->dispatch(new CombatWave((int) $kingdom->getId(), $scheduledAt, $round + 1));
            }
        }
    }
}
