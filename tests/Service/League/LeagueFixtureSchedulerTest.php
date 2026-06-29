<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Enum\LeagueFixtureStatus;
use App\Service\League\LeagueFixtureScheduler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LeagueFixtureSchedulerTest extends TestCase
{
    public function testScheduleFixturesForGroup(): void
    {
        // 1. Setup mock/dummy entities
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(90))
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                $this->assertInstanceOf(LeagueFixture::class, $entity);
            });

        $scheduler = new LeagueFixtureScheduler($em);

        $group = new LeagueGroup();
        
        /** @var list<Team> $teams */
        $teams = [];
        for ($i = 1; $i <= 10; $i++) {
            $team = $this->createStub(Team::class);
            $team->method('getId')
                ->willReturn($i);
            $teams[] = $team;

            $standing = new LeagueStanding();
            $standing->setGroup($group);
            $standing->setTeam($team);
            $group->getStandings()->add($standing);
        }

        // Season starts on a Thursday
        $startDate = new \DateTimeImmutable('2026-06-04'); // June 4th, 2026 (Thursday)
        // Monday of next week is June 8th, 2026.
        // Preparation Week is June 8th (Mon) to June 14th (Sun).
        // First match week is Week 2: Monday June 15th to Sunday June 21st.
        // Round 0 (Tue): June 16th at 18:00
        // Round 1 (Fri): June 19th at 18:00

        // 2. Run the scheduler
        $scheduler->scheduleFixturesForGroup($group, $startDate, 'UTC');

        $fixtures = $group->getFixtures();

        // 3. Assertions
        // Total fixtures: 18 rounds * (10 teams / 2) = 90 matches
        $this->assertCount(90, $fixtures);

        $teamMatches = [];
        $matchups = [];
        $weeklyHomeAway = []; // w -> teamId -> 'H' or 'A' or list

        for ($w = 0; $w < 9; $w++) {
            $weeklyHomeAway[$w] = [];
            for ($t = 1; $t <= 10; $t++) {
                $weeklyHomeAway[$w][$t] = ['H' => 0, 'A' => 0];
            }
        }

        /** @var LeagueFixture $fixture */
        foreach ($fixtures as $fixture) {
            $this->assertEquals(LeagueFixtureStatus::Scheduled, $fixture->getStatus());
            $this->assertSame($group, $fixture->getGroup());

            $homeId = $fixture->getHomeTeam()->getId();
            $awayId = $fixture->getAwayTeam()->getId();

            $this->assertNotNull($homeId);
            $this->assertNotNull($awayId);
            $this->assertNotEquals($homeId, $awayId);

            // Track matches played per team
            $teamMatches[$homeId] = ($teamMatches[$homeId] ?? 0) + 1;
            $teamMatches[$awayId] = ($teamMatches[$awayId] ?? 0) + 1;

            // Track matchups: A vs B where A < B. Value must hold the home team ID for Leg 1/2.
            $matchKey = ($homeId < $awayId) ? "$homeId-$awayId" : "$awayId-$homeId";
            if (!isset($matchups[$matchKey])) {
                $matchups[$matchKey] = [];
            }
            $matchups[$matchKey][] = $homeId;

            // Check date/time format
            $date = $fixture->getScheduledAt();
            $this->assertEquals('18:00:00', $date->format('H:i:s'));
            
            // Should be Tuesday (2) or Friday (5)
            $dayOfWeek = (int) $date->format('w');
            $this->assertTrue(in_array($dayOfWeek, [2, 5], true));

            // Verify Prep week (June 8th to June 14th) is completely empty of matches
            $this->assertGreaterThan(new \DateTimeImmutable('2026-06-14 23:59:59'), $date);

            // Determine week index (0-indexed play week, Weeks 2 to 10 -> 0 to 8)
            // Round index can be derived from dates.
            // June 15th week is play week 0.
            // Let's compute play week:
            // Days difference from prep Monday (June 8th)
            $daysDiff = $date->diff(new \DateTimeImmutable('2026-06-08'))->days;
            // Tuesday: 8 days diff -> (8/7) - 1 = 0
            // Friday: 11 days diff -> (11/7) - 1 = 0
            $playWeek = (int) ($daysDiff / 7) - 1;
            
            $this->assertGreaterThanOrEqual(0, $playWeek);
            $this->assertLessThan(9, $playWeek);

            assert(isset($weeklyHomeAway[$playWeek][$homeId]['H']));
            $weeklyHomeAway[$playWeek][$homeId]['H']++;
            assert(isset($weeklyHomeAway[$playWeek][$awayId]['A']));
            $weeklyHomeAway[$playWeek][$awayId]['A']++;
        }

        // Assert every team played 18 matches
        for ($t = 1; $t <= 10; $t++) {
            $this->assertEquals(18, $teamMatches[$t]);
        }

        // Assert every matchup happened exactly twice (once home, once away)
        foreach ($matchups as $key => $homeHistory) {
            $this->assertCount(2, $homeHistory);
            $this->assertNotEquals($homeHistory[0], $homeHistory[1], "Matchup $key did not alternate Home/Away");
        }

        // Assert weekly home/away balance (1 Home and 1 Away per week of play)
        for ($w = 0; $w < 9; $w++) {
            for ($t = 1; $t <= 10; $t++) {
                $this->assertEquals(1, $weeklyHomeAway[$w][$t]['H'], "Team $t did not play exactly 1 Home match in week $w");
                $this->assertEquals(1, $weeklyHomeAway[$w][$t]['A'], "Team $t did not play exactly 1 Away match in week $w");
            }
        }
    }

    public function testScheduleFixturesForGroupStoresUtcKickoffForNonUtcKingdom(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $scheduler = new LeagueFixtureScheduler($em);

        $group = new LeagueGroup();
        for ($i = 1; $i <= 10; ++$i) {
            $team = $this->createStub(Team::class);
            $team->method('getId')->willReturn($i);
            $standing = new LeagueStanding();
            $standing->setGroup($group);
            $standing->setTeam($team);
            $group->getStandings()->add($standing);
        }

        $captured = [];
        $em->method('persist')->willReturnCallback(function (LeagueFixture $fixture) use (&$captured): void {
            $captured[] = $fixture;
        });

        $startDate = new \DateTimeImmutable('2026-06-04');
        $scheduler->scheduleFixturesForGroup($group, $startDate, 'Europe/Prague');

        $this->assertNotEmpty($captured);
        $first = $captured[0];
        $local = $first->getScheduledAt()->setTimezone(new \DateTimeZone('Europe/Prague'));
        $this->assertSame('18:00:00', $local->format('H:i:s'));
        $this->assertContains((int) $local->format('w'), [2, 5]);
    }
}
