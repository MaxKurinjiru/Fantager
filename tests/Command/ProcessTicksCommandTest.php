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

        $tickOrchestrator = $this->createMock(KingdomTickOrchestrator::class);
        $tickOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->with($kingdom);

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
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--time' => $now->format('Y-m-d H:i:s'),
        ]);

        $this->assertSame(0, $exitCode);

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
}
