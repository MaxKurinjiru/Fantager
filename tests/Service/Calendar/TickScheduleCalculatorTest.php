<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Enum\TickType;
use App\Service\Calendar\TickScheduleCalculator;
use PHPUnit\Framework\TestCase;

class TickScheduleCalculatorTest extends TestCase
{
    private TickScheduleCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TickScheduleCalculator();
    }

    public function testGenerateOccurrencesInUtc(): void
    {
        // Monday June 8th 2026 00:00:00 to Monday June 8th 2026 23:59:59 (UTC)
        $from = new \DateTimeImmutable('2026-06-08T00:00:00Z');
        $to = new \DateTimeImmutable('2026-06-08T23:59:59Z');

        $occurrences = $this->calculator->generateOccurrences($from, $to, 'UTC');

        // Expected in UTC:
        // DailyReset at 00:00:00Z (out of range, as it's exactly 00:00:00, but is it > from? wait, > 2026-06-08T00:00:00Z, so it won't be included. Let's check.)
        // FatigueRecovery at 04:00:00Z
        
        $types = array_map(static fn(array $o): string => $o['type']->value, $occurrences);
        
        $this->assertContains(TickType::FatigueRecovery->value, $types);
        
        foreach ($occurrences as $o) {
            if ($o['type'] === TickType::FatigueRecovery) {
                $this->assertSame('2026-06-08T04:00:00+00:00', $o['time']->format(\DateTimeInterface::ATOM));
            }
        }
    }

    public function testGenerateOccurrencesTuesdayWithTimezone(): void
    {
        // Tuesday June 9th 2026 00:00:00 to Wednesday June 10th 2026 00:00:00 in Europe/Prague
        // Europe/Prague timezone in summer is UTC+2
        $from = new \DateTimeImmutable('2026-06-08T21:59:59Z'); // 23:59:59 in Prague Monday
        $to = new \DateTimeImmutable('2026-06-09T22:00:00Z'); // 00:00:00 in Prague Wednesday

        $occurrences = $this->calculator->generateOccurrences($from, $to, 'Europe/Prague');

        $types = array_map(static fn(array $o): string => $o['type']->value, $occurrences);

        // Expected in Prague on Tuesday:
        // Daily Reset at 00:00 local (22:00 UTC Monday) - wait, 2026-06-09T00:00:00 local is 2026-06-08T22:00:00 UTC, which is > Monday 21:59:59 UTC.
        // Fatigue Recovery at 04:00 local (02:00 UTC)
        // League Match at 18:00 local (16:00 UTC)

        $this->assertContains(TickType::DailyReset->value, $types);
        $this->assertContains(TickType::FatigueRecovery->value, $types);
        $this->assertContains(TickType::LeagueMatch->value, $types);

        $dailyResets = array_values(array_filter($occurrences, static fn(array $o): bool => $o['type'] === TickType::DailyReset));
        $this->assertCount(2, $dailyResets);
        $this->assertSame('2026-06-08T22:00:00+00:00', $dailyResets[0]['time']->format(\DateTimeInterface::ATOM));
        $this->assertSame('2026-06-09T22:00:00+00:00', $dailyResets[1]['time']->format(\DateTimeInterface::ATOM));

        foreach ($occurrences as $o) {
            if ($o['type'] === TickType::LeagueMatch) {
                $this->assertSame('2026-06-09T16:00:00+00:00', $o['time']->format(\DateTimeInterface::ATOM));
            }
        }
    }

    public function testGenerateOccurrencesFridayWithTransition(): void
    {
        // Friday June 12th 2026 00:00:00 to Saturday June 13th 2026 00:00:00 UTC
        $from = new \DateTimeImmutable('2026-06-12T00:00:00Z');
        $to = new \DateTimeImmutable('2026-06-12T23:59:59Z');

        // Pass season start date such that June 12th 2026 falls on Friday of Week 11
        // (prepMonday = March 30, 2026; Monday of Week 11 = June 8, 2026)
        $seasonStart = new \DateTimeImmutable('2026-03-26');

        $occurrences = $this->calculator->generateOccurrences($from, $to, 'UTC', $seasonStart);

        $types = array_map(static fn(array $o): string => $o['type']->value, $occurrences);

        // Expected on Friday:
        // Fatigue Recovery (04:00)
        // Weekly Training (10:00)
        // League Match (18:00)
        // Season Transition (19:00)
        
        $this->assertContains(TickType::FatigueRecovery->value, $types);
        $this->assertContains(TickType::WeeklyTraining->value, $types);
        $this->assertContains(TickType::LeagueMatch->value, $types);
        $this->assertContains(TickType::SeasonTransition->value, $types);

        foreach ($occurrences as $o) {
            if ($o['type'] === TickType::WeeklyTraining) {
                $this->assertSame('2026-06-12T10:00:00+00:00', $o['time']->format(\DateTimeInterface::ATOM));
            }
            if ($o['type'] === TickType::SeasonTransition) {
                $this->assertSame('2026-06-12T19:00:00+00:00', $o['time']->format(\DateTimeInterface::ATOM));
            }
        }
    }

    public function testGenerateOccurrencesFridayWithoutTransition(): void
    {
        // Friday June 12th 2026 00:00:00 to Saturday June 13th 2026 00:00:00 UTC
        $from = new \DateTimeImmutable('2026-06-12T00:00:00Z');
        $to = new \DateTimeImmutable('2026-06-12T23:59:59Z');

        // No season start date passed (or non-Week 11 season start date passed, e.g., Week 10)
        $seasonStart = new \DateTimeImmutable('2026-04-02'); // June 12th is Week 10 Friday (April 6 prepMonday + 9 weeks = June 8 Monday Week 10)

        $occurrences = $this->calculator->generateOccurrences($from, $to, 'UTC', $seasonStart);

        $types = array_map(static fn(array $o): string => $o['type']->value, $occurrences);

        $this->assertContains(TickType::FatigueRecovery->value, $types);
        $this->assertContains(TickType::WeeklyTraining->value, $types);
        $this->assertContains(TickType::LeagueMatch->value, $types);
        $this->assertNotContains(TickType::SeasonTransition->value, $types);
    }

    public function testGenerateOccurrencesRaceOptimizationOnSunday(): void
    {
        // Sunday June 14th 2026 00:00:00 to Monday June 15th 2026 00:00:00 UTC
        $from = new \DateTimeImmutable('2026-06-14T00:00:00Z');
        $to = new \DateTimeImmutable('2026-06-14T23:59:59Z');

        $occurrences = $this->calculator->generateOccurrences($from, $to, 'UTC');

        $types = array_map(static fn(array $o): string => $o['type']->value, $occurrences);

        $this->assertContains(TickType::RaceOptimization->value, $types);

        foreach ($occurrences as $o) {
            if ($o['type'] === TickType::RaceOptimization) {
                $this->assertSame('2026-06-14T09:30:00+00:00', $o['time']->format(\DateTimeInterface::ATOM));
            }
        }
    }
}
