<?php

declare(strict_types=1);

namespace App\Message;

use App\Entity\Auth\User;
use App\Entity\Auth\VerificationToken;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\Notification\Notification;
use App\Entity\Team\Team;
use App\Entity\Team\TeamDailySnapshot;
use App\Enum\ChronicleReleaseReason;
use App\Enum\TickType;
use App\Repository\Hero\HeroRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Service\Auth\PlayerInactivityService;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Calendar\TickClock;
use App\Service\Calendar\TickScheduleCalculator;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Economy\RoyalTreasuryService;
use App\Service\Formation\FixtureFormationService;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Kingdom\KingdomPace;
use App\Service\Team\FanClubService;
use App\Service\Team\NpcSimulationService;
use App\Service\Team\TeamMoraleReputationService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Training\TrainingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
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
        private readonly \App\Service\Economy\TeamPayrollService $teamPayrollService,
        private readonly PlayerInactivityService $playerInactivityService,
        private readonly FixtureFormationService $fixtureFormationService,
        private readonly \App\Service\League\LeagueMatchResolutionService $leagueMatchResolutionService,
        private readonly \App\Service\FriendlyMatch\FriendlyMatchResolutionService $friendlyMatchResolutionService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly \App\Service\League\SeasonTransitionService $seasonTransitionService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly TickClock $tickClock,
        private readonly NpcSimulationService $npcSimulationService,
        private readonly KingdomTickOrchestrator $orchestrator,
        private readonly \App\Service\Hero\HeroMasteryService $heroMasteryService,
        private readonly TickScheduleCalculator $scheduleCalculator,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'monolog.logger.tick_metrics')]
        private readonly ?LoggerInterface $metricsLogger = null,
    ) {
    }

    public function __invoke(ExecuteSingleTickMessage $message, bool $synchronous = false): void
    {
        $connection = $this->em->getConnection();
        $acquired = $connection->fetchOne('SELECT GET_LOCK(?, 0)', [$message->getLockName()]);
        if (0 === (int) $acquired && null !== $acquired && false !== $acquired) {
            throw new \Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException('Tick execution is already in progress.', retryDelay: 1000);
        }
        if (1 !== (int) $acquired) {
            throw new \RuntimeException('Unable to acquire the tick execution lock.');
        }

        try {
            $this->processTick($message, $synchronous);
        } finally {
            $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$message->getLockName()]);
        }
    }

    private function processTick(ExecuteSingleTickMessage $message, bool $synchronous): void
    {
        $tickLogId = $message->getTickLogId();
        /** @var KingdomTickLog|null $log */
        $log = $this->tickLogRepository->find($tickLogId);
        if (null === $log) {
            $this->logger->error(sprintf('ExecuteSingleTickHandler: KingdomTickLog with ID %d not found.', $tickLogId));

            return;
        }

        $kingdom = $log->getKingdom();

        if ($this->tickLogRepository->hasFailedTicks($kingdom)) {
            $this->logger->warning(sprintf(
                'ExecuteSingleTickHandler: Kingdom ID %d has failed tick(s). Halting queue execution.',
                $kingdom->getId()
            ));

            throw new \RuntimeException(sprintf('Kingdom ID %d has failed tick(s). Queue processing halted until state is repaired.', $kingdom->getId()));
        }

        // 1. Try to atomically acquire the tick by updating its status to 'processing'
        $qb = $this->em->createQueryBuilder()
            ->update(KingdomTickLog::class, 'l')
            ->set('l.status', ':processing')
            ->set('l.executedAt', ':now')
            ->where('l.id = :id')
            ->andWhere('l.status IN (:allowed_statuses)')
            ->setParameter('processing', 'processing')
            ->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
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
        $startedAt = hrtime(true);
        $memoryAtStart = memory_get_usage();
        $this->tickClock->setCustomTime($log->getScheduledAt());
        $this->em->beginTransaction();
        try {
            $this->executeTick($kingdom, $log);

            $isSimulatingLeagueMatch = false;
            if (TickType::LeagueMatch === $log->getTickType() && null !== $log->getFixture()) {
                $battle = $log->getFixture()->getBattle();
                if (null !== $battle && \App\Enum\BattleStatus::Simulating === $battle->getStatus()) {
                    $isSimulatingLeagueMatch = true;
                }
            }
            if (TickType::FriendlyMatch === $log->getTickType() && null !== $log->getFriendlyMatch()) {
                $battle = $log->getFriendlyMatch()->getBattle();
                if (null !== $battle && \App\Enum\BattleStatus::Simulating === $battle->getStatus()) {
                    $isSimulatingLeagueMatch = true;
                }
            }

            if (!$isSimulatingLeagueMatch) {
                $log->setStatus('completed');
                $log->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                $log->setErrorMessage(null);
            } else {
                $log->setStatus('awaiting_combat');
                $log->setExecutedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                $log->setErrorMessage(null);
            }

            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            $this->em->clear();

            if (!$synchronous && $e instanceof OptimisticLockException) {
                try {
                    if ($this->orchestrator->retryOptimisticConflict($tickLogId)) {
                        $this->logger->info(sprintf('Tick ID %d requeued after an optimistic-lock conflict.', $tickLogId));

                        return;
                    }
                } catch (\Throwable $retryException) {
                    $this->logger->error('Could not requeue an optimistic-lock conflict.', ['exception' => $retryException, 'tick_id' => $tickLogId]);
                }
            }

            try {
                $this->em->getConnection()->executeStatement(
                    'UPDATE kingdom_tick_log SET status = ?, executed_at = ?, error_message = ? WHERE id = ? AND status = ?',
                    ['failed', gmdate('Y-m-d H:i:s'), $e->getMessage()."\n".$e->getTraceAsString(), $tickLogId, 'processing'],
                );
            } catch (\Throwable $recordingException) {
                $this->logger->error('Could not record tick failure.', ['exception' => $recordingException, 'tick_id' => $tickLogId]);
            }

            $this->logger->error(sprintf('Tick %s failed for Kingdom %s: %s', $log->getTickType()->value, $kingdom->getName(), $e->getMessage()), [
                'exception' => $e,
                'tick_id' => $tickLogId,
                'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
                'memory_delta_bytes' => memory_get_usage() - $memoryAtStart,
            ]);

            // Halt the Messenger queue execution until repaired by re-throwing exception
            throw $e;
        } finally {
            $this->tickClock->setCustomTime(null);
        }

        $this->metricsLogger?->info('Tick execution metrics.', [
            'tick_id' => $tickLogId,
            'kingdom_id' => $kingdom->getId(),
            'tick_type' => $log->getTickType()->value,
            'team_id' => $log->getTeam()?->getId(),
            'fixture_id' => $log->getFixture()?->getId(),
            'friendly_match_id' => $log->getFriendlyMatch()?->getId(),
            'marketplace_batch' => $log->getMarketplaceBatch(),
            'duration_ms' => (hrtime(true) - $startedAt) / 1_000_000,
            'memory_delta_bytes' => memory_get_usage() - $memoryAtStart,
            'process_peak_memory_bytes' => memory_get_peak_usage(),
        ]);
        if (!$isSimulatingLeagueMatch) {
            $this->logger->info(sprintf('Tick %s completed successfully for Kingdom %s at scheduled time %s', $log->getTickType()->value, $kingdom->getName(), $log->getScheduledAt()->format('Y-m-d H:i:s')));
        } else {
            $this->logger->info(sprintf('Tick %s initiated asynchronous battle simulation for Kingdom %s at scheduled time %s', $log->getTickType()->value, $kingdom->getName(), $log->getScheduledAt()->format('Y-m-d H:i:s')));
        }

        if (!$synchronous || $isSimulatingLeagueMatch) {
            $this->orchestrator->orchestrate($kingdom);
        }
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
                    $this->trainingService->processTrainingTick($scheduledAt, $kingdom, $team);
                    $this->processTrainingMastery($team);
                }
                break;

            case TickType::WeeklyReset:
                if (null !== $team) {
                    // Team-scoped weekly reset — runs after kingdom treasury distribution.
                    $this->npcSimulationService->simulateWeeklyManagementAndEconomy($kingdom, $scheduledAt, $team);
                    $this->resetWeeklySummons($kingdom, $team);
                    $this->hqService->processMaintenanceTick($kingdom, $team);
                    $this->teamPayrollService->processPayrollTick($kingdom, $team);
                    $this->hqService->processFacilityDowngradeLockTick($kingdom, $team);
                    $this->financialCrisisService->processWeeklyCrisisTick($kingdom, $team);
                } else {
                    // Kingdom-scoped weekly reset: Royal Treasury distribution (first wave).
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
                } else {
                    $this->orchestrator->processDailyKingdomPhase($kingdom, $log);
                }
                break;

            case TickType::LeagueMatch:
                if (null !== $fixture) {
                    if (null === $fixture->getBattle()) {
                        $this->npcSimulationService->simulateTactics($kingdom, $scheduledAt, $fixture->getHomeTeam());
                        $this->npcSimulationService->simulateTactics($kingdom, $scheduledAt, $fixture->getAwayTeam());
                        $this->arenaRevenueService->payFixtureRevenue($fixture);
                    }
                    $this->leagueMatchResolutionService->resolveFixture($fixture, $scheduledAt, false);

                    $this->logger->debug(sprintf('Processed league match tick for fixture ID %d', $fixture->getId()));
                }
                break;

            case TickType::FriendlyMatch:
                $friendlyMatch = $log->getFriendlyMatch();
                if (null !== $friendlyMatch) {
                    // No NPC tactics simulation and no arena revenue for friendly matches
                    $this->friendlyMatchResolutionService->resolveMatch($friendlyMatch, $scheduledAt, false);

                    $this->logger->debug(sprintf('Processed friendly match tick for friendly match ID %d', $friendlyMatch->getId() ?? 0));
                }
                break;

            case TickType::SeasonTransition:
                $this->em->refresh($kingdom, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
                $this->seasonTransitionService->executeTransition($kingdom);
                $this->scheduleNewSeasonTicks($kingdom, $scheduledAt);
                break;
        }
    }

    private function scheduleNewSeasonTicks(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): void
    {
        $horizon = $kingdom->getTickPlanningHorizon();
        if (null === $horizon || $horizon <= $scheduledAt) {
            return;
        }

        $season = $this->em->getRepository(\App\Entity\League\LeagueSeason::class)->findOneBy([
            'kingdom' => $kingdom,
            'status' => \App\Enum\LeagueSeasonStatus::Active,
        ]);
        if (!$season instanceof \App\Entity\League\LeagueSeason) {
            throw new \RuntimeException('No active season found after transition.');
        }

        $events = [];
        foreach ($this->scheduleCalculator->generateOccurrences($scheduledAt, $horizon, $kingdom->getTimezone(), $season->getStartDate()) as $occurrence) {
            $type = $occurrence['type'];
            if (TickType::SeasonTransition === $type) {
                $scopes = [null];
            } elseif (TickType::LeagueMatch === $type) {
                $scopes = $this->em->getRepository(\App\Entity\League\LeagueFixture::class)
                    ->findScheduledFixturesAtTime($kingdom, $occurrence['time']);
            } else {
                continue;
            }

            foreach ($scopes as $fixture) {
                $criteria = [
                    'kingdom' => $kingdom,
                    'tickType' => $type,
                    'scheduledAt' => $occurrence['time'],
                    'team' => null,
                    'fixture' => $fixture,
                    'friendlyMatch' => null,
                ];
                if (null !== $this->tickLogRepository->findOneBy($criteria)) {
                    continue;
                }

                $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType($type)
                    ->setScheduledAt($occurrence['time'])->setFixture($fixture)->setStatus('pending');
                $this->tickLogRepository->attachToEvent($tick, $events);
                $this->em->persist($tick);
            }
        }
    }

    private function processFatigueRecoveryForTeam(Team $team): void
    {
        $speed = KingdomPace::recovery($team->getKingdom());

        $fatigueReductionPct = 0.0;
        $recoverySpeedPct = 0.0;

        try {
            if ($this->financialCrisisService->areHqBonusesActive($team)) {
                $hq = $this->hqService->getForTeam($team);
                foreach ($hq->getFacilities() as $facility) {
                    if (\App\Enum\FacilityType::Medical === $facility->getType()) {
                        $bonuses = $facility->getPassiveBonuses();
                        $fatigueReductionPct = (float) ($bonuses['fatigue_reduction_pct'] ?? 0.0);
                        $recoverySpeedPct = (float) ($bonuses['recovery_speed_pct'] ?? 0.0);
                        break;
                    }
                }
            }
        } catch (\Throwable) {
            // Safe fallback if HQ not initialized or accessible
        }

        // Base recovery amounts modified by Medical Wing facility bonuses
        $fatigueReduction = (int) round(10 * $speed * (1.0 + $fatigueReductionPct / 100.0));
        $formIncrease = (int) round(5 * $speed * (1.0 + $recoverySpeedPct / 100.0));

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

        // Create a daily values snapshot
        $dateOnly = $scheduledAt->setTime(0, 0, 0);
        $snapshotRepo = $this->em->getRepository(TeamDailySnapshot::class);
        $existingSnapshot = $snapshotRepo->findOneBy([
            'team' => $team,
            'recordedAt' => $dateOnly,
        ]);

        if (null === $existingSnapshot) {
            $snapshot = new TeamDailySnapshot();
            $snapshot->setTeam($team);
            $snapshot->setRecordedAt($dateOnly);
            $snapshot->setMorale($team->getMorale());
            $snapshot->setReputation($team->getReputation());
            $snapshot->setChemistry($team->getChemistry());
            $snapshot->setFanBase($team->getFanBase());

            $this->em->persist($snapshot);
        }

        // Clean up snapshots older than 30 days
        $snapshotRepo->deleteOlderThan($team, $scheduledAt->modify('-30 days'));

        // Process pending facility upgrades
        $this->hqService->processFacilityUpgradesTick($kingdom, $scheduledAt, $team);

        // Update hero and trainer aging
        $ageIncrement = (int) round(KingdomPace::aging($kingdom));

        if ($ageIncrement > 0) {
            // Fetch all heroes for this team that are not Undead
            /** @var list<\App\Entity\Hero\Hero> $heroes */
            $heroes = $this->em->getRepository(\App\Entity\Hero\Hero::class)
                ->createQueryBuilder('h')
                ->where('h.team = :team')
                ->andWhere('h.race != :undead')
                ->setParameter('team', $team)
                ->setParameter('undead', \App\Enum\Race::Undead)
                ->getQuery()
                ->getResult();

            foreach ($heroes as $hero) {
                $hero->setAgeRaw($hero->getAgeRaw() + $ageIncrement);
            }
        }

        // Process Daily Decay for Hero Mastery
        $combatants = $this->heroRepository->findCombatantsByTeam($team);
        foreach ($combatants as $hero) {
            $this->heroMasteryService->processDailyDecayTick($hero, $scheduledAt);
        }

        // Run daily NPC simulation actions
        if ($team->isNpc()) {
            $this->npcSimulationService->simulateDailyManagementAndEconomy($kingdom, $scheduledAt, $team);

            // Determine local day of the week to run weekly/twice-weekly tasks
            $tz = new \DateTimeZone($kingdom->getTimezone());
            $localTime = $scheduledAt->setTimezone($tz);
            $localDay = (int) $localTime->format('N');

            if (2 === $localDay || 5 === $localDay) {
                $this->npcSimulationService->simulateMarketplaceActions($kingdom, $scheduledAt, $team);
            }

            if (2 === $localDay) {
                $this->npcSimulationService->simulateTraining($kingdom, $scheduledAt, $team);
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

    private function processTrainingMastery(Team $team): void
    {
        // Find all combatant heroes of the team who have a trainer and that trainer is training MAGIC
        $heroes = $this->heroRepository->createQueryBuilder('h')
            ->join('h.trainer', 't')
            ->where('h.team = :team')
            ->andWhere('h.role = :combatant')
            ->andWhere('t.trainingType = :magicTraining')
            ->setParameter('team', $team)
            ->setParameter('combatant', \App\Enum\HeroRole::Combatant)
            ->setParameter('magicTraining', \App\Enum\TrainingType::Magic)
            ->getQuery()
            ->getResult();

        foreach ($heroes as $hero) {
            // 1. Award +50 attunement progress to equipped gear sub-types
            $equippedSubTypes = $this->heroMasteryService->getEquippedSubTypes($hero);
            foreach ($equippedSubTypes as $subType) {
                $wm = $this->heroMasteryService->getOrCreateWeaponMastery($hero, $subType);
                $wm->setAttunementProgress(min(100, $wm->getAttunementProgress() + 50));
            }

            // 2. Award +25 XP to equipped magic school masteries
            $equippedSchools = $this->heroMasteryService->getEquippedSpellSchools($hero);
            foreach ($equippedSchools as $school) {
                $this->heroMasteryService->addSchoolMasteryXp($hero, $school, 25);
            }
        }
    }
}
