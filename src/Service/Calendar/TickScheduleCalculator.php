<?php

declare(strict_types=1);

namespace App\Service\Calendar;

use App\Enum\TickType;

class TickScheduleCalculator
{
    /**
     * Generates all scheduled tick occurrences between two datetimes for a given timezone.
     * Returned datetimes are normalized back to UTC.
     *
     * @return list<array{type: TickType, time: \DateTimeImmutable}>
     */
    public function generateOccurrences(
        \DateTimeImmutable $fromUtc,
        \DateTimeImmutable $toUtc,
        string $timezone,
        ?\DateTimeImmutable $seasonStartDate = null,
    ): array {
        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Exception) {
            $tz = new \DateTimeZone('UTC');
        }

        $fromLocal = $fromUtc->setTimezone($tz);
        $toLocal = $toUtc->setTimezone($tz);

        $occurrences = [];

        // Loop through each day from the day before $fromLocal to the day after $toLocal to ensure boundary coverage
        $current = $fromLocal->modify('-1 day')->setTime(0, 0, 0);
        $end = $toLocal->modify('+1 day');

        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');

            // 1. Daily Reset (00:00)
            $tDaily = new \DateTimeImmutable($dateStr.' 00:00:00', $tz);
            if ($tDaily > $fromLocal && $tDaily <= $toLocal) {
                $occurrences[] = [
                    'type' => TickType::DailyReset,
                    'time' => $tDaily->setTimezone(new \DateTimeZone('UTC')),
                ];
            }

            // 2. Fatigue Recovery (04:00)
            $tFatigue = new \DateTimeImmutable($dateStr.' 04:00:00', $tz);
            if ($tFatigue > $fromLocal && $tFatigue <= $toLocal) {
                $occurrences[] = [
                    'type' => TickType::FatigueRecovery,
                    'time' => $tFatigue->setTimezone(new \DateTimeZone('UTC')),
                ];
            }

            // 3. Weekly Training (Friday 10:00)
            if (5 === (int) $current->format('N')) {
                $tTraining = new \DateTimeImmutable($dateStr.' 10:00:00', $tz);
                if ($tTraining > $fromLocal && $tTraining <= $toLocal) {
                    $occurrences[] = [
                        'type' => TickType::WeeklyTraining,
                        'time' => $tTraining->setTimezone(new \DateTimeZone('UTC')),
                    ];
                }
            }

            // 4. League Match (Tuesday and Friday 18:00)
            $dayOfWeek = (int) $current->format('N');
            if (2 === $dayOfWeek || 5 === $dayOfWeek) {
                $tMatch = new \DateTimeImmutable($dateStr.' 18:00:00', $tz);
                if ($tMatch > $fromLocal && $tMatch <= $toLocal) {
                    $occurrences[] = [
                        'type' => TickType::LeagueMatch,
                        'time' => $tMatch->setTimezone(new \DateTimeZone('UTC')),
                    ];
                }
            }

            // 5. Season Transition (Friday 19:00)
            if (5 === (int) $current->format('N')) {
                $tTransition = new \DateTimeImmutable($dateStr.' 19:00:00', $tz);
                if ($tTransition > $fromLocal && $tTransition <= $toLocal) {
                    $isWeek11 = false;
                    if (null !== $seasonStartDate) {
                        $prepMonday = (1 === (int) $seasonStartDate->format('N'))
                            ? $seasonStartDate->setTime(0, 0, 0)
                            : $seasonStartDate->modify('next monday')->setTime(0, 0, 0);
                        // Monday of Week 11 is prepMonday + 10 weeks
                        $mondayWeek11 = $prepMonday->modify('+10 weeks');
                        // Sunday of Week 11 is prepMonday + 11 weeks - 1 second
                        $sundayWeek11 = $prepMonday->modify('+11 weeks')->modify('-1 second');
                        if ($tTransition >= $mondayWeek11 && $tTransition <= $sundayWeek11) {
                            $isWeek11 = true;
                        }
                    }
                    if ($isWeek11) {
                        $occurrences[] = [
                            'type' => TickType::SeasonTransition,
                            'time' => $tTransition->setTimezone(new \DateTimeZone('UTC')),
                        ];
                    }
                }
            }

            // 6. Weekly Reset (Sunday 23:59)
            if (7 === (int) $current->format('N')) {
                $tReset = new \DateTimeImmutable($dateStr.' 23:59:00', $tz);
                if ($tReset > $fromLocal && $tReset <= $toLocal) {
                    $occurrences[] = [
                        'type' => TickType::WeeklyReset,
                        'time' => $tReset->setTimezone(new \DateTimeZone('UTC')),
                    ];
                }
            }

            // 7. Race Optimization (Sunday 09:30)
            if (7 === (int) $current->format('N')) {
                $tRaceOpt = new \DateTimeImmutable($dateStr.' 09:30:00', $tz);
                if ($tRaceOpt > $fromLocal && $tRaceOpt <= $toLocal) {
                    $occurrences[] = [
                        'type' => TickType::RaceOptimization,
                        'time' => $tRaceOpt->setTimezone(new \DateTimeZone('UTC')),
                    ];
                }
            }

            $current = $current->modify('+1 day');
        }

        // Sort occurrences chronologically
        usort($occurrences, static function (array $a, array $b): int {
            return $a['time'] <=> $b['time'];
        });

        return $occurrences;
    }
}
