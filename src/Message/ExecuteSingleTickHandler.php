<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Auth\User;
use App\Entity\Auth\VerificationToken;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueFixture;
use App\Entity\Notification\Notification;
use App\Entity\Team\Team;
use App\Enum\ChronicleReleaseReason;
use App\Enum\TickType;
use App\Repository\Hero\HeroRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Service\Auth\PlayerInactivityService;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Calendar\TickClock;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Economy\RoyalTreasuryService;
use App\Service\Formation\FixtureFormationService;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Team\FanClubService;
use App\Service\Team\NpcSimulationService;
use App\Service\Team\TeamMoraleReputationService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Training\TrainingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ExecuteSingleTickHandler
{
    public function __construct(
        private readonly KingdomTickLogRepository $tickLogRepository,
        private readonly HeroRepository $heroRepository,
        private readonly TrainingService $trainingService,
        private readonly ArenaRevenueService $arenaRevenueService,
        private readonly FanClubService $fanClubService,
        private readonly TeamMoraleReputationService $teamMoraleReputationService,
        private readonly HeadquartersService $hqService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly RoyalTreasuryService $royalTreasuryService,
        private readonly PlayerInactivityService $playerInactivityService,
        private readonly FixtureFormationService $fixtureFormationService,
        private readonly MarketplaceService $marketplaceService,
        private readonly \App\Service\League\LeagueMatchResolutionService $leagueMatchResolutionService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly \App\Service\League\SeasonTransitionService $seasonTransitionService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly TickClock $tickClock,
        private readonly NpcSimulationService $npcSimulationService,
        private readonly KingdomTickOrchestrator $orchestrator,
    ) {
    }

    public function __invoke(ExecuteSingleTickMessage $message): void
    {
        $tickLogId = $message->getTickLogId();
        /** @var KingdomTickLog|null $log */
        $log = $this->tickLogRepository->find($tickLogId);
        if (null === $log) {
            $this->logger->error(sprintf('ExecuteSingleTickHandler: KingdomTickLog with ID %d not found.', $tickLogId));

            return;
        }

        $kingdom = $log->getKingdom();

        // 1. Try to atomically acquire the tick by updating its status to 'processing'
        $qb = $this->em->createQueryBuilder()
            ->update(KingdomTickLog::class, 'l')
            ->set('l.status', ':processing')
            ->where('l.id = :id')
            ->andWhere('l.status IN (:allowed_statuses)')
            ->setParameter('processing', 'processing')
            ->setParameter('id', $log->getId())
            ->setParameter('allowed_statuses', ['pending', 'dispatched']);

        $rowsUpdated = $qb->getQuery()->execute();
        if (0 === $rowsUpdated) {
            $this->logger->info(sprintf(
                'ExecuteSingleTickHandler: Tick (ID: %d) was already claimed by another worker or is not pending/dispatched. Exiting.',
                $log->getId()
            ));

            return;
        }

        // Now we can execute it.
        $this->tickClock->setCustomTime($log->getScheduledAt());
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

            // Halt the pipeline by exiting without triggering the orchestrator
            return;
        } finally {
            $this->tickClock->setCustomTime(null);
        }

        // 2. Trigger the orchestrator to check if the group is done and proceed to the next step
        $this->orchestrator->orchestrate($kingdom);
    }

    private function executeTick(Kingdom $kingdom, KingdomTickLog $log): void
    {
        $type = $log->getTickType();
        $scheduledAt = $log->getScheduledAt();
        $team = $log->getTeam();
        $fixture = $log->getFixture();

        switch ($type) {
            case TickType::WeeklyTraining:
                if (null !== $team) {
                    $this->npcSimulationService->simulateTraining($kingdom, $scheduledAt, $team);
                    $this->trainingService->processTrainingTick($scheduledAt, $kingdom, $team);
                }
                break;

            case TickType::WeeklyReset:
                if (null !== $team) {
                    // Team-scoped weekly reset
                    $this->npcSimulationService->simulateManagementAndEconomy($kingdom, $scheduledAt, $team);
                    $this->resetWeeklySummons($kingdom, $team);
                    $this->hqService->processMaintenanceTick($kingdom, $team);
                    $this->hqService->processFacilityDowngradeLockTick($kingdom, $team);
                    $this->financialCrisisService->processWeeklyCrisisTick($kingdom, $team);
                } else {
                    // Kingdom-scoped weekly reset: Shared Royal Treasury distribution
                    $this->royalTreasuryService->processWeeklyDistribution($kingdom);
                }
                break;

            case TickType::RaceOptimization:
                if (null !== $team) {
                    $this->hqService->processRaceOptimizationTick($kingdom, $team);
                }
                break;

            case TickType::FatigueRecovery:
                if (null !== $team) {
                    $this->processFatigueRecoveryForTeam($team);
                }
                break;

            case TickType::InactiveRegistrationCleanup:
                $this->cleanupInactiveRegistrations($kingdom, $scheduledAt);
                break;

            case TickType::InactivePlayerCleanup:
                if (null !== $team) {
                    $this->playerInactivityService->processDailyInactivityTick($kingdom, $scheduledAt, $team);
                }
                break;

            case TickType::DailyReset:
                if (null !== $team) {
                    $this->processDailyResetForTeam($team, $scheduledAt);
                }
                break;

            case TickType::LeagueMatch:
                if (null !== $fixture) {
                    // NPC tactics simulation for both teams
                    $this->npcSimulationService->simulateTactics($kingdom, $scheduledAt, $fixture->getHomeTeam());
                    $this->npcSimulationService->simulateTactics($kingdom, $scheduledAt, $fixture->getAwayTeam());

                    // Arena revenue and match resolution
                    $this->arenaRevenueService->payFixtureRevenue($fixture);
                    $this->leagueMatchResolutionService->resolveFixture($fixture, $scheduledAt);

                    // Clean up temporary formations for the fixture
                    $this->cleanupTemporaryFormationsForFixture($fixture);

                    $this->logger->debug(sprintf('Processed league match tick for fixture ID %d', $fixture->getId()));
                }
                break;

            case TickType::SeasonTransition:
                $this->seasonTransitionService->executeTransition($kingdom);
                break;
        }
    }

    private function processFatigueRecoveryForTeam(Team $team): void
    {
        $kingdom = $team->getKingdom();
        $speed = (float) $kingdom->getGameSpeed();

        // Base recovery amounts
        $fatigueReduction = (int) round(10 * $speed);
        $formIncrease = (int) round(5 * $speed);

        // Fetch heroes belonging to this Team that need recovery
        $qb = $this->heroRepository->createQueryBuilder('h')
            ->where('h.team = :team')
            ->andWhere('h.fatigue > 0 OR h.form < 100')
            ->setParameter('team', $team);

        /** @var list<\App\Entity\Hero\Hero> $heroes */
        $heroes = $qb->getQuery()->getResult();

        foreach ($heroes as $hero) {
            $newFatigue = max(0, $hero->getFatigue() - $fatigueReduction);
            $newForm = min(100, $hero->getForm() + $formIncrease);

            $hero->setFatigue($newFatigue);
            $hero->setForm($newForm);
        }
    }

    private function cleanupInactiveRegistrations(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): void
    {
        $threshold = $scheduledAt->modify('-1 day');

        /** @var list<User> $users */
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.kingdom = :kingdom')
            ->andWhere('u.isVerified = false')
            ->andWhere('u.createdAt < :threshold')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();

        foreach ($users as $user) {
            $team = $user->getTeam();
            if (null !== $team) {
                $this->teamChronicleService->recordPlayerReleased(
                    $team,
                    $user,
                    ChronicleReleaseReason::UnverifiedRegistration,
                );
                $team->setUser(null);
                $team->setIsNpc(true);
            }

            // Delete verification tokens
            $this->em->createQueryBuilder()
                ->delete(VerificationToken::class, 'vt')
                ->where('vt.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->execute();

            // Delete notifications
            $this->em->createQueryBuilder()
                ->delete(Notification::class, 'n')
                ->where('n.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->execute();

            // Delete the user
            $this->em->remove($user);

            $this->logger->info(sprintf(
                'Inactive registration cleaned up: user ID %d (email: %s), team released',
                $user->getId(),
                $user->getEmail()
            ));
        }
    }

    private function processDailyResetForTeam(Team $team, \DateTimeImmutable $scheduledAt): void
    {
        $kingdom = $team->getKingdom();
        $this->logger->debug(sprintf('Executing DailyReset for Team %s (Kingdom: %s)', $team->getName(), $kingdom->getName()));

        $this->cleanupStaleTemporaryFormationsForTeam($team);
        $this->fanClubService->processDailyEvolutionTick($kingdom, $team);
        $this->teamMoraleReputationService->processDailyEvolutionTick($kingdom, $team);
        $this->marketplaceService->processExpiredListingsForKingdom($kingdom, $scheduledAt, $team);

        // Process pending facility upgrades
        $this->hqService->processFacilityUpgradesTick($kingdom, $scheduledAt, $team);

        // Update hero and trainer aging
        $speed = (float) $kingdom->getGameSpeed();
        if ($speed <= 0.0) {
            $speed = 1.0;
        }
        $ageIncrement = (int) round(1 * $speed);

        if ($ageIncrement > 0) {
            // Fetch all heroes for this team that are not Undead
            /** @var list<\App\Entity\Hero\Hero> $heroes */
            $heroes = $this->em->getRepository(\App\Entity\Hero\Hero::class)
                ->createQueryBuilder('h')
                ->where('h.team = :team')
                ->andWhere('h.role = :combatant')
                ->andWhere('h.race != :undead')
                ->setParameter('team', $team)
                ->setParameter('combatant', \App\Enum\HeroRole::Combatant)
                ->setParameter('undead', \App\Enum\Race::Undead)
                ->getQuery()
                ->getResult();

            foreach ($heroes as $hero) {
                $hero->setAgeRaw($hero->getAgeRaw() + $ageIncrement);
            }
        }

        // Check if we need to pre-create the next season (Option A: Monday of Week 11)
        if (1 === (int) $scheduledAt->format('N')) {
            /** @var \App\Entity\League\LeagueSeason|null $activeSeason */
            $activeSeason = $this->em->getRepository(\App\Entity\League\LeagueSeason::class)->findOneBy([
                'kingdom' => $kingdom,
                'status' => \App\Enum\LeagueSeasonStatus::Active,
            ]);

            if (null !== $activeSeason) {
                $startDate = $activeSeason->getStartDate();
                $prepMonday = (1 === (int) $startDate->format('N'))
                    ? $startDate->setTime(0, 0, 0)
                    : $startDate->modify('next monday')->setTime(0, 0, 0);

                // Monday of Week 11 is prepMonday + 10 weeks
                $mondayWeek11 = $prepMonday->modify('+10 weeks');

                // If scheduledAt is equal to or after the Monday of Week 11
                if ($scheduledAt->setTime(0, 0, 0) >= $mondayWeek11) {
                    $this->seasonTransitionService->prepareUpcomingSeason($kingdom);
                }
            }
        }
    }

    private function resetWeeklySummons(Kingdom $kingdom, ?Team $team = null): void
    {
        if (null !== $team) {
            $team->setSummonsThisCycle(0);
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy(['kingdom' => $kingdom]);
            foreach ($teams as $team) {
                $team->setSummonsThisCycle(0);
            }
        }
    }

    private function cleanupStaleTemporaryFormationsForTeam(Team $team): void
    {
        $count = $this->fixtureFormationService->cleanupStaleTemporaryFormationsForTeam($team);
        if ($count > 0) {
            $this->logger->info(sprintf('Removed %d stale temporary formation(s) for Team %s', $count, $team->getName()));
        }
    }

    private function cleanupTemporaryFormationsForFixture(LeagueFixture $fixture): void
    {
        $count = $this->fixtureFormationService->cleanupTemporaryFormationsAfterCompletion($fixture);
        if ($count > 0) {
            $this->logger->info(sprintf('Removed %d stale temporary formation(s) for completed Fixture ID %d', $count, $fixture->getId()));
        }
    }
}
