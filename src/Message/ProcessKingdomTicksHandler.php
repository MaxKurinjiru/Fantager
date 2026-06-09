<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Repository\Hero\HeroRepository;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Training\TrainingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessKingdomTicksHandler
{
    private const PRIORITIES = [
        TickType::WeeklyTraining->value => 2,
        TickType::LeagueMatch->value => 3,
        TickType::SeasonTransition->value => 4,
        TickType::FatigueRecovery->value => 5,
        TickType::DailyReset->value => 6,
        TickType::WeeklyReset->value => 6,
        TickType::RaceOptimization->value => 6,
    ];

    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly HeroRepository $heroRepository,
        private readonly TrainingService $trainingService,
        private readonly ArenaRevenueService $arenaRevenueService,
        private readonly HeadquartersService $hqService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessKingdomTicksMessage $message): void
    {
        $kingdomId = $message->getKingdomId();
        /** @var Kingdom|null $kingdom */
        $kingdom = $this->kingdomRepository->find($kingdomId);
        if (null === $kingdom) {
            $this->logger->error(sprintf('ProcessKingdomTicksHandler: Kingdom with ID %d not found.', $kingdomId));

            return;
        }

        // Find all processing log entries for this kingdom
        /** @var list<KingdomTickLog> $logs */
        $logs = $this->tickLogRepository->findBy(
            ['kingdom' => $kingdom, 'status' => 'processing']
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

            $pA = self::PRIORITIES[$a->getTickType()->value];
            $pB = self::PRIORITIES[$b->getTickType()->value];

            return $pA <=> $pB;
        });

        foreach ($logs as $log) {
            /* @var KingdomTickLog $log */
            $this->em->beginTransaction();
            try {
                $this->executeTick($kingdom, $log);

                $log->setStatus('completed');
                $log->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                $log->setErrorMessage(null);

                $this->em->flush();
                $this->em->commit();

                $this->logger->info(sprintf('Tick %s completed successfully for Kingdom %s at scheduled time %s', $log->getTickType()->value, $kingdom->getName(), $log->getScheduledAt()->format('Y-m-d H:i:s')));
            } catch (\Throwable $e) {
                $this->em->rollback();

                // Start a new independent transaction to record the failure
                $this->em->beginTransaction();
                try {
                    // Refresh entity in case of session clear using a separate variable
                    /** @var KingdomTickLog|null $refreshedLog */
                    $refreshedLog = $this->tickLogRepository->find($log->getId());
                    if (null !== $refreshedLog) {
                        $refreshedLog->setStatus('failed');
                        $refreshedLog->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                        $refreshedLog->setErrorMessage($e->getMessage()."\n".$e->getTraceAsString());
                        $this->em->flush();
                    }
                    $this->em->commit();
                } catch (\Throwable) {
                    $this->em->rollback();
                }

                $this->logger->error(sprintf('Tick %s failed for Kingdom %s: %s', $log->getTickType()->value, $kingdom->getName(), $e->getMessage()), ['exception' => $e]);

                // Halt execution for this Kingdom entirely to maintain chronological order
                break;
            }
        }
    }

    private function executeTick(Kingdom $kingdom, KingdomTickLog $log): void
    {
        $type = $log->getTickType();
        $scheduledAt = $log->getScheduledAt();

        switch ($type) {
            case TickType::WeeklyTraining:
                $this->trainingService->processTrainingTick($scheduledAt, $kingdom);
                break;

            case TickType::WeeklyReset:
                // Distribute arena ticket revenue for teams in this kingdom
                $this->arenaRevenueService->distributeWeeklyRevenue($kingdom);
                break;

            case TickType::RaceOptimization:
                $this->hqService->processRaceOptimizationTick($kingdom);
                break;

            case TickType::FatigueRecovery:
                $this->processFatigueRecovery($kingdom);
                break;

            case TickType::DailyReset:
            case TickType::LeagueMatch:
            case TickType::SeasonTransition:
                // Stub execution paths. In the future, these will invoke respective services.
                $this->logger->debug(sprintf('Executing stub tick %s for Kingdom %s', $type->value, $kingdom->getName()));
                break;
        }
    }

    private function processFatigueRecovery(Kingdom $kingdom): void
    {
        $speed = (float) $kingdom->getGameSpeed();

        // Base recovery amounts
        $fatigueReduction = (int) round(10 * $speed);
        $formIncrease = (int) round(5 * $speed);

        // Fetch heroes belonging to this Kingdom that need recovery
        $qb = $this->heroRepository->createQueryBuilder('h')
            ->join('h.team', 't')
            ->where('t.kingdom = :kingdom')
            ->andWhere('h.fatigue > 0 OR h.form < 100')
            ->setParameter('kingdom', $kingdom);

        /** @var list<\App\Entity\Hero\Hero> $heroes */
        $heroes = $qb->getQuery()->getResult();

        foreach ($heroes as $hero) {
            $newFatigue = max(0, $hero->getFatigue() - $fatigueReduction);
            $newForm = min(100, $hero->getForm() + $formIncrease);

            $hero->setFatigue($newFatigue);
            $hero->setForm($newForm);
        }
    }
}
