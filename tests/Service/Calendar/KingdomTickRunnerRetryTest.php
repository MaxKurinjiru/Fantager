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
    public function testSchedulingPersistsTheFurthestRequestedHorizonWithoutTicks(): void
    {
        $kingdom = (new Kingdom())->setTimezone('UTC');
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $horizon = new \DateTimeImmutable('2026-06-09 18:00:00 UTC');
        $teamRepository = $this->createStub(\App\Repository\Team\TeamRepository::class);
        $teamRepository->method('findBy')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($teamRepository);
        $em->expects($this->exactly(2))->method('refresh')->with($kingdom, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
        $em->expects($this->exactly(2))->method('flush');
        $service = new KingdomTickRunnerService(
            $this->createStub(KingdomTickLogRepository::class),
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $this->createStub(ExecuteSingleTickHandler::class),
            $em,
        );

        $service->schedulePendingTicks($kingdom, $horizon);
        $service->schedulePendingTicks($kingdom, $horizon->modify('-1 day'));
        $service->schedulePendingTicks($kingdom, $horizon->modify('+1 day'), false);

        $this->assertEquals($horizon, $kingdom->getTickPlanningHorizon());
    }

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

    public function testInterruptedTickCanBeResetAfterWorkersStop(): void
    {
        $kingdom = new Kingdom();
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setStatus('processing')->setRetryCount(2);
        $repository = $this->createMock(KingdomTickLogRepository::class);
        $repository->expects($this->once())->method('findOneBy')
            ->with(['kingdom' => $kingdom, 'id' => 42, 'status' => 'processing'])->willReturn($log);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $service = new KingdomTickRunnerService(
            $repository,
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $this->createStub(ExecuteSingleTickHandler::class),
            $em,
        );

        $this->assertSame($log, $service->retryInterruptedTick($kingdom, 42));
        $this->assertSame('pending', $log->getStatus());
        $this->assertSame(0, $log->getRetryCount());
    }

    public function testInterruptedTickLinkedToBattleCannotBeRetriedAsKickoff(): void
    {
        $kingdom = new Kingdom();
        $fixture = (new \App\Entity\League\LeagueFixture())->setBattle(new \App\Entity\Combat\Battle());
        $log = (new KingdomTickLog())->setKingdom($kingdom)->setFixture($fixture)->setStatus('processing');
        $repository = $this->createStub(KingdomTickLogRepository::class);
        $repository->method('findOneBy')->willReturn($log);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $service = new KingdomTickRunnerService(
            $repository,
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $this->createStub(ExecuteSingleTickHandler::class),
            $em,
        );

        $this->expectExceptionMessage('require combat recovery');
        $service->retryInterruptedTick($kingdom, 42);
    }

    public function testSynchronousCatchUpStopsAtAsynchronousCombat(): void
    {
        $kingdom = new Kingdom();
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $match = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-09 18:00:00 UTC'))->setStatus('pending');
        (new \ReflectionProperty(KingdomTickLog::class, 'id'))->setValue($match, 10);
        $later = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-10 00:00:00 UTC'))->setStatus('pending');
        (new \ReflectionProperty(KingdomTickLog::class, 'id'))->setValue($later, 11);
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->method('findBy')->willReturn([$match, $later]);
        $handler = $this->createMock(ExecuteSingleTickHandler::class);
        $handler->expects($this->once())->method('__invoke')->willReturnCallback(static function () use ($match): void {
            $match->setStatus('awaiting_combat');
        });
        $service = new KingdomTickRunnerService(
            $logs,
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $handler,
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectExceptionMessage('requires asynchronous combat');
        $service->processPendingTicksSynchronously($kingdom);
    }

    public function testSynchronousCatchUpReloadsAfterSeasonTransition(): void
    {
        $kingdom = new Kingdom();
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $transition = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::SeasonTransition)
            ->setScheduledAt(new \DateTimeImmutable('2026-03-20 19:00:00 UTC'))->setStatus('pending');
        (new \ReflectionProperty(KingdomTickLog::class, 'id'))->setValue($transition, 10);
        $match = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::LeagueMatch)
            ->setScheduledAt(new \DateTimeImmutable('2026-03-24 18:00:00 UTC'))->setStatus('pending');
        (new \ReflectionProperty(KingdomTickLog::class, 'id'))->setValue($match, 11);
        $later = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
            ->setScheduledAt(new \DateTimeImmutable('2026-03-25 00:00:00 UTC'))->setStatus('pending');
        (new \ReflectionProperty(KingdomTickLog::class, 'id'))->setValue($later, 12);
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->expects($this->exactly(2))->method('findBy')->willReturnOnConsecutiveCalls(
            [$transition, $later],
            [$later, $match],
        );
        $handler = $this->createMock(ExecuteSingleTickHandler::class);
        $handler->expects($this->exactly(2))->method('__invoke')->willReturnCallback(
            static function ($message) use ($transition, $match): void {
                if (10 === $message->getTickLogId()) {
                    $transition->setStatus('completed');
                } elseif (11 === $message->getTickLogId()) {
                    $match->setStatus('awaiting_combat');
                } else {
                    throw new \RuntimeException('Later tick ran before the new match.');
                }
            },
        );
        $service = new KingdomTickRunnerService(
            $logs,
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $handler,
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectExceptionMessage('requires asynchronous combat');
        $service->processPendingTicksSynchronously($kingdom);
    }

    public function testSynchronousCatchUpStopsWhenTickWasClaimedElsewhere(): void
    {
        $kingdom = new Kingdom();
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 1);
        $first = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::WeeklyTraining)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-11 10:00:00 UTC'))->setStatus('pending');
        (new \ReflectionProperty(KingdomTickLog::class, 'id'))->setValue($first, 10);
        $later = (new KingdomTickLog())->setKingdom($kingdom)->setTickType(TickType::DailyReset)
            ->setScheduledAt(new \DateTimeImmutable('2026-06-12 00:00:00 UTC'))->setStatus('pending');
        (new \ReflectionProperty(KingdomTickLog::class, 'id'))->setValue($later, 11);
        $logs = $this->createMock(KingdomTickLogRepository::class);
        $logs->method('findBy')->willReturn([$first, $later]);
        $handler = $this->createMock(ExecuteSingleTickHandler::class);
        $handler->expects($this->once())->method('__invoke')->willReturnCallback(static function () use ($first): void {
            $first->setStatus('processing');
        });
        $service = new KingdomTickRunnerService(
            $logs,
            $this->createStub(LeagueSeasonRepository::class),
            $this->createStub(TickScheduleCalculator::class),
            $handler,
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectExceptionMessage('not completed');
        $service->processPendingTicksSynchronously($kingdom);
    }
}
