<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Service\League\LeagueGroupSeeder;
use PHPUnit\Framework\TestCase;

class LeagueGroupSeederTest extends TestCase
{
    private LeagueGroupSeeder $seeder;

    protected function setUp(): void
    {
        $this->seeder = new LeagueGroupSeeder();
    }

    public function testEachGroupReceivesOneTeamFromEveryStrengthPot(): void
    {
        $entries = [
            $this->entry('Winner A', 1, 30),
            $this->entry('Winner B', 1, 20),
            $this->entry('Mid A', 5, 12),
            $this->entry('Mid B', 5, 8),
            $this->entry('Last A', 10, 3),
            $this->entry('Last B', 10, 0),
        ];

        $groups = $this->seeder->distribute($entries, 2, $this->compareByPoints(...), $this->keepOrder(...));

        $this->assertCount(2, $groups);
        $this->assertSame(['Winner A', 'Mid A', 'Last A'], $this->names($groups[0]));
        $this->assertSame(['Winner B', 'Mid B', 'Last B'], $this->names($groups[1]));
    }

    public function testShuffleStaysInsideThePot(): void
    {
        $entries = [
            $this->entry('Winner A', 1, 30),
            $this->entry('Winner B', 1, 20),
            $this->entry('Last A', 10, 3),
            $this->entry('Last B', 10, 0),
        ];

        $groups = $this->seeder->distribute($entries, 2, $this->compareByPoints(...), $this->reverse(...));

        $this->assertSame(['Winner B', 'Last B'], $this->names($groups[0]));
        $this->assertSame(['Winner A', 'Last A'], $this->names($groups[1]));
    }

    public function testSingleGroupKeepsEveryTeamInSeedOrder(): void
    {
        $entries = [
            $this->entry('Third', 3, 1),
            $this->entry('First', 1, 9),
            $this->entry('Second', 2, 5),
        ];

        $groups = $this->seeder->distribute($entries, 1, $this->compareByPoints(...), $this->keepOrder(...));

        $this->assertSame(['First', 'Second', 'Third'], $this->names($groups[0]));
    }

    public function testSamePlaceIsOrderedByStandingComparison(): void
    {
        $entries = [
            $this->entry('Weaker first', 1, 10),
            $this->entry('Stronger first', 1, 18),
        ];

        $groups = $this->seeder->distribute($entries, 2, $this->compareByPoints(...), $this->keepOrder(...));

        $this->assertSame(['Stronger first'], $this->names($groups[0]));
        $this->assertSame(['Weaker first'], $this->names($groups[1]));
    }

    public function testNoGroupsReturnsEmpty(): void
    {
        $this->assertSame([], $this->seeder->distribute([], 0, $this->compareByPoints(...)));
    }

    /** @return array{team: Team, position: int, standing: LeagueStanding} */
    private function entry(string $name, int $position, int $points): array
    {
        $team = new Team();
        $team->setName($name);

        $standing = new LeagueStanding();
        $standing->setTeam($team);
        $standing->setPoints($points);

        return [
            'team' => $team,
            'position' => $position,
            'standing' => $standing,
        ];
    }

    private function compareByPoints(LeagueStanding $a, LeagueStanding $b): int
    {
        return $b->getPoints() <=> $a->getPoints();
    }

    /**
     * @param list<Team> $teams
     *
     * @return list<Team>
     */
    private function keepOrder(array $teams): array
    {
        return $teams;
    }

    /**
     * @param list<Team> $teams
     *
     * @return list<Team>
     */
    private function reverse(array $teams): array
    {
        return array_reverse($teams);
    }

    /**
     * @param list<Team> $teams
     *
     * @return list<string>
     */
    private function names(array $teams): array
    {
        return array_map(static fn (Team $team): string => $team->getName(), $teams);
    }
}
