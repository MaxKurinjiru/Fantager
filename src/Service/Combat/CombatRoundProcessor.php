<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Combat\Battle;
use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueFixture;
use App\Enum\BattleResult;
use App\Enum\BattleStatus;
use App\Enum\TickType;
use App\Service\FriendlyMatch\FriendlyMatchResolutionService;
use App\Service\League\LeagueMatchResolutionService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CombatRoundProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CombatEngine $combatEngine,
        private readonly LeagueMatchResolutionService $leagueMatchResolutionService,
        private readonly FriendlyMatchResolutionService $friendlyMatchResolutionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(int $battleId, int $round): void
    {
        $startedSimulation = false;
        $this->em->beginTransaction();
        try {
            $battle = $this->em->find(Battle::class, $battleId, LockMode::PESSIMISTIC_WRITE);
            if (null === $battle) {
                $this->em->rollback();

                return;
            }

            $this->em->refresh($battle);
            if (BattleStatus::Simulating !== $battle->getStatus() || $battle->getCurrentRound() + 1 !== $round) {
                $this->em->rollback();

                return;
            }

            $startedSimulation = true;
            $runState = $battle->getRunState() ?? [];
            if ($round <= CombatEngine::MAX_ROUNDS) {
                $this->combatEngine->simulateRound($runState, $round);
                $battle->setCurrentRound($round);
            }
            if ($round >= CombatEngine::MAX_ROUNDS) {
                $this->combatEngine->forceCompleteAtRoundCap($runState);
            }
            $battle->setRunState($runState);

            if ('completed' === $runState['status']) {
                $battle->setStatus(BattleStatus::Completed);
                $battle->setScoreA((int) $runState['scoreA']);
                $battle->setScoreB((int) $runState['scoreB']);
                $battle->setCombatLog((array) $runState['combatLog']);
                $battle->setResult($battle->getResult() ?? BattleResult::fromScores($runState['scoreA'], $runState['scoreB']));
                $battle->setProcessedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

                $fixture = $this->em->getRepository(LeagueFixture::class)->findOneBy(['battle' => $battle]);
                if (null !== $fixture) {
                    $this->leagueMatchResolutionService->completeBattle($battle);
                    $tickCriteria = ['fixture' => $fixture, 'tickType' => TickType::LeagueMatch];
                } else {
                    $friendlyMatch = $this->em->getRepository(FriendlyMatch::class)->findOneBy(['battle' => $battle]);
                    if (null === $friendlyMatch) {
                        throw new \RuntimeException(sprintf('No fixture or friendly match found for battle ID %d.', $battleId));
                    }
                    $this->friendlyMatchResolutionService->completeBattle($battle);
                    $tickCriteria = ['friendlyMatch' => $friendlyMatch, 'tickType' => TickType::FriendlyMatch];
                }

                $tickLog = $this->em->getRepository(KingdomTickLog::class)->findOneBy($tickCriteria + ['status' => 'processing']);
                if (null !== $tickLog) {
                    $tickLog->setStatus('completed');
                    $tickLog->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                }
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $exception) {
            $this->em->rollback();
            $this->em->clear();
            if (!$startedSimulation || !$this->em->isOpen()) {
                throw $exception;
            }

            $this->em->beginTransaction();
            try {
                $battle = $this->em->find(Battle::class, $battleId, LockMode::PESSIMISTIC_WRITE);
                if (null !== $battle) {
                    $battle->setStatus(BattleStatus::Stalled);
                    $runState = $battle->getRunState() ?? [];
                    $runState['error'] = $exception->getMessage()."\n".$exception->getTraceAsString();
                    $battle->setRunState($runState);
                    $this->em->flush();
                }
                $this->em->commit();
            } catch (\Throwable $stallException) {
                $this->em->rollback();

                throw $stallException;
            }

            $this->logger->error(sprintf('CombatRoundProcessor: Battle ID %d failed in round %d: %s', $battleId, $round, $exception->getMessage()), ['exception' => $exception]);
        } finally {
            $this->em->clear();
        }
    }
}
