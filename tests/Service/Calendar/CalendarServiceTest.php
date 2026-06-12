<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Enum\TickType;
use App\Service\Calendar\CalendarService;
use App\Service\Calendar\TickScheduleCalculator;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\Training\TrainingQueueRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Repository\Event\EventRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class CalendarServiceTest extends TestCase
{
    private $scheduleCalculatorMock;
    private $tickLogRepositoryMock;
    private $trainingQueueRepositoryMock;
    private $leagueFixtureRepositoryMock;
    private $eventRepositoryMock;
    private $seasonRepositoryMock;
    private CalendarService $service;

    protected function setUp(): void
    {
        $this->scheduleCalculatorMock = $this->createMock(TickScheduleCalculator::class);
        $this->tickLogRepositoryMock = $this->createMock(KingdomTickLogRepository::class);
        $this->trainingQueueRepositoryMock = $this->createMock(TrainingQueueRepository::class);
        $this->leagueFixtureRepositoryMock = $this->createMock(LeagueFixtureRepository::class);
        $this->eventRepositoryMock = $this->createMock(EventRepository::class);
        $this->seasonRepositoryMock = $this->createMock(LeagueSeasonRepository::class);

        $this->service = new CalendarService(
            $this->scheduleCalculatorMock,
            $this->tickLogRepositoryMock,
            $this->trainingQueueRepositoryMock,
            $this->leagueFixtureRepositoryMock,
            $this->eventRepositoryMock,
            $this->seasonRepositoryMock
        );
    }

    public function testGetCalendarFeedAggregatesData(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setTimezone('UTC');

        $start = new \DateTimeImmutable('2026-06-08T00:00:00Z');
        $end = new \DateTimeImmutable('2026-06-08T23:59:59Z');

        // 1. Mock Schedule Calculator (1 tick)
        $this->seasonRepositoryMock
            ->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['kingdom' => $kingdom, 'status' => \App\Enum\LeagueSeasonStatus::Active], null, null],
                [['kingdom' => $kingdom], ['seasonNumber' => 'DESC'], null],
            ]);

        $this->scheduleCalculatorMock
            ->method('generateOccurrences')
            ->willReturn([
                ['type' => TickType::FatigueRecovery, 'time' => new \DateTimeImmutable('2026-06-08T04:00:00Z')],
            ]);

        // 2. Mock League Fixtures (0 returned)
        $this->leagueFixtureRepositoryMock
            ->method('findFixturesInPeriod')
            ->willReturn([]);

        // 3. Mock Events (0 returned)
        $this->eventRepositoryMock
            ->method('findEventsInPeriod')
            ->willReturn([]);

        $feed = $this->service->getCalendarFeed($kingdom, $start, $end, null);

        $this->assertCount(1, $feed);
        $this->assertSame('system_tick', $feed[0]['type']);
        $this->assertSame('Fatigue & Form Recovery', $feed[0]['title']);
        $this->assertSame('system_only', $feed[0]['visibility']);
        $this->assertSame('scheduled', $feed[0]['status']);
    }
}
