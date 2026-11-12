<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Service\Calendar\KingdomTickRunnerService;
use App\Service\Calendar\TickHealthMonitor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[AllowMockObjectsWithoutExpectations]
class TickHealthMonitorTest extends TestCase
{
    public function testAlertsAreDeduplicatedAcrossRepeatedChecks(): void
    {
        $kingdom = new Kingdom();
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 7);
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql): array {
            return str_contains($sql, "status = 'failed'") ? ['id' => 11] : ['id' => 12, 'status' => 'processing'];
        });
        $connection->method('fetchOne')->willReturn('2020-01-01 00:00:00');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $runner = $this->createStub(KingdomTickRunnerService::class);
        $runner->method('getStatusSnapshot')->willReturn(['combat_recoveries' => [['battle_id' => 42, 'round' => 3]]]);
        $logger = $this->createMock(LoggerInterface::class);
        $messages = [];
        $logger->expects($this->exactly(4))->method('error')->willReturnCallback(static function (string $message) use (&$messages): void {
            $messages[] = $message;
        });
        $monitor = new TickHealthMonitor($em, $runner, new ArrayAdapter(), $logger);

        $monitor->checkKingdom($kingdom);
        $monitor->checkQueue();
        $monitor->checkKingdom($kingdom);
        $monitor->checkQueue();

        self::assertStringContainsString('failed tick 11', $messages[0]);
        self::assertStringContainsString('tick 12', $messages[1]);
        self::assertStringContainsString('battle 42', $messages[2]);
        self::assertStringContainsString('async queue', $messages[3]);
    }

    public function testHealthyPipelineDoesNotAlert(): void
    {
        $kingdom = new Kingdom();
        (new \ReflectionProperty(Kingdom::class, 'id'))->setValue($kingdom, 7);
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->method('fetchOne')->willReturn(null);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $runner = $this->createStub(KingdomTickRunnerService::class);
        $runner->method('getStatusSnapshot')->willReturn(['combat_recoveries' => []]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $monitor = new TickHealthMonitor($em, $runner, new ArrayAdapter(), $logger);

        $monitor->checkKingdom($kingdom);
        $monitor->checkQueue();
    }

    public function testRepeatedQueueAlertCapturesOneSentryEvent(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchOne')->willReturn('2020-01-01 00:00:00');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);
        $hub = $this->createMock(\Sentry\State\HubInterface::class);
        $hub->method('withScope')->willReturnCallback(static fn (callable $callback): mixed => $callback(new \Sentry\State\Scope()));
        $hub->expects($this->once())->method('captureEvent')->with($this->callback(
            static fn (\Sentry\Event $event): bool => str_contains((string) $event->getMessage(), 'async queue'),
        ));
        $logger = new \Monolog\Logger('app', [new \Sentry\Monolog\Handler($hub, \Monolog\Level::Error)]);
        $monitor = new TickHealthMonitor($em, $this->createStub(KingdomTickRunnerService::class), new ArrayAdapter(), $logger);

        $monitor->checkQueue();
        $monitor->checkQueue();
    }
}
