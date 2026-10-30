<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ProcessTicksCommand;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Entity\Team\Team;
use App\Entity\League\LeagueFixture;
use App\Enum\TickType;
use App\Message\ExecuteSingleTickHandler;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Calendar\KingdomTickRunnerService;
use App\Service\Calendar\TickScheduleCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class ProcessTicksCommandTest extends TestCase
{
    public function testSchedulesOnlyMatchingTickTypesForEachOccurrence(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $kingdom->setTimezone('UTC');

        $reflection = new \ReflectionClass(Kingdom::class);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($kingdom, 1);

        $team = new Team();
        $team->setKingdom($kingdom);
        $reflectionTeam = new \ReflectionClass(Team::class);
        $idTeamProperty = $reflectionTeam->getProperty('id');
        $idTeamProperty->setValue($team, 1);

        $fixture = new LeagueFixture();
        $fixture->setHomeTeam($team);
        $fixture->setAwayTeam($team);
        $reflectionFixture = new \ReflectionClass(LeagueFixture::class);
        $idFixtureProperty = $reflectionFixture->getProperty('id');
        $idFixtureProperty->setValue($fixture, 1);

        $now = new \DateTimeImmutable('2026-06-09T18:30:00Z');
        $leagueMatchTime = new \DateTimeImmutable('2026-06-09T18:00:00Z');
        $fatigueRecoveryTime = new \DateTimeImmutable('2026-06-09T04:00:00Z');

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->method('findAll')->willReturn([$kingdom]);

        $tickLogRepository = $this->createMock(KingdomTickLogRepository::class);
        $tickLogRepository->method('findOneBy')->willReturnCallback(
            static function (array $criteria): ?KingdomTickLog {
                return null;
            }
        );
        $tickLogRepository->method('getLatestCompletedTickTime')->willReturn(null);
        $tickLogRepository->method('count')->willReturn(0);

        $seasonRepository = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepository->method('findOneBy')->willReturn(null);

        $scheduleCalculator = $this->createMock(TickScheduleCalculator::class);
        $scheduleCalculator->method('generateOccurrences')->willReturn([
            ['type' => TickType::FatigueRecovery, 'time' => $fatigueRecoveryTime],
            ['type' => TickType::LeagueMatch, 'time' => $leagueMatchTime],
        ]);

        $teamRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $teamRepository->method('findBy')->willReturn([$team]);

        $leagueFixtureRepository = $this->createMock(\App\Repository\League\LeagueFixtureRepository::class);
        $leagueFixtureRepository->method('findScheduledFixturesAtTime')->willReturn([$fixture]);

        $persistedLogs = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(
            static function (string $className) use ($teamRepository, $leagueFixtureRepository) {
                if ($className === Team::class) {
                    return $teamRepository;
                }
                if ($className === LeagueFixture::class) {
                    return $leagueFixtureRepository;
                }
                return null;
            }
        );
        $entityManager->method('persist')->willReturnCallback(
            static function (KingdomTickLog $log) use (&$persistedLogs): void {
                $persistedLogs[] = $log;
            }
        );

        $calledKingdom = null;
        $tickOrchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $tickOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->willReturnCallback(function (Kingdom $k) use (&$calledKingdom) {
                $calledKingdom = $k;
            });

        $singleTickHandler = $this->createMock(ExecuteSingleTickHandler::class);
        $tickRunner = new KingdomTickRunnerService(
            $tickLogRepository,
            $seasonRepository,
            $scheduleCalculator,
            $singleTickHandler,
            $entityManager,
        );

        $command = new ProcessTicksCommand(
            $kingdomRepository,
            $tickRunner,
            $tickOrchestrator,
            $tickLogRepository,
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--time' => $now->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame($kingdom, $calledKingdom);

        $scheduledTypes = array_map(
            static fn (KingdomTickLog $log): string => $log->getTickType()->value,
            $persistedLogs
        );

        $this->assertContains(TickType::FatigueRecovery->value, $scheduledTypes);
        $this->assertContains(TickType::LeagueMatch->value, $scheduledTypes);
        $this->assertNotContains(TickType::DailyReset->value, $scheduledTypes);

        foreach ($persistedLogs as $log) {
            if (TickType::LeagueMatch === $log->getTickType()) {
                $this->assertEquals($leagueMatchTime, $log->getScheduledAt());
            }
            if (TickType::FatigueRecovery === $log->getTickType()) {
                $this->assertEquals($fatigueRecoveryTime, $log->getScheduledAt());
            }
        }
    }

    public function testAdvanceUsesEachKingdomsLatestCompletedScheduledTime(): void
    {
        $firstKingdom = new Kingdom();
        $firstKingdom->setName('First Kingdom');
        $secondKingdom = new Kingdom();
        $secondKingdom->setName('Second Kingdom');
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($firstKingdom, 1);
        $idProperty->setValue($secondKingdom, 2);

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->method('findAll')->willReturn([$firstKingdom, $secondKingdom]);

        $firstLog = new KingdomTickLog();
        $firstLog->setScheduledAt(new \DateTimeImmutable('2026-10-27T03:00:00Z'));
        $secondLog = new KingdomTickLog();
        $secondLog->setScheduledAt(new \DateTimeImmutable('2026-11-10T03:00:00Z'));

        $tickLogRepository = $this->createMock(KingdomTickLogRepository::class);
        $tickLogRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(function (array $criteria, ?array $orderBy) use ($firstKingdom, $firstLog, $secondLog): KingdomTickLog {
                $this->assertSame('completed', $criteria['status']);
                $this->assertSame(['scheduledAt' => 'DESC'], $orderBy);

                return $criteria['kingdom'] === $firstKingdom ? $firstLog : $secondLog;
            });

        $tickRunner = $this->createMock(KingdomTickRunnerService::class);
        $tickRunner->method('recoverStaleTicks')->willReturn(0);
        $tickRunner->method('findBlockedTick')->willReturn(null);
        $tickRunner->method('hasPendingTicks')->willReturn(false);
        $tickRunner->expects($this->exactly(2))
            ->method('schedulePendingTicks')
            ->willReturnCallback(function (Kingdom $kingdom, \DateTimeImmutable $until) use ($firstKingdom): int {
                $expected = $kingdom === $firstKingdom ? '2026-11-03 03:00:00' : '2026-11-17 03:00:00';
                $this->assertSame($expected, $until->format('Y-m-d H:i:s'));
                $this->assertSame('UTC', $until->getTimezone()->getName());

                return 1;
            });

        $tickOrchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $tickOrchestrator->expects($this->exactly(2))->method('orchestrate');

        $tester = new CommandTester(new ProcessTicksCommand($kingdomRepository, $tickRunner, $tickOrchestrator, $tickLogRepository));

        $this->assertSame(0, $tester->execute(['--advance' => '+1 week']));
        $this->assertStringContainsString('Advancing from 2026-10-27 03:00:00 UTC to 2026-11-03 03:00:00 UTC.', $tester->getDisplay());
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('invalidAdvanceProvider')]
    public function testRejectsInvalidAdvanceWithoutScheduling(array $options, bool $hasHistory, string $expectedError): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 1);

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->method('findAll')->willReturn([$kingdom]);
        $kingdomRepository->method('findBy')->willReturn([$kingdom]);

        $latestLog = new KingdomTickLog();
        $latestLog->setScheduledAt(new \DateTimeImmutable('2026-10-27T03:00:00Z'));
        $tickLogRepository = $this->createMock(KingdomTickLogRepository::class);
        $tickLogRepository->method('findOneBy')->willReturn($hasHistory ? $latestLog : null);

        $tickRunner = $this->createMock(KingdomTickRunnerService::class);
        $tickRunner->expects($this->never())->method('schedulePendingTicks');
        $tickOrchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $tickOrchestrator->expects($this->never())->method('orchestrate');

        $tester = new CommandTester(new ProcessTicksCommand($kingdomRepository, $tickRunner, $tickOrchestrator, $tickLogRepository));

        $this->assertSame(1, $tester->execute($options));
        $this->assertStringContainsString($expectedError, $tester->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, string>, bool, string}>
     */
    public static function invalidAdvanceProvider(): iterable
    {
        yield 'conflicting options' => [['--advance' => '+1 week', '--time' => '2026-11-03 03:00:00'], true, 'cannot be used together'];
        yield 'invalid interval' => [['--advance' => 'not-an-interval'], true, 'Invalid --advance interval'];
        yield 'empty interval' => [['--advance' => ''], true, 'Invalid --advance interval'];
        yield 'absolute date' => [['--advance' => '2026-11-03'], true, 'Invalid --advance interval'];
        yield 'negative interval' => [['--advance' => '-1 week'], true, 'must move time forward'];
        yield 'zero interval' => [['--advance' => '0 days'], true, 'must move time forward'];
        yield 'no completed history' => [['--advance' => '+1 week', '--kingdom-id' => '1'], false, 'has no completed ticks'];
    }

    public function testDryRunDoesNotPersistOrOrchestrate(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 1);

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->method('findAll')->willReturn([$kingdom]);

        $tickRunner = $this->createMock(KingdomTickRunnerService::class);
        $tickRunner->expects($this->never())->method('recoverStaleTicks');
        $tickRunner->method('findBlockedTick')->willReturn(null);
        $tickRunner->method('hasPendingTicks')->willReturn(false);
        $tickRunner->expects($this->once())
            ->method('schedulePendingTicks')
            ->with($kingdom, $this->isInstanceOf(\DateTimeImmutable::class), false)
            ->willReturn(4);
        $tickRunner->expects($this->never())->method('processPendingTicksSynchronously');

        $tickOrchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $tickOrchestrator->expects($this->never())->method('orchestrate');

        $tester = new CommandTester(new ProcessTicksCommand(
            $kingdomRepository,
            $tickRunner,
            $tickOrchestrator,
            $this->createMock(KingdomTickLogRepository::class),
        ));

        $this->assertSame(0, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('would schedule 4 new tick(s)', $tester->getDisplay());
    }

    public function testBlockedTickReturnsFailure(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 1);

        $blocked = new KingdomTickLog();
        $blocked->setTickType(TickType::DailyReset);
        $blocked->setStatus('failed');
        $blockedId = new \ReflectionProperty(KingdomTickLog::class, 'id');
        $blockedId->setValue($blocked, 9);

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->method('findAll')->willReturn([$kingdom]);

        $tickRunner = $this->createMock(KingdomTickRunnerService::class);
        $tickRunner->method('recoverStaleTicks')->willReturn(0);
        $tickRunner->method('findBlockedTick')->willReturn($blocked);
        $tickRunner->expects($this->never())->method('schedulePendingTicks');

        $tickOrchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $tickOrchestrator->expects($this->never())->method('orchestrate');

        $tester = new CommandTester(new ProcessTicksCommand(
            $kingdomRepository,
            $tickRunner,
            $tickOrchestrator,
            $this->createMock(KingdomTickLogRepository::class),
        ));

        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('blocked tick', $tester->getDisplay());
    }

    public function testSyncProcessesPendingWithoutOrchestrator(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 1);

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->method('findAll')->willReturn([$kingdom]);

        $tickRunner = $this->createMock(KingdomTickRunnerService::class);
        $tickRunner->method('recoverStaleTicks')->willReturn(0);
        $tickRunner->method('findBlockedTick')->willReturn(null);
        $tickRunner->method('hasPendingTicks')->willReturn(true);
        $tickRunner->method('schedulePendingTicks')->willReturn(0);
        $tickRunner->expects($this->once())->method('processPendingTicksSynchronously')->with($kingdom);

        $tickOrchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $tickOrchestrator->expects($this->never())->method('orchestrate');

        $tester = new CommandTester(new ProcessTicksCommand(
            $kingdomRepository,
            $tickRunner,
            $tickOrchestrator,
            $this->createMock(KingdomTickLogRepository::class),
        ));

        $this->assertSame(0, $tester->execute(['--sync' => true]));
    }

    public function testRejectsDryRunWithSync(): void
    {
        $tester = new CommandTester(new ProcessTicksCommand(
            $this->createMock(KingdomRepository::class),
            $this->createMock(KingdomTickRunnerService::class),
            $this->createMock(KingdomTickOrchestrator::class),
            $this->createMock(KingdomTickLogRepository::class),
        ));

        $this->assertSame(1, $tester->execute(['--dry-run' => true, '--sync' => true]));
        $this->assertStringContainsString('cannot be used together', $tester->getDisplay());
    }
}
