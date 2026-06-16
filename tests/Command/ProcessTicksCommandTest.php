<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ProcessTicksCommand;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Message\ProcessKingdomTicksMessage;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\Calendar\TickScheduleCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

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

        $now = new \DateTimeImmutable('2026-06-09T18:30:00Z');
        $leagueMatchTime = new \DateTimeImmutable('2026-06-09T18:00:00Z');
        $fatigueRecoveryTime = new \DateTimeImmutable('2026-06-09T04:00:00Z');

        $kingdomRepository = $this->createMock(KingdomRepository::class);
        $kingdomRepository->method('findAll')->willReturn([$kingdom]);

        $tickLogRepository = $this->createMock(KingdomTickLogRepository::class);
        $tickLogRepository->method('findOneBy')->willReturnCallback(
            static function (array $criteria): ?KingdomTickLog {
                if (isset($criteria['status']) && is_array($criteria['status'])) {
                    return null;
                }

                return null;
            }
        );
        $tickLogRepository->method('getLatestCompletedTickTime')->willReturn(null);

        $seasonRepository = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepository->method('findOneBy')->willReturn(null);

        $scheduleCalculator = $this->createMock(TickScheduleCalculator::class);
        $scheduleCalculator->method('generateOccurrences')->willReturn([
            ['type' => TickType::FatigueRecovery, 'time' => $fatigueRecoveryTime],
            ['type' => TickType::LeagueMatch, 'time' => $leagueMatchTime],
        ]);

        $persistedLogs = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(
            static function (KingdomTickLog $log) use (&$persistedLogs): void {
                $persistedLogs[] = $log;
            }
        );

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ProcessKingdomTicksMessage::class))
            ->willReturn(new Envelope(new ProcessKingdomTicksMessage(1)));

        $command = new ProcessTicksCommand(
            $kingdomRepository,
            $tickLogRepository,
            $seasonRepository,
            $scheduleCalculator,
            $entityManager,
            $messageBus,
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
