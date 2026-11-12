<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Combat\Battle;
use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueTier;
use App\Entity\Team\Team;
use App\Enum\BattleStatus;
use App\Enum\MatchType;
use App\Enum\LeagueSeasonStatus;
use App\Enum\TickType;
use App\Message\ExecuteSingleTickHandler;
use App\Message\ExecuteSingleTickMessage;
use App\Repository\Kingdom\KingdomTickLogRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KingdomTickLogRepositoryTest extends KernelTestCase
{
    private ?\Doctrine\ORM\EntityManagerInterface $em = null;
    private ?KingdomTickLogRepository $repository = null;
    private ?Kingdom $kingdom = null;
    /** @var array<string, \App\Entity\Kingdom\KingdomTickEvent> */
    private array $events = [];

    private function persistTick(KingdomTickLog $tick): void
    {
        self::assertNotNull($this->repository);
        self::assertNotNull($this->em);
        $this->repository->attachToEvent($tick, $this->events);
        $this->em->persist($tick);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $this->em = $em;

        /** @var KingdomTickLogRepository $repository */
        $repository = $container->get(KingdomTickLogRepository::class);
        $this->repository = $repository;

        // Create a temporary kingdom
        $kingdom = new Kingdom();
        $kingdom->setName('Stale Test Kingdom');
        $kingdom->setLanguage('cs');
        $kingdom->setTimezone('UTC');
        $this->kingdom = $kingdom;

        $em->persist($kingdom);
        $em->flush();
    }

    protected function tearDown(): void
    {
        $em = $this->em;
        $repository = $this->repository;
        $kingdom = $this->kingdom;

        if ($em !== null && $repository !== null && $kingdom !== null) {
            $logs = $repository->findBy(['kingdom' => $kingdom]);
            foreach ($logs as $log) {
                $em->remove($log);
            }
            foreach ($em->getRepository(\App\Entity\Kingdom\KingdomTickEvent::class)->findBy(['kingdom' => $kingdom]) as $event) {
                $em->remove($event);
            }
            $em->remove($kingdom);
            $em->flush();
            $em->close();
        }

        $this->em = null;
        $this->repository = null;
        $this->kingdom = null;

        restore_exception_handler();
        parent::tearDown();
    }

    private function restoreKingdom(?int $kingdomId): void
    {
        self::assertNotNull($this->em);
        $kingdom = $this->em->find(Kingdom::class, $kingdomId);
        self::assertInstanceOf(Kingdom::class, $kingdom);
        $this->kingdom = $kingdom;
    }

    public function testSeasonTransitionSchedulesFixturesAtExactRequestedHorizon(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $horizon = new \DateTimeImmutable('2026-03-31 18:00:00 UTC');
            $kingdom->setSeasonLength(77)->setTickPlanningHorizon($horizon)->setLeagueTiersConfig([
                'teams_per_group' => 10,
                'tiers' => [['name' => 'T1', 'groups' => ['1-A'], 'promotion_slots' => 0, 'relegation_slots' => 0]],
            ]);
            $season = (new LeagueSeason())->setKingdom($kingdom)->setSeasonNumber(1)
                ->setStartDate(new \DateTimeImmutable('2026-01-05 UTC'))
                ->setEndDate(new \DateTimeImmutable('2026-03-22 UTC'))->setStatus(LeagueSeasonStatus::Active);
            $tier = (new LeagueTier())->setSeason($season)->setTierName('T1')->setPromotionSlots(0)->setRelegationSlots(0);
            $season->addTier($tier);
            $group = (new LeagueGroup())->setTier($tier)->setGroupName('1-A');
            $tier->addGroup($group);
            foreach ([$season, $tier, $group] as $entity) {
                $em->persist($entity);
            }
            for ($teamNumber = 1; $teamNumber <= 10; ++$teamNumber) {
                $team = (new Team())->setKingdom($kingdom)->setName('Transition team '.$teamNumber);
                $standing = (new \App\Entity\League\LeagueStanding())->setGroup($group)->setTeam($team);
                $group->getStandings()->add($standing);
                $em->persist($team);
                $em->persist($standing);
            }
            $transition = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::SeasonTransition)
                ->setScheduledAt(new \DateTimeImmutable('2026-03-20 19:00:00 UTC'))->setStatus('pending');
            $this->persistTick($transition);
            $em->flush();

            $handler = self::getContainer()->get(ExecuteSingleTickHandler::class);
            $handler(new ExecuteSingleTickMessage((int) $transition->getId()), true);

            self::assertSame('completed', $transition->getStatus());
            $matchTicks = $this->repository->findBy([
                'kingdom' => $kingdom,
                'tickType' => TickType::LeagueMatch,
                'scheduledAt' => $horizon,
                'status' => 'pending',
            ]);
            self::assertCount(5, $matchTicks);
            foreach ($matchTicks as $matchTick) {
                self::assertNotNull($matchTick->getFixture());
            }
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testDailyResetSchedulesDistinctKingdomAndTeamPhases(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $team = (new Team())->setKingdom($kingdom)->setName('Daily phase team');
            $em->persist($team);
            $em->flush();
            $until = new \DateTimeImmutable('2030-01-02 00:00:00 UTC');
            $runner = self::getContainer()->get(\App\Service\Calendar\KingdomTickRunnerService::class);
            $runner->schedulePendingTicks($kingdom, $until);
            $runner->schedulePendingTicks($kingdom, $until);
            $logs = $this->repository->findBy(['kingdom' => $kingdom, 'tickType' => TickType::DailyReset, 'scheduledAt' => $until]);
            self::assertCount(2, $logs);
            self::assertCount(1, array_filter($logs, static fn (KingdomTickLog $log): bool => null === $log->getTeam()));
            self::assertCount(1, array_filter($logs, static fn (KingdomTickLog $log): bool => $log->getTeam() === $team));
            $event = $logs[0]->getEvent();
            self::assertNotNull($event);
            self::assertSame($event, $logs[1]->getEvent());
            $columns = $em->getConnection()->createSchemaManager()->listTableColumns('kingdom_tick_log');
            self::assertArrayNotHasKey('kingdom_id', $columns);
            self::assertArrayNotHasKey('tick_type', $columns);
            self::assertArrayNotHasKey('scheduled_at', $columns);
            foreach ([null, $team->getId()] as $teamId) {
                try {
                    $em->getConnection()->executeStatement(
                        'INSERT INTO kingdom_tick_log (event_id, team_id, kingdom_scope, status, executed_at, retry_count) VALUES (?, ?, ?, ?, ?, ?)',
                        [$event->getId(), $teamId, null === $teamId ? 1 : null, 'pending', '2030-01-02 00:00:00', 0],
                    );
                    self::fail('Duplicate event scopes must be rejected by the database.');
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                    self::addToAssertionCount(1);
                }
            }
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testMessengerProcessesKingdomDailyPhaseBeforeTeamPhase(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $team = (new Team())->setKingdom($kingdom)->setName('Messenger daily team');
            $scheduledAt = new \DateTimeImmutable('2030-01-02 00:00:00 UTC');
            $kingdomTick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt);
            $teamTick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt)->setTeam($team);
            $em->persist($team);
            for ($index = 0; $index < 26; ++$index) {
                $em->persist((new \App\Entity\Marketplace\MarketplaceListing())->setKingdom($kingdom)->setSellerTeam($team)
                    ->setListingType(\App\Enum\ListingType::Item)->setListingMode(\App\Enum\ListingMode::Auction)
                    ->setPriceGold(10)->setExpiresAt($scheduledAt));
            }
            $this->persistTick($teamTick);
            $this->persistTick($kingdomTick);
            $em->flush();
            $container = self::getContainer();
            $queue = 'tick_test_'.bin2hex(random_bytes(8));
            $transport = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport(
                new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection(['queue_name' => $queue, 'auto_setup' => false], $em->getConnection()),
                new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer(),
            );
            $container->set('messenger.transport.async', $transport);
            $container->get(\App\Service\Calendar\KingdomTickOrchestrator::class)->orchestrate($kingdom);
            $bus = $container->get('messenger.default_bus');

            for ($phase = 0; $phase < 3; ++$phase) {
                $messages = iterator_to_array($transport->get());
                self::assertCount(1, $messages);
                $envelope = reset($messages);
                self::assertInstanceOf(ExecuteSingleTickMessage::class, $envelope->getMessage());
                $expectedTick = match ($phase) {
                    0 => $kingdomTick,
                    1 => $this->repository->findOneBy(['event' => $kingdomTick->getEvent(), 'marketplaceBatch' => 1]),
                    2 => $teamTick,
                };
                self::assertNotNull($expectedTick);
                self::assertSame($expectedTick->getId(), $envelope->getMessage()->getTickLogId());
                $bus->dispatch($envelope->with(new \Symfony\Component\Messenger\Stamp\ReceivedStamp('async')));
                $transport->ack($envelope);
                $em->refresh($expectedTick);
                self::assertSame('completed', $expectedTick->getStatus());
                if (0 === $phase) {
                    self::assertSame('pending', $teamTick->getStatus());
                } elseif (1 === $phase) {
                    self::assertSame('dispatched', $teamTick->getStatus());
                }
            }
            self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', [$queue]));
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testMarketplaceBatchesFinishBeforeDailyTeamPhase(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $scheduledAt = new \DateTimeImmutable('2030-01-02 00:00:00 UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Marketplace batch team');
            $em->persist($team);
            $listings = [];
            for ($index = 0; $index < 26; ++$index) {
                $listing = (new \App\Entity\Marketplace\MarketplaceListing())->setKingdom($kingdom)->setSellerTeam($team)
                    ->setListingType(\App\Enum\ListingType::Item)->setListingMode(\App\Enum\ListingMode::Auction)
                    ->setPriceGold(10)->setExpiresAt($scheduledAt);
                $em->persist($listing);
                $listings[] = $listing;
            }
            $kingdomTick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt);
            $teamTick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt)->setTeam($team);
            $this->persistTick($kingdomTick);
            $this->persistTick($teamTick);
            $em->flush();

            $handler = self::getContainer()->get(ExecuteSingleTickHandler::class);
            $handler(new ExecuteSingleTickMessage((int) $kingdomTick->getId()), true);
            foreach (array_slice($listings, 0, 25) as $listing) {
                self::assertSame(\App\Enum\ListingStatus::Expired, $listing->getStatus());
            }
            self::assertSame(\App\Enum\ListingStatus::Active, $listings[25]->getStatus());
            self::assertSame('pending', $teamTick->getStatus());
            $continuation = $this->repository->findOneBy(['event' => $kingdomTick->getEvent(), 'marketplaceBatch' => 1]);
            self::assertNotNull($continuation);
            self::assertSame($listings[25]->getId(), $continuation->getMarketplaceMaxListingId());
            $event = $kingdomTick->getEvent();
            self::assertNotNull($event);
            try {
                $em->getConnection()->executeStatement(
                    'INSERT INTO kingdom_tick_log (event_id, kingdom_scope, marketplace_batch, marketplace_max_listing_id, status, executed_at, retry_count) VALUES (?, NULL, ?, ?, ?, ?, ?)',
                    [$event->getId(), 1, $continuation->getMarketplaceMaxListingId(), 'pending', '2030-01-02 00:00:00', 0],
                );
                self::fail('Duplicate marketplace batches must be rejected.');
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                self::addToAssertionCount(1);
            }

            $later = (new \App\Entity\Marketplace\MarketplaceListing())->setKingdom($kingdom)->setSellerTeam($team)
                ->setListingType(\App\Enum\ListingType::Item)->setListingMode(\App\Enum\ListingMode::Auction)
                ->setPriceGold(10)->setExpiresAt($scheduledAt);
            $em->persist($later);
            $em->flush();
            $handler(new ExecuteSingleTickMessage((int) $kingdomTick->getId()), true);
            $handler(new ExecuteSingleTickMessage((int) $continuation->getId()), true);

            self::assertSame(\App\Enum\ListingStatus::Expired, $listings[25]->getStatus());
            self::assertSame(\App\Enum\ListingStatus::Active, $later->getStatus());
            self::assertSame('completed', $continuation->getStatus());
            self::assertSame('pending', $teamTick->getStatus());
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testSynchronousCatchUpReloadsMarketplaceContinuationsBeforeTeams(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $scheduledAt = new \DateTimeImmutable('2030-01-02 00:00:00 UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Synchronous marketplace team')->setIsNpc(false);
            $em->persist($team);
            for ($index = 0; $index < 26; ++$index) {
                $em->persist((new \App\Entity\Marketplace\MarketplaceListing())->setKingdom($kingdom)->setSellerTeam($team)
                    ->setListingType(\App\Enum\ListingType::Item)->setListingMode(\App\Enum\ListingMode::Auction)
                    ->setPriceGold(10)->setExpiresAt($scheduledAt));
            }
            $kingdomTick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt);
            $teamTick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt)->setTeam($team);
            $this->persistTick($teamTick);
            $this->persistTick($kingdomTick);
            $em->flush();

            self::getContainer()->get(\App\Service\Calendar\KingdomTickRunnerService::class)->processPendingTicksSynchronously($kingdom);

            $continuation = $this->repository->findOneBy(['event' => $kingdomTick->getEvent(), 'marketplaceBatch' => 1]);
            self::assertNotNull($continuation);
            self::assertSame('completed', $continuation->getStatus());
            self::assertSame('completed', $teamTick->getStatus());
            self::assertSame(0, (int) $em->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM marketplace_listing WHERE kingdom_id = ? AND status = ?',
                [$kingdomId, 'active'],
            ));
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testMarketplaceBatchFailureRollsBackItsChanges(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $scheduledAt = new \DateTimeImmutable('2030-01-02 00:00:00 UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Rollback marketplace team');
            $listing = (new \App\Entity\Marketplace\MarketplaceListing())->setKingdom($kingdom)->setSellerTeam($team)
                ->setListingType(\App\Enum\ListingType::Item)->setListingMode(\App\Enum\ListingMode::Auction)
                ->setPriceGold(10)->setExpiresAt($scheduledAt);
            $em->persist($team);
            $em->persist($listing);
            $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($scheduledAt);
            $this->persistTick($tick);
            $em->flush();

            $realMarketplace = self::getContainer()->get(\App\Service\Marketplace\MarketplaceService::class);
            $marketplace = $this->createMock(\App\Service\Marketplace\MarketplaceService::class);
            $marketplace->expects($this->once())->method('getMaximumListingId')->willReturn((int) $listing->getId());
            $marketplace->expects($this->once())->method('processExpiredListingsBatch')->willReturnCallback(
                static function (Kingdom $currentKingdom, \DateTimeImmutable $time, int $maximumId, int $limit) use ($realMarketplace): int {
                    $realMarketplace->processExpiredListingsBatch($currentKingdom, $time, $maximumId, $limit);
                    throw new \RuntimeException('Failure after settlement flush');
                },
            );
            $orchestrator = new \App\Service\Calendar\KingdomTickOrchestrator(
                $this->repository,
                $em,
                $this->createStub(\Symfony\Component\Messenger\MessageBusInterface::class),
                $this->createStub(\Psr\Log\LoggerInterface::class),
                $this->createStub(\App\Service\League\SeasonTransitionService::class),
                $marketplace,
                new \App\Service\Calendar\TickClock(),
            );
            try {
                $orchestrator->processDailyKingdomPhase($kingdom, $tick);
                self::fail('The batch must fail after its flush.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Failure after settlement flush', $exception->getMessage());
            }
            $em->refresh($listing);
            self::assertSame(\App\Enum\ListingStatus::Active, $listing->getStatus());
            self::assertNull($this->repository->findOneBy(['event' => $tick->getEvent(), 'marketplaceBatch' => 1]));
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testSyntheticTickAndCombatMetrics(): void
    {
        if ('1' !== getenv('FANTAGER_PERFORMANCE_REPORT')) {
            self::markTestSkipped('Set FANTAGER_PERFORMANCE_REPORT=1 to run the synthetic performance sample.');
        }
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        self::assertNotNull(self::$kernel);
        if ('test' !== self::$kernel->getEnvironment() || !$em->getConnection()->getDatabase()
            || getenv('FANTAGER_BENCHMARK_DATABASE') !== $em->getConnection()->getDatabase()) {
            self::markTestSkipped('Set FANTAGER_BENCHMARK_DATABASE to the name of an isolated test database.');
        }
        $kingdomId = (int) $kingdom->getId();
        $capture = new \Monolog\Handler\TestHandler(\Monolog\Level::Info);
        self::getContainer()->set('monolog.logger.tick_metrics', new \Monolog\Logger('tick_metrics', [$capture]));
        $em->beginTransaction();
        try {
            $time = new \DateTimeImmutable('2030-01-02 UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Performance sample team');
            $em->persist($team);
            for ($index = 0; $index < 26; ++$index) {
                $em->persist((new \App\Entity\Marketplace\MarketplaceListing())->setKingdom($kingdom)->setSellerTeam($team)
                    ->setListingType(\App\Enum\ListingType::Item)->setListingMode(\App\Enum\ListingMode::Auction)
                    ->setPriceGold(10)->setExpiresAt($time));
            }
            $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)->setScheduledAt($time);
            $this->persistTick($tick);
            $em->flush();
            $handler = self::getContainer()->get(ExecuteSingleTickHandler::class);
            $handler(new ExecuteSingleTickMessage((int) $tick->getId()), true);
            $continuation = $this->repository->findOneBy(['event' => $tick->getEvent(), 'marketplaceBatch' => 1]);
            self::assertNotNull($continuation);
            $handler(new ExecuteSingleTickMessage((int) $continuation->getId()), true);

            $side = static function (int $heroBase) use ($team): \App\ValueObject\Combat\CombatSide {
                $combatants = [];
                foreach (\App\Enum\FormationPosition::cases() as $index => $position) {
                    $combatants[] = new \App\ValueObject\Combat\CombatantSnapshot(
                        $heroBase + $index, 'Benchmark hero', $position, \App\Enum\Race::Human,
                        5, 100, 0, 50, new \App\ValueObject\Combat\DerivedCombatStats(1000, 1000, 1, 0, 10, 0.1, 10, 0.1, 10, 75.0, 30.0, 0.0),
                    );
                }

                return new \App\ValueObject\Combat\CombatSide((int) $team->getId(), null, \App\Enum\FormationApproach::Balanced, $combatants);
            };
            $state = (new \App\Service\Combat\CombatEngine())->initializeRunState(new \App\ValueObject\Combat\CombatMatchRequest($side(100), $side(200), MatchType::Friendly, 42));
            for ($index = 0; $index < 2; ++$index) {
                $battle = (new Battle())->setKingdom($kingdom)->setTeamA($team)->setTeamB($team)
                    ->setMatchType(MatchType::Friendly)->setStatus(BattleStatus::Simulating)->setScheduledAt($time)->setCurrentRound(0)->setRunState($state);
                $match = (new FriendlyMatch())->setKingdom($kingdom)->setHomeTeam($team)->setAwayTeam($team)->setScheduledAt($time)->setBattle($battle);
                $combatTick = (new KingdomTickLog())->setKingdom($kingdom)->setFriendlyMatch($match)->setTickType(TickType::FriendlyMatch)
                    ->setScheduledAt($time)->setStatus('processing');
                $em->persist($battle);
                $em->persist($match);
                $this->persistTick($combatTick);
            }
            $em->flush();
            self::getContainer()->get(\App\Service\Combat\CombatWaveProcessor::class)->process(new \App\Message\CombatWave($kingdomId, $time, 1));

            $records = array_map(static fn (\Monolog\LogRecord $record): array => [
                'event' => $record->message,
                'context' => array_intersect_key($record->context, array_flip(['tick_type', 'duration_ms', 'batch', 'listing_count', 'round_attempts', 'battle_count', 'memory_delta_bytes', 'process_peak_memory_bytes'])),
            ], $capture->getRecords());
            self::assertCount(5, $records);
            fwrite(STDOUT, "\n".json_encode($records, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
        } finally {
            $em->rollBack();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testKilledMessengerWorkerRecoversTickWithoutDuplicatingSnapshot(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $connection = $em->getConnection();
        self::assertNotNull(self::$kernel);
        if ('test' !== self::$kernel->getEnvironment() || !$connection->getDatabase()
            || getenv('FANTAGER_CRASH_TEST_DATABASE') !== $connection->getDatabase()) {
            self::markTestSkipped('Set FANTAGER_CRASH_TEST_DATABASE to the name of an isolated test database.');
        }
        if (0 !== (int) $connection->fetchOne('SELECT COUNT(*) FROM messenger_messages')) {
            self::markTestSkipped('The isolated Messenger queue must be empty.');
        }

        $team = (new Team())->setKingdom($kingdom)->setName('Crash recovery team');
        $em->persist($team);
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTeam($team)->setTickType(TickType::DailyReset)
            ->setScheduledAt(new \DateTimeImmutable('2030-01-02 UTC'))->setStatus('dispatched');
        $this->persistTick($tick);
        $em->flush();
        $tickId = (int) $tick->getId();
        $teamId = (int) $team->getId();
        $lockName = (new ExecuteSingleTickMessage($tickId))->getLockName();
        $locker = \Doctrine\DBAL\DriverManager::getConnection($connection->getParams());
        $worker = null;
        $messageId = null;
        try {
            $locker->beginTransaction();
            $locker->fetchOne('SELECT id FROM team WHERE id = ? FOR UPDATE', [$teamId]);
            self::getContainer()->get('messenger.bus.default')->dispatch(new ExecuteSingleTickMessage($tickId));
            $messageId = (int) $connection->fetchOne('SELECT MAX(id) FROM messenger_messages');
            $worker = new \Symfony\Component\Process\Process([PHP_BINARY, 'bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=15', '--no-debug', '-vv'], dirname(__DIR__, 2), ['APP_ENV' => 'test', 'DATABASE_URL' => getenv('DATABASE_URL')]);
            $worker->start();
            $deadline = microtime(true) + 10;
            $blocked = false;
            while (microtime(true) < $deadline && $worker->isRunning()) {
                $blocked = (int) $connection->fetchOne(
                    "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID = IS_USED_LOCK(?) AND INFO LIKE 'UPDATE team SET %'",
                    [$lockName],
                ) > 0;
                if ($blocked) {
                    break;
                }
                usleep(50_000);
            }
            self::assertTrue($blocked, 'Worker did not reach the locked team write; tick status: '.$connection->fetchOne('SELECT status FROM kingdom_tick_log WHERE id = ?', [$tickId]).'; process: '.json_encode($connection->fetchAssociative('SELECT STATE, INFO FROM information_schema.PROCESSLIST WHERE ID = IS_USED_LOCK(?)', [$lockName])).'; output: '.$worker->getOutput().' '.$worker->getErrorOutput());
            $worker->stop(0, 9);
            $locker->rollBack();
            $deadline = microtime(true) + 5;
            do {
                $owner = $connection->fetchOne('SELECT IS_USED_LOCK(?)', [$lockName]);
                if (null === $owner) {
                    break;
                }
                usleep(50_000);
            } while (microtime(true) < $deadline);
            self::assertNull($owner);
            self::assertSame('processing', $connection->fetchOne('SELECT status FROM kingdom_tick_log WHERE id = ?', [$tickId]));
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM team_daily_snapshot WHERE team_id = ?', [$teamId]));

            $connection->executeStatement('UPDATE kingdom_tick_log SET executed_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $tickId]);
            $orchestrator = self::getContainer()->get(\App\Service\Calendar\KingdomTickOrchestrator::class);
            self::assertSame(1, $orchestrator->recoverInterruptedTicks($kingdom));
            $orchestrator->orchestrate($kingdom);
            $worker = new \Symfony\Component\Process\Process([PHP_BINARY, 'bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=15', '--no-debug'], dirname(__DIR__, 2));
            $worker->mustRun();
            self::assertSame('completed', $connection->fetchOne('SELECT status FROM kingdom_tick_log WHERE id = ?', [$tickId]));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM team_daily_snapshot WHERE team_id = ?', [$teamId]));

            $connection->executeStatement('UPDATE messenger_messages SET delivered_at = NULL, available_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $messageId]);
            $worker = new \Symfony\Component\Process\Process([PHP_BINARY, 'bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=15', '--no-debug'], dirname(__DIR__, 2));
            $worker->mustRun();
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM team_daily_snapshot WHERE team_id = ?', [$teamId]));
        } finally {
            if (null !== $worker && $worker->isRunning()) {
                $worker->stop(0, 9);
            }
            if ($locker->isTransactionActive()) {
                $locker->rollBack();
            }
            $locker->close();
            if (null !== $messageId) {
                $connection->executeStatement('DELETE FROM messenger_messages WHERE id = ?', [$messageId]);
            }
            $connection->executeStatement('DELETE FROM team_daily_snapshot WHERE team_id = ?', [$teamId]);
            $em->remove($tick);
            $em->flush();
            $em->remove($team);
            $em->flush();
        }
    }

    public function testKilledCombatWorkerRollsBackPartialBatchAndContinues(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $connection = $em->getConnection();
        self::assertNotNull(self::$kernel);
        if ('test' !== self::$kernel->getEnvironment() || !$connection->getDatabase()
            || getenv('FANTAGER_CRASH_TEST_DATABASE') !== $connection->getDatabase()) {
            self::markTestSkipped('Set FANTAGER_CRASH_TEST_DATABASE to the name of an isolated test database.');
        }
        if (0 !== (int) $connection->fetchOne('SELECT COUNT(*) FROM messenger_messages')) {
            self::markTestSkipped('The isolated Messenger queue must be empty.');
        }

        $team = (new Team())->setKingdom($kingdom)->setName('Combat crash team');
        $em->persist($team);
        $em->flush();
        $side = static function (int $heroBase) use ($team): \App\ValueObject\Combat\CombatSide {
            $combatants = [];
            foreach (\App\Enum\FormationPosition::cases() as $index => $position) {
                $combatants[] = new \App\ValueObject\Combat\CombatantSnapshot(
                    $heroBase + $index, 'Crash hero '.($heroBase + $index), $position, \App\Enum\Race::Human,
                    5, 100, 0, 50, new \App\ValueObject\Combat\DerivedCombatStats(1000, 1000, 1, 0, 10, 0.1, 10, 0.1, 10, 75.0, 30.0, 0.0),
                );
            }

            return new \App\ValueObject\Combat\CombatSide((int) $team->getId(), null, \App\Enum\FormationApproach::Balanced, $combatants);
        };
        $state = (new \App\Service\Combat\CombatEngine())->initializeRunState(new \App\ValueObject\Combat\CombatMatchRequest($side(100), $side(200), MatchType::Friendly, 42));
        $time = new \DateTimeImmutable('2030-01-02 UTC');
        $battles = [];
        $matches = [];
        $ticks = [];
        for ($index = 0; $index < 2; ++$index) {
            $battle = (new Battle())->setKingdom($kingdom)->setTeamA($team)->setTeamB($team)
                ->setMatchType(MatchType::Friendly)->setStatus(BattleStatus::Simulating)->setScheduledAt($time)->setCurrentRound(0)->setRunState($state);
            $match = (new FriendlyMatch())->setKingdom($kingdom)->setHomeTeam($team)->setAwayTeam($team)->setScheduledAt($time)->setBattle($battle);
            $tick = (new KingdomTickLog())->setKingdom($kingdom)->setFriendlyMatch($match)->setTickType(TickType::FriendlyMatch)
                ->setScheduledAt($time)->setStatus('processing')->setExecutedAt(new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC')));
            $em->persist($battle);
            $em->persist($match);
            $this->persistTick($tick);
            $battles[] = $battle;
            $matches[] = $match;
            $ticks[] = $tick;
        }
        $em->flush();
        $firstId = (int) $battles[0]->getId();
        $secondId = (int) $battles[1]->getId();
        $message = new \App\Message\CombatWave((int) $kingdom->getId(), $time, 1);
        $locker = \Doctrine\DBAL\DriverManager::getConnection($connection->getParams());
        $observer = \Doctrine\DBAL\DriverManager::getConnection($connection->getParams());
        $worker = null;
        $messageId = null;
        try {
            $observer->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            $locker->beginTransaction();
            $locker->fetchOne('SELECT id FROM combat_battle WHERE id = ? FOR UPDATE', [$secondId]);
            self::getContainer()->get('messenger.bus.default')->dispatch($message);
            $messageId = (int) $connection->fetchOne('SELECT MAX(id) FROM messenger_messages');
            $environment = ['APP_ENV' => 'test', 'DATABASE_URL' => getenv('DATABASE_URL')];
            $worker = new \Symfony\Component\Process\Process([PHP_BINARY, 'bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=15', '--no-debug'], dirname(__DIR__, 2), $environment);
            $worker->start();
            $deadline = microtime(true) + 10;
            $partial = false;
            while (microtime(true) < $deadline && $worker->isRunning()) {
                $partial = 1 === (int) $observer->fetchOne('SELECT current_round FROM combat_battle WHERE id = ?', [$firstId])
                    && null !== $connection->fetchOne('SELECT IS_USED_LOCK(?)', [$message->getLockName()]);
                if ($partial) {
                    break;
                }
                usleep(50_000);
            }
            self::assertTrue($partial, 'Worker did not process the first round: '.$worker->getErrorOutput());
            $worker->stop(0, 9);
            $locker->rollBack();
            $deadline = microtime(true) + 5;
            do {
                $owner = $connection->fetchOne('SELECT IS_USED_LOCK(?)', [$message->getLockName()]);
                if (null === $owner) {
                    break;
                }
                usleep(50_000);
            } while (microtime(true) < $deadline);
            self::assertNull($owner);
            self::assertSame(0, (int) $connection->fetchOne('SELECT current_round FROM combat_battle WHERE id = ?', [$firstId]));
            self::assertSame(0, (int) $connection->fetchOne('SELECT current_round FROM combat_battle WHERE id = ?', [$secondId]));
            $orchestrator = self::getContainer()->get(\App\Service\Calendar\KingdomTickOrchestrator::class);
            self::assertTrue($orchestrator->recoverCombatContinuation($kingdom));
            $worker = new \Symfony\Component\Process\Process([PHP_BINARY, 'bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=15', '--no-debug'], dirname(__DIR__, 2), $environment);
            $worker->mustRun();
            self::assertGreaterThan(0, (int) $connection->fetchOne('SELECT current_round FROM combat_battle WHERE id = ?', [$firstId]));
            self::assertGreaterThan(0, (int) $connection->fetchOne('SELECT current_round FROM combat_battle WHERE id = ?', [$secondId]));
            $connection->executeStatement('UPDATE messenger_messages SET delivered_at = NULL, available_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $messageId]);
            $worker = new \Symfony\Component\Process\Process([PHP_BINARY, 'bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=15', '--no-debug'], dirname(__DIR__, 2), $environment);
            $worker->mustRun();
            foreach ([$firstId, $secondId] as $battleId) {
                $runState = json_decode((string) $connection->fetchOne('SELECT run_state FROM combat_battle WHERE id = ?', [$battleId]), true, flags: JSON_THROW_ON_ERROR);
                $rounds = array_column(array_filter($runState['events'], static fn (array $event): bool => 'round_start' === $event['type']), 'round');
                self::assertCount(count(array_unique($rounds)), $rounds);
            }
        } finally {
            if (null !== $worker && $worker->isRunning()) {
                $worker->stop(0, 9);
            }
            if ($locker->isTransactionActive()) {
                $locker->rollBack();
            }
            $locker->close();
            $observer->close();
            if (null !== $messageId) {
                $connection->executeStatement('DELETE FROM messenger_messages WHERE id >= ? AND queue_name = ?', [$messageId, 'default']);
            }
            foreach ($ticks as $tick) {
                $em->remove($tick);
            }
            foreach ($matches as $match) {
                $em->remove($match);
            }
            $em->flush();
            foreach ($battles as $battle) {
                $em->remove($battle);
            }
            $em->remove($team);
            $em->flush();
        }
    }

    public function testTickHealthMonitorFindsOldWorkAndDeduplicatesAlerts(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = (int) $kingdom->getId();
        $em->beginTransaction();
        try {
            $failed = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
                ->setScheduledAt(new \DateTimeImmutable('2030-01-02 UTC'))->setStatus('failed');
            $stale = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
                ->setScheduledAt(new \DateTimeImmutable('2030-01-03 UTC'))->setStatus('processing')
                ->setExecutedAt(new \DateTimeImmutable('-20 minutes', new \DateTimeZone('UTC')));
            $this->persistTick($failed);
            $this->persistTick($stale);
            $em->flush();
            self::getContainer()->get('messenger.bus.default')->dispatch(new ExecuteSingleTickMessage((int) $stale->getId()));
            $messageId = (int) $em->getConnection()->fetchOne('SELECT MAX(id) FROM messenger_messages');
            $em->getConnection()->executeStatement('UPDATE messenger_messages SET available_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $messageId]);

            $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
            $messages = [];
            $logger->expects($this->exactly(3))->method('error')->willReturnCallback(static function (string $message) use (&$messages): void {
                $messages[] = $message;
            });
            $monitor = new \App\Service\Calendar\TickHealthMonitor(
                $em, self::getContainer()->get(\App\Service\Calendar\KingdomTickRunnerService::class),
                new \Symfony\Component\Cache\Adapter\ArrayAdapter(), $logger,
            );
            $monitor->checkKingdom($kingdom);
            $monitor->checkQueue();
            $monitor->checkKingdom($kingdom);
            $monitor->checkQueue();
            self::assertStringContainsString('failed tick '.$failed->getId(), $messages[0]);
            self::assertStringContainsString('tick '.$stale->getId(), $messages[1]);
            self::assertStringContainsString('async queue', $messages[2]);
            self::assertCount(3, $messages);
        } finally {
            $em->rollBack();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testInterruptedRecoverySkipsLiveWorkersAndCombat(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $otherConnection = \Doctrine\DBAL\DriverManager::getConnection($em->getConnection()->getParams());
        $em->beginTransaction();
        try {
            $stale = new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC'));
            $live = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
                ->setScheduledAt(new \DateTimeImmutable('2030-01-02 UTC'))->setStatus('processing')->setExecutedAt($stale);
            $interrupted = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
                ->setScheduledAt(new \DateTimeImmutable('2030-01-03 UTC'))->setStatus('processing')->setExecutedAt($stale);
            $exhausted = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
                ->setScheduledAt(new \DateTimeImmutable('2030-01-04 UTC'))->setStatus('processing')->setExecutedAt($stale)->setRetryCount(2);
            $combat = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
                ->setScheduledAt(new \DateTimeImmutable('2030-01-05 UTC'))->setStatus('processing')->setExecutedAt($stale);
            foreach ([$live, $interrupted, $exhausted, $combat] as $tick) {
                $this->persistTick($tick);
            }
            $em->flush();
            $lock = (new ExecuteSingleTickMessage((int) $live->getId()))->getLockName();
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT GET_LOCK(?, 0)', [$lock]));

            try {
                self::getContainer()->get(ExecuteSingleTickHandler::class)(new ExecuteSingleTickMessage((int) $live->getId()));
                self::fail('A locked tick message must be retried.');
            } catch (\Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException $exception) {
                self::assertTrue($exception->forceRetry());
                self::assertSame(1000, $exception->getRetryDelay());
            }

            $orchestrator = self::getContainer()->get(\App\Service\Calendar\KingdomTickOrchestrator::class);
            self::assertSame(2, $orchestrator->recoverInterruptedTicks($kingdom));
            foreach ([$live, $interrupted, $exhausted, $combat] as $tick) {
                $em->refresh($tick);
            }
            self::assertSame('processing', $live->getStatus());
            self::assertSame('pending', $interrupted->getStatus());
            self::assertSame(1, $interrupted->getRetryCount());
            self::assertSame('failed', $exhausted->getStatus());
            self::assertSame('processing', $combat->getStatus());

            $otherConnection->fetchOne('SELECT RELEASE_LOCK(?)', [$lock]);
            self::assertSame(1, $orchestrator->recoverInterruptedTicks($kingdom));
            $em->refresh($live);
            self::assertSame('pending', $live->getStatus());
        } finally {
            $otherConnection->close();
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testDispatchFailureRollsBackStatusAndQueuedMessages(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $time = new \DateTimeImmutable('2030-01-02 UTC');
            for ($index = 0; $index < 2; ++$index) {
                $team = (new Team())->setKingdom($kingdom)->setName('Dispatch team '.$index);
                $em->persist($team);
                $this->persistTick((new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::FatigueRecovery)
                    ->setScheduledAt($time)->setTeam($team));
            }
            $em->flush();
            $queue = 'tick_test_'.bin2hex(random_bytes(8));
            $transport = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport(
                new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection(['queue_name' => $queue, 'auto_setup' => false], $em->getConnection()),
                new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer(),
            );
            $bus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
            $calls = 0;
            $bus->expects($this->exactly(2))->method('dispatch')->willReturnCallback(
                static function (object $message) use ($transport, &$calls): \Symfony\Component\Messenger\Envelope {
                    ++$calls;
                    if (2 === $calls) {
                        throw new \RuntimeException('Dispatch failed');
                    }

                    return $transport->send(new \Symfony\Component\Messenger\Envelope($message));
                },
            );
            $orchestrator = new \App\Service\Calendar\KingdomTickOrchestrator(
                $this->repository, $em, $bus, $this->createStub(\Psr\Log\LoggerInterface::class),
                $this->createStub(\App\Service\League\SeasonTransitionService::class),
                $this->createStub(\App\Service\Marketplace\MarketplaceService::class), new \App\Service\Calendar\TickClock(),
            );
            try {
                $orchestrator->orchestrate($kingdom);
                self::fail('Dispatch must fail.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Dispatch failed', $exception->getMessage());
            }
            self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', [$queue]));
            self::assertSame(2, $this->repository->count(['kingdom' => $kingdom, 'status' => 'pending']));
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testCombatContinuationFailureRollsBackRoundAndMessage(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = (int) $kingdom->getId();
        $em->beginTransaction();
        try {
            $time = new \DateTimeImmutable('2030-01-02 UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Combat atomicity team');
            $battle = (new Battle())->setKingdom($kingdom)->setTeamA($team)->setTeamB($team)
                ->setMatchType(MatchType::Friendly)->setStatus(BattleStatus::Simulating)->setScheduledAt($time)
                ->setCurrentRound(0)->setRunState(['status' => 'simulating']);
            $em->persist($team);
            $em->persist($battle);
            $em->flush();
            $battleId = (int) $battle->getId();
            $repository = $this->createMock(\App\Repository\Combat\BattleRepository::class);
            $repository->expects($this->exactly(2))->method('findSimulatingCohortRounds')->willReturnOnConsecutiveCalls(
                [['id' => $battleId, 'currentRound' => 0]], [['id' => $battleId, 'currentRound' => 1]],
            );
            $engine = $this->createMock(\App\Service\Combat\CombatEngine::class);
            $engine->expects($this->once())->method('simulateRound')->willReturn([]);
            $roundProcessor = new \App\Service\Combat\CombatRoundProcessor(
                $em, $engine, $this->createStub(\App\Service\League\LeagueMatchResolutionService::class),
                $this->createStub(\App\Service\FriendlyMatch\FriendlyMatchResolutionService::class),
                $this->createStub(\Psr\Log\LoggerInterface::class),
                new \App\Service\Calendar\TickClock(),
            );
            $queue = 'tick_test_'.bin2hex(random_bytes(8));
            $transport = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport(
                new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection(['queue_name' => $queue, 'auto_setup' => false], $em->getConnection()),
                new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer(),
            );
            $bus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
            $bus->expects($this->once())->method('dispatch')->willReturnCallback(static function (object $message) use ($transport): never {
                $transport->send(new \Symfony\Component\Messenger\Envelope($message));
                throw new \RuntimeException('Combat continuation failed');
            });
            $processor = new \App\Service\Combat\CombatWaveProcessor(
                $repository, $roundProcessor, $em, $bus, $this->createStub(\App\Service\Calendar\KingdomTickOrchestrator::class), 1,
            );
            try {
                $processor->process(new \App\Message\CombatWave($kingdomId, $time, 1));
                self::fail('Dispatch must fail.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Combat continuation failed', $exception->getMessage());
            }
            self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT current_round FROM combat_battle WHERE id = ?', [$battleId]));
            self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', [$queue]));
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testConcurrentTreasuryUpdatesCannotOverwriteEachOther(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $connection = \Doctrine\DBAL\DriverManager::getConnection($em->getConnection()->getParams());
        $otherManager = new \Doctrine\ORM\EntityManager($connection, $em->getConfiguration());
        try {
            $otherKingdom = $otherManager->find(Kingdom::class, $kingdom->getId());
            self::assertNotNull($otherKingdom);
            $kingdom->setRoyalTreasuryGold(100);
            $em->flush();
            $otherKingdom->setRoyalTreasuryGold(200);

            try {
                $otherManager->flush();
                self::fail('A stale treasury update must not overwrite the committed balance.');
            } catch (\Doctrine\ORM\OptimisticLockException) {
                $em->refresh($kingdom);
                self::assertSame(100, $kingdom->getRoyalTreasuryGold());
            }
        } finally {
            $otherManager->close();
            $connection->close();
        }
    }

    public function testConcurrentTeamGoldUpdatesCannotOverwriteEachOther(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $team = (new Team())->setKingdom($kingdom)->setName('Concurrent team')->setGold(100);
        $em->persist($team);
        $em->flush();
        $connection = \Doctrine\DBAL\DriverManager::getConnection($em->getConnection()->getParams());
        $otherManager = new \Doctrine\ORM\EntityManager($connection, $em->getConfiguration());
        try {
            $otherTeam = $otherManager->find(Team::class, $team->getId());
            self::assertNotNull($otherTeam);
            $team->setGold(150);
            $em->flush();
            $otherTeam->setGold(50);
            try {
                $otherManager->flush();
                self::fail('A stale team update must not overwrite the committed balance.');
            } catch (\Doctrine\ORM\OptimisticLockException) {
                $em->refresh($team);
                self::assertSame(150, $team->getGold());
            }
        } finally {
            $otherManager->close();
            $connection->close();
            $em->remove($team);
            $em->flush();
        }
    }

    public function testBidCannotCommitAgainstAConcurrentlyClosedListing(): void
    {
        $this->assertStaleBidCannotCommit(false);
    }

    public function testBidCannotCommitAgainstAConcurrentBid(): void
    {
        $this->assertStaleBidCannotCommit(true);
    }

    private function assertStaleBidCannotCommit(bool $concurrentBid): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $seller = (new Team())->setKingdom($kingdom)->setName('Auction seller');
        $bidder = (new Team())->setKingdom($kingdom)->setName('Auction bidder')->setGold(100)->setIsNpc(true);
        $otherBidder = (new Team())->setKingdom($kingdom)->setName('Other auction bidder')->setGold(100)->setIsNpc(true);
        $listing = (new \App\Entity\Marketplace\MarketplaceListing())
            ->setKingdom($kingdom)->setSellerTeam($seller)
            ->setListingType(\App\Enum\ListingType::Item)->setListingMode(\App\Enum\ListingMode::Auction)
            ->setPriceGold(10)->setExpiresAt(new \DateTimeImmutable('2030-01-02 UTC'));
        foreach ([$seller, $bidder, $otherBidder, $listing] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $connection = \Doctrine\DBAL\DriverManager::getConnection($em->getConnection()->getParams());
        $otherManager = new \Doctrine\ORM\EntityManager($connection, $em->getConfiguration());
        try {
            $otherListing = $otherManager->find(\App\Entity\Marketplace\MarketplaceListing::class, $listing->getId());
            self::assertNotNull($otherListing);
            if ($concurrentBid) {
                $otherManagedBidder = $otherManager->find(Team::class, $otherBidder->getId());
                self::assertNotNull($otherManagedBidder);
                $otherManagedBidder->setGold(90);
                $firstBid = (new \App\Entity\Marketplace\MarketplaceBid())->setListing($otherListing)
                    ->setBidderTeam($otherManagedBidder)->setBidAmount(10)->setBidTime(new \DateTimeImmutable('2030-01-01 UTC'));
                $otherListing->recordBid();
                $otherManager->persist($firstBid);
            } else {
                $otherListing->setStatus(\App\Enum\ListingStatus::Sold);
            }
            $otherManager->flush();
            try {
                self::getContainer()->get(\App\Service\Marketplace\MarketplaceService::class)
                    ->placeBid($bidder, (int) $listing->getId(), 20, new \DateTimeImmutable('2030-01-01 UTC'));
                self::fail('A bid against a stale listing must not commit.');
            } catch (\Doctrine\ORM\OptimisticLockException) {
                self::assertSame(100, (int) $connection->fetchOne('SELECT gold FROM team WHERE id = ?', [$bidder->getId()]));
                self::assertSame($concurrentBid ? 1 : 0, (int) $connection->fetchOne('SELECT COUNT(*) FROM marketplace_bid WHERE listing_id = ?', [$listing->getId()]));
                self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM team_financial_record WHERE team_id = ?', [$bidder->getId()]));
                if ($concurrentBid) {
                    self::assertSame(90, (int) $connection->fetchOne('SELECT gold FROM team WHERE id = ?', [$otherBidder->getId()]));
                    self::assertSame(10, (int) $connection->fetchOne('SELECT bid_amount FROM marketplace_bid WHERE listing_id = ?', [$listing->getId()]));
                }
            }
        } finally {
            $connection->executeStatement('DELETE FROM marketplace_bid WHERE listing_id = ?', [$listing->getId()]);
            $connection->executeStatement('DELETE FROM team_financial_record WHERE team_id = ?', [$bidder->getId()]);
            $connection->executeStatement('DELETE FROM marketplace_listing WHERE id = ?', [$listing->getId()]);
            $connection->executeStatement('DELETE FROM team WHERE id IN (?, ?, ?)', [$seller->getId(), $bidder->getId(), $otherBidder->getId()]);
            $connection->executeStatement('DELETE FROM kingdom WHERE id = ?', [$kingdom->getId()]);
            $otherManager->close();
            $connection->close();
            $this->em = null;
            $this->kingdom = null;
        }
    }

    public function testRecoveryLeavesAsynchronousBattleTicksProcessing(): void
    {
        $em = $this->em;
        $repository = $this->repository;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($repository);
        self::assertNotNull($kingdom);

        $kingdomId = $kingdom->getId();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $em->beginTransaction();
        try {
            $team = (new Team())->setKingdom($kingdom)->setName('Recovery test team');
            $season = (new LeagueSeason())->setKingdom($kingdom)->setSeasonNumber(1)->setStartDate($now)->setEndDate($now);
            $tier = (new LeagueTier())->setSeason($season)->setTierName('Test')->setPromotionSlots(0)->setRelegationSlots(0);
            $group = (new LeagueGroup())->setTier($tier)->setGroupName('Test');
            foreach ([$team, $season, $tier, $group] as $entity) {
                $em->persist($entity);
            }

            $logs = [];
            foreach ([MatchType::League, MatchType::Friendly] as $matchType) {
                foreach ([BattleStatus::Simulating, BattleStatus::Stalled, BattleStatus::Completed] as $battleStatus) {
                    foreach (['processing', 'dispatched'] as $tickStatus) {
                        $battle = (new Battle())->setKingdom($kingdom)->setMatchType($matchType)
                            ->setTeamA($team)->setTeamB($team)->setStatus($battleStatus)
                            ->setRunState(['seed' => 1, 'events' => array_fill(0, 100, 'event')]);
                        $em->persist($battle);

                        $log = (new KingdomTickLog())->setKingdom($kingdom)
                            ->setTickType(MatchType::League === $matchType ? TickType::LeagueMatch : TickType::FriendlyMatch)
                            ->setScheduledAt($now)->setExecutedAt($now->modify('-10 minutes'))->setStatus($tickStatus);
                        if (MatchType::League === $matchType) {
                            $match = (new LeagueFixture())->setGroup($group)->setHomeTeam($team)->setAwayTeam($team)
                                ->setScheduledAt($now)->setBattle($battle);
                            $log->setFixture($match);
                        } else {
                            $match = (new FriendlyMatch())->setKingdom($kingdom)->setHomeTeam($team)->setAwayTeam($team)
                                ->setScheduledAt($now)->setBattle($battle);
                            $log->setFriendlyMatch($match);
                        }
                        $em->persist($match);
                        $this->persistTick($log);
                        $logs[] = [$log, 'processing' === $tickStatus];
                    }
                }
            }
            $em->flush();
            $expected = array_map(static fn (array $entry): array => [$entry[0]->getId(), $entry[1]], $logs);
            $em->clear();
            $kingdom = $em->find(Kingdom::class, $kingdomId);
            self::assertNotNull($kingdom);

            self::assertSame(6, $repository->recoverStaleTicks($kingdom, $now->modify('-5 minutes')));
            self::assertArrayNotHasKey(Battle::class, $em->getUnitOfWork()->getIdentityMap());
            foreach ($expected as [$id, $protected]) {
                $log = $em->find(KingdomTickLog::class, $id);
                self::assertNotNull($log);
                self::assertSame($protected ? 'processing' : 'pending', $log->getStatus());
                self::assertSame($protected ? 0 : 1, $log->getRetryCount());
            }
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testCombatContinuationRecoveryRespectsActiveWorkerAndThrottlesRequeue(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $otherConnection = \Doctrine\DBAL\DriverManager::getConnection($em->getConnection()->getParams());
        $em->beginTransaction();
        try {
            $scheduledAt = new \DateTimeImmutable('2026-06-17 18:00:00 UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Combat recovery team');
            $battle = (new Battle())->setKingdom($kingdom)->setMatchType(MatchType::Friendly)->setTeamA($team)->setTeamB($team)
                ->setStatus(BattleStatus::Simulating)->setScheduledAt($scheduledAt)->setCurrentRound(12);
            $friendly = (new FriendlyMatch())->setKingdom($kingdom)->setHomeTeam($team)->setAwayTeam($team)
                ->setScheduledAt($scheduledAt)->setBattle($battle);
            $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::FriendlyMatch)->setFriendlyMatch($friendly)
                ->setScheduledAt($scheduledAt)->setExecutedAt(new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC')))->setStatus('processing');
            foreach ([$team, $battle, $friendly] as $entity) {
                $em->persist($entity);
            }
            $this->persistTick($tick);
            $em->flush();
            $container = self::getContainer();
            $queue = 'tick_test_'.bin2hex(random_bytes(8));
            $transport = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport(
                new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection(['queue_name' => $queue, 'auto_setup' => false], $em->getConnection()),
                new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer(),
            );
            $container->set('messenger.transport.async', $transport);
            $orchestrator = $container->get(\App\Service\Calendar\KingdomTickOrchestrator::class);
            $message = new \App\Message\CombatWave((int) $kingdomId, $scheduledAt, 1);
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT GET_LOCK(?, 0)', [$message->getLockName()]));
            self::assertFalse($orchestrator->recoverCombatContinuation($kingdom));
            self::assertSame(0, $transport->getMessageCount());
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT RELEASE_LOCK(?)', [$message->getLockName()]));

            self::assertTrue($orchestrator->recoverCombatContinuation($kingdom));
            self::assertFalse($orchestrator->recoverCombatContinuation($kingdom));
            $messages = iterator_to_array($transport->get());
            self::assertCount(1, $messages);
            $queued = reset($messages)->getMessage();
            self::assertInstanceOf(\App\Message\CombatWave::class, $queued);
            self::assertSame(13, $queued->getRound());
            self::assertSame('processing', $tick->getStatus());
            self::assertSame(BattleStatus::Simulating, $battle->getStatus());

            for ($attempt = 2; $attempt <= 3; ++$attempt) {
                $tick->setExecutedAt(new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC')));
                $em->flush();
                self::assertTrue($orchestrator->recoverCombatContinuation($kingdom));
                self::assertSame($attempt, $tick->getCombatRecoveryCount());
            }
            $runner = $container->get(\App\Service\Calendar\KingdomTickRunnerService::class);
            $snapshot = $runner->getStatusSnapshot($kingdom);
            self::assertSame(3, $snapshot['combat_recoveries'][0]['recoveries']);
            self::assertSame(12, $snapshot['combat_recoveries'][0]['round']);
            $battle->setCurrentRound(13);
            $tick->setExecutedAt(new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC')));
            $em->flush();
            self::assertSame([], $runner->getStatusSnapshot($kingdom)['combat_recoveries']);
            self::assertTrue($orchestrator->recoverCombatContinuation($kingdom));
            self::assertSame(1, $tick->getCombatRecoveryCount());
            self::assertSame(13, $tick->getCombatRecoveryRound());

            $tick->setExecutedAt(new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC')));
            $battle->setStatus(BattleStatus::Stalled);
            $em->flush();
            self::assertFalse($orchestrator->recoverCombatContinuation($kingdom));
            self::assertSame(BattleStatus::Stalled, $battle->getStatus());

            $battle->setStatus(BattleStatus::Simulating);
            $unfinishedKickoff = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
                ->setScheduledAt($scheduledAt)->setStatus('processing');
            $this->persistTick($unfinishedKickoff);
            $em->flush();
            self::assertFalse($orchestrator->recoverCombatContinuation($kingdom));
            self::assertSame(4, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE queue_name = ?', [$queue]));
        } finally {
            $otherConnection->close();
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testOptimisticConflictRetryIsAtomicAndBounded(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $kingdomId = $kingdom->getId();
        $em->beginTransaction();
        try {
            $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::WeeklyReset)
                ->setScheduledAt(new \DateTimeImmutable('2026-06-17 18:00:00 UTC'))->setStatus('processing');
            $this->persistTick($tick);
            $em->flush();
            $container = self::getContainer();
            $queue = 'tick_test_'.bin2hex(random_bytes(8));
            $transport = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport(
                new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection(['queue_name' => $queue, 'auto_setup' => false], $em->getConnection()),
                new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer(),
            );
            $container->set('messenger.transport.async', $transport);
            $orchestrator = $container->get(\App\Service\Calendar\KingdomTickOrchestrator::class);
            for ($attempt = 1; $attempt <= 3; ++$attempt) {
                self::assertTrue($orchestrator->retryOptimisticConflict((int) $tick->getId()));
                $em->refresh($tick);
                self::assertSame('dispatched', $tick->getStatus());
                self::assertSame($attempt, $tick->getRetryCount());
                $tick->setStatus('processing');
                $em->flush();
            }
            self::assertFalse($orchestrator->retryOptimisticConflict((int) $tick->getId()));
            self::assertSame(3, $transport->getMessageCount());

            $tick->setRetryCount(0);
            $em->flush();
            $bus = $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class);
            $bus->expects($this->once())->method('dispatch')->willThrowException(new \RuntimeException('Cannot send retry'));
            $failingOrchestrator = new \App\Service\Calendar\KingdomTickOrchestrator(
                $this->repository, $em, $bus, $this->createStub(\Psr\Log\LoggerInterface::class),
                $this->createStub(\App\Service\League\SeasonTransitionService::class),
                $this->createStub(\App\Service\Marketplace\MarketplaceService::class), new \App\Service\Calendar\TickClock(),
            );
            try {
                $failingOrchestrator->retryOptimisticConflict((int) $tick->getId());
                self::fail('A failed dispatch must be propagated.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Cannot send retry', $exception->getMessage());
                $em->refresh($tick);
                self::assertSame('processing', $tick->getStatus());
                self::assertSame(0, $tick->getRetryCount());
            }
        } finally {
            $em->rollback();
            $em->clear();
            $this->restoreKingdom($kingdomId);
        }
    }

    public function testTickRequeuesAfterRealOptimisticConflictClosesEntityManager(): void
    {
        $em = $this->em;
        $kingdom = $this->kingdom;
        self::assertNotNull($em);
        self::assertNotNull($kingdom);
        $team = (new Team())->setKingdom($kingdom)->setName('Conflicting training team')->setGold(100);
        $tick = (new KingdomTickLog())->setKingdom($kingdom)->setTeam($team)->setTickType(TickType::WeeklyTraining)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-17 18:00:00 UTC'))->setStatus('pending');
        $em->persist($team);
        $this->persistTick($tick);
        $em->flush();
        $otherConnection = \Doctrine\DBAL\DriverManager::getConnection($em->getConnection()->getParams());
        $otherManager = new \Doctrine\ORM\EntityManager($otherConnection, $em->getConfiguration());
        $queue = 'tick_test_'.bin2hex(random_bytes(8));
        try {
            $container = self::getContainer();
            $transport = new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport(
                new \Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection(['queue_name' => $queue, 'auto_setup' => false], $em->getConnection()),
                new \Symfony\Component\Messenger\Transport\Serialization\PhpSerializer(),
            );
            $container->set('messenger.transport.async', $transport);
            $training = $this->createMock(\App\Service\Training\TrainingService::class);
            $training->expects($this->once())->method('processTrainingTick')->willReturnCallback(
                static function (\DateTimeImmutable $time, Kingdom $actualKingdom, Team $actualTeam) use ($em, $otherManager): void {
                    $otherTeam = $otherManager->find(Team::class, $actualTeam->getId());
                    self::assertNotNull($otherTeam);
                    $otherTeam->setGold(200);
                    $otherManager->flush();
                    (new \App\Service\Economy\EconomyService($em, new \App\Service\Calendar\TickClock()))->addGold(
                        $actualTeam, 20, \App\Enum\FinancialRecordType::ArenaRevenue, \App\Enum\FinancialRecordActor::System,
                    );
                },
            );
            $container->set(\App\Service\Training\TrainingService::class, $training);
            $container->get(ExecuteSingleTickHandler::class)(new ExecuteSingleTickMessage((int) $tick->getId()));

            self::assertFalse($em->isOpen());
            self::assertSame(200, (int) $otherConnection->fetchOne('SELECT gold FROM team WHERE id = ?', [$team->getId()]));
            self::assertSame(0, (int) $otherConnection->fetchOne('SELECT COUNT(*) FROM team_financial_record WHERE team_id = ?', [$team->getId()]));
            self::assertSame('dispatched', $otherConnection->fetchOne('SELECT status FROM kingdom_tick_log WHERE id = ?', [$tick->getId()]));
            self::assertSame(1, (int) $otherConnection->fetchOne('SELECT retry_count FROM kingdom_tick_log WHERE id = ?', [$tick->getId()]));
            self::assertSame(1, $transport->getMessageCount());
        } finally {
            $otherConnection->executeStatement('DELETE FROM messenger_messages WHERE queue_name = ?', [$queue]);
            $otherConnection->executeStatement('DELETE FROM team_financial_record WHERE team_id = ?', [$team->getId()]);
            $otherConnection->executeStatement('DELETE FROM kingdom_tick_log WHERE id = ?', [$tick->getId()]);
            $otherConnection->executeStatement('DELETE FROM team WHERE id = ?', [$team->getId()]);
            $otherConnection->executeStatement('DELETE FROM kingdom_tick_event WHERE kingdom_id = ?', [$kingdom->getId()]);
            $otherConnection->executeStatement('DELETE FROM kingdom WHERE id = ?', [$kingdom->getId()]);
            $otherManager->close();
            $otherConnection->close();
            $this->em = null;
            $this->kingdom = null;
        }
    }

    public function testRecoverStaleTicks(): void
    {
        $em = $this->em;
        $repository = $this->repository;
        $kingdom = $this->kingdom;

        $this->assertNotNull($em);
        $this->assertNotNull($repository);
        $this->assertNotNull($kingdom);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        // 1. Stale processing tick is not automatically retried.
        $log1 = new KingdomTickLog();
        $log1->setKingdom($kingdom);
        $log1->setTickType(TickType::WeeklyTraining);
        $log1->setScheduledAt($now->modify('-1 hour'));
        $log1->setStatus('processing');
        $log1->setExecutedAt($now->modify('-45 seconds'));
        $log1->setRetryCount(0);
        $this->persistTick($log1);

        // 2. Stale tick at max retries (dispatched, executed 45s ago, retryCount = 2) -> should fail (status failed, retryCount = 3)
        $log2 = new KingdomTickLog();
        $log2->setKingdom($kingdom);
        $log2->setTickType(TickType::LeagueMatch);
        $log2->setScheduledAt($now->modify('-1 hour'));
        $log2->setStatus('dispatched');
        $log2->setExecutedAt($now->modify('-45 seconds'));
        $log2->setRetryCount(2);
        $this->persistTick($log2);

        // 3. Fresh tick (processing, executed 5s ago, retryCount = 0) -> should remain unchanged
        $log3 = new KingdomTickLog();
        $log3->setKingdom($kingdom);
        $log3->setTickType(TickType::DailyReset);
        $log3->setScheduledAt($now->modify('-1 hour'));
        $log3->setStatus('processing');
        $log3->setExecutedAt($now->modify('-5 seconds'));
        $log3->setRetryCount(0);
        $this->persistTick($log3);

        // 4. Pending tick (pending, scheduled 45s ago) -> should remain unchanged
        $log4 = new KingdomTickLog();
        $log4->setKingdom($kingdom);
        $log4->setTickType(TickType::FatigueRecovery);
        $log4->setScheduledAt($now->modify('-1 hour'));
        $log4->setStatus('pending');
        $log4->setExecutedAt($now->modify('-45 seconds'));
        $log4->setRetryCount(0);
        $this->persistTick($log4);

        $em->flush();

        // Run recovery with a 30-second threshold
        $threshold = $now->modify('-30 seconds');
        $recoveredCount = $repository->recoverStaleTicks($kingdom, $threshold);

        $this->assertEquals(1, $recoveredCount);

        // Refresh from database and assert
        $em->refresh($log1);
        $em->refresh($log2);
        $em->refresh($log3);
        $em->refresh($log4);

        // Assert Log 1: untouched while it may still be running
        $this->assertEquals('processing', $log1->getStatus());
        $this->assertEquals(0, $log1->getRetryCount());

        // Assert Log 2: failed because max retries reached
        $this->assertEquals('failed', $log2->getStatus());
        $this->assertEquals(3, $log2->getRetryCount());
        $this->assertStringContainsString('Dispatch timeout. Maximum retries reached (3)', (string) $log2->getErrorMessage());

        // Assert Log 3: untouched
        $this->assertEquals('processing', $log3->getStatus());
        $this->assertEquals(0, $log3->getRetryCount());

        // Assert Log 4: untouched
        $this->assertEquals('pending', $log4->getStatus());
        $this->assertEquals(0, $log4->getRetryCount());
    }
}
