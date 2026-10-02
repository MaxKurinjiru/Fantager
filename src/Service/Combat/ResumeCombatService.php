<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Combat\Battle;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueFixture;
use App\Enum\BattleStatus;
use App\Enum\TickType;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\League\LeagueMatchResolutionService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ResumeCombatService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CombatEngine $combatEngine,
        private readonly LeagueMatchResolutionService $leagueMatchResolutionService,
        private readonly KingdomTickOrchestrator $orchestrator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resume(int $battleId): void
    {
        $this->em->beginTransaction();
        try {
            /** @var Battle|null $battle */
            $battle = $this->em->find(Battle::class, $battleId, LockMode::PESSIMISTIC_WRITE);

            if (null === $battle) {
                $this->logger->error(sprintf('ResumeCombatService: Battle ID %d not found.', $battleId));
                $this->em->rollback();

                return;
            }

            if (BattleStatus::Stalled !== $battle->getStatus()) {
                $this->logger->info(sprintf('ResumeCombatService: Battle ID %d is not stalled (status: %s). Exiting.', $battleId, $battle->getStatus()->value));
                $this->em->rollback();

                return;
            }

            $battle->setStatus(BattleStatus::Simulating);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('ResumeCombatService: Lock error for Battle ID %d: %s', $battleId, $e->getMessage()));
            try {
                $this->em->rollback();
            } catch (\Throwable) {
            }

            return;
        }

        $round = 1;
        while (true) {
            $this->em->beginTransaction();
            try {
                $battle = $this->em->find(Battle::class, $battleId, LockMode::PESSIMISTIC_WRITE);
                if (null === $battle || BattleStatus::Simulating !== $battle->getStatus()) {
                    $this->em->rollback();
                    break;
                }

                $round = $battle->getCurrentRound() + 1;
                if ($round > 200) {
                    $runState = $battle->getRunState() ?? [];
                    $runState['status'] = 'completed';
                    $battle->setRunState($runState);
                } else {
                    $runState = $battle->getRunState() ?? [];
                    $this->combatEngine->simulateRound($runState, $round);
                    $battle->setCurrentRound($round);
                    $battle->setRunState($runState);
                }

                if ('completed' === $runState['status']) {
                    $battle->setStatus(BattleStatus::Completed);
                    $battle->setScoreA((int) $runState['scoreA']);
                    $battle->setScoreB((int) $runState['scoreB']);
                    $battle->setCombatLog((array) $runState['combatLog']);
                    $battle->setResult($battle->getResult() ?? \App\Enum\BattleResult::fromScores($runState['scoreA'], $runState['scoreB']));
                    $battle->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

                    $this->leagueMatchResolutionService->completeBattle($battle);

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

                if ('completed' === $runState['status']) {
                    $this->orchestrator->orchestrate($battle->getKingdom());
                    break;
                }
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('ResumeCombatService: Battle ID %d failed in round %d during resume: %s', $battleId, $round, $e->getMessage()), ['exception' => $e]);
                $this->em->rollback();

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
                break;
            }
        }
    }
}
