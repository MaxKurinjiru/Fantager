<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Message\ExecuteSingleTickHandler;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\Calendar\KingdomTickRunnerService;
use App\Service\Calendar\TickScheduleCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class KingdomTickRunnerRetryTest extends TestCase
{
    public function testRetryFailedTicksResetsStatusAndRetryCount(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Main');
        $idProperty = new \ReflectionProperty(Kingdom::class, 'id');
        $idProperty->setValue($kingdom, 1);

        $log = new KingdomTickLog();
        $log->setKingdom($kingdom);
        $log->setTickType(TickType::DailyReset);
        $log->setScheduledAt(new \DateTimeImmutable('2026-06-08 00:00:00', new \DateTimeZone('UTC')));
        $log->setStatus('failed');
        $log->setRetryCount(3);
        $log->setErrorMessage('boom');

        $tickLogRepository = $this->createMock(KingdomTickLogRepository::class);
        $tickLogRepository->expects($this->once())
            ->method('findFailed')
            ->with($kingdom, null)
            ->willReturn([$log]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new KingdomTickRunnerService(
            $tickLogRepository,
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $this->createStub(ExecuteSingleTickHandler::class),
            $em,
        );

        $reset = $service->retryFailedTicks($kingdom);

        $this->assertCount(1, $reset);
        $this->assertSame('pending', $log->getStatus());
        $this->assertSame(0, $log->getRetryCount());
        $this->assertStringContainsString('Operator retry', (string) $log->getErrorMessage());
    }

    public function testRetryUnknownTickIdThrows(): void
    {
        $kingdom = new Kingdom();
        $tickLogRepository = $this->createMock(KingdomTickLogRepository::class);
        $tickLogRepository->method('findFailed')->willReturn([]);

        $service = new KingdomTickRunnerService(
            $tickLogRepository,
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $this->createStub(ExecuteSingleTickHandler::class),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectException(\DomainException::class);
        $service->retryFailedTicks($kingdom, 99);
    }
}
