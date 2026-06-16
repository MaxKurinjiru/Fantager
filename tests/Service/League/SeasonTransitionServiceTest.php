<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueStanding;
use App\Entity\League\LeagueTier;
use App\Entity\Team\Team;
use App\Enum\LeagueSeasonStatus;
use App\Service\Economy\EconomyService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\League\LeagueFixtureScheduler;
use App\Service\League\SeasonTransitionService;
use App\Repository\League\LeagueSeasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class SeasonTransitionServiceTest extends TestCase
{
    private $entityManagerMock;
    private $fixtureSchedulerMock;
    private $economyServiceMock;
    private SeasonTransitionService $transitionService;

    protected function setUp(): void
    {
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->fixtureSchedulerMock = $this->createMock(LeagueFixtureScheduler::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);

        $this->transitionService = new SeasonTransitionService(
            $this->entityManagerMock,
            $this->fixtureSchedulerMock,
            $this->economyServiceMock,
            $this->createMock(TeamChronicleService::class),
        );
    }

    public function testPrepareUpcomingSeasonForFirstTime(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setSeasonLength(77);
        $kingdom->setLeagueTiersConfig([
            'teams_per_group' => 10,
            'tiers' => [
                [
                    'name' => 'T1',
                    'groups' => 1,
                    'promotion_slots' => 0,
                    'relegation_slots' => 2,
                    'rewards' => ['gold' => 5000]
                ],
                [
                    'name' => 'T2',
                    'groups' => 1,
                    'promotion_slots' => 2,
                    'relegation_slots' => 0,
                    'rewards' => ['gold' => 2500]
                ]
            ]
        ]);

        $seasonRepoMock = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepoMock->method('findOneBy')
            ->willReturn(null); // No previous season

        $this->entityManagerMock->method('getRepository')
            ->with(LeagueSeason::class)
            ->willReturn($seasonRepoMock);

        $this->entityManagerMock->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->logicalOr(
                $this->isInstanceOf(LeagueSeason::class),
                $this->isInstanceOf(LeagueTier::class),
                $this->isInstanceOf(LeagueGroup::class)
            ));

        $newSeason = $this->transitionService->prepareUpcomingSeason($kingdom);

        $this->assertSame(1, $newSeason->getSeasonNumber());
        $this->assertSame(LeagueSeasonStatus::Scheduled, $newSeason->getStatus());
        $this->assertCount(2, $newSeason->getTiers());
    }

    public function testExecuteTransitionWithPromotionRelegationAndRewards(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $kingdom->setSeasonLength(77);
        $kingdom->setLeagueTiersConfig([
            'teams_per_group' => 10,
            'tiers' => [
                [
                    'name' => 'T1',
                    'groups' => 1,
                    'promotion_slots' => 0,
                    'relegation_slots' => 1,
                    'rewards' => ['gold' => 5000]
                ],
                [
                    'name' => 'T2',
                    'groups' => 1,
                    'promotion_slots' => 1,
                    'relegation_slots' => 0,
                    'rewards' => ['gold' => 2500]
                ]
            ]
        ]);

        // Setup current season, tiers, groups, standings
        $currentSeason = new LeagueSeason();
        $currentSeason->setKingdom($kingdom);
        $currentSeason->setSeasonNumber(1);
        $currentSeason->setStatus(LeagueSeasonStatus::Active);

        $t1Current = new LeagueTier();
        $this->setEntityId($t1Current, 1);
        $t1Current->setTierName('T1');
        $t1Current->setRewards(['gold' => 5000]);
        $t1Current->setRelegationSlots(1);
        $currentSeason->addTier($t1Current);

        $t1GroupCurrent = new LeagueGroup();
        $this->setEntityId($t1GroupCurrent, 1);
        $t1GroupCurrent->setGroupName('T1-G1');
        $t1Current->addGroup($t1GroupCurrent);

        // Populate T1 with 10 teams
        for ($i = 1; $i <= 10; $i++) {
            $team = new Team();
            $this->setEntityId($team, $i);
            $team->setKingdom($kingdom);
            $team->setName("T1 Team $i");
            $team->setIsNpc(true);
            $team->setReputation($i * 10);

            $standing = new LeagueStanding();
            $standing->setTeam($team);
            $standing->setGroup($t1GroupCurrent);
            $standing->setPoints($i * 3); // last team (i=1) has lowest points (3), first (i=10) has highest (30)
            $standing->setGoalDifference($i);
            $standing->setWins($i);
            $t1GroupCurrent->getStandings()->add($standing);
        }

        $t2Current = new LeagueTier();
        $this->setEntityId($t2Current, 2);
        $t2Current->setTierName('T2');
        $t2Current->setRewards(['gold' => 2500]);
        $t2Current->setPromotionSlots(1);
        $currentSeason->addTier($t2Current);

        $t2GroupCurrent = new LeagueGroup();
        $this->setEntityId($t2GroupCurrent, 2);
        $t2GroupCurrent->setGroupName('T2-G1');
        $t2Current->addGroup($t2GroupCurrent);

        // Populate T2 with 10 teams
        for ($i = 1; $i <= 10; $i++) {
            $team = new Team();
            $this->setEntityId($team, 10 + $i);
            $team->setKingdom($kingdom);
            $team->setName("T2 Team $i");
            $team->setIsNpc(true);

            $standing = new LeagueStanding();
            $standing->setTeam($team);
            $standing->setGroup($t2GroupCurrent);
            $standing->setPoints($i * 3); // last team (i=10) has highest points (30)
            $standing->setGoalDifference($i);
            $standing->setWins($i);
            $t2GroupCurrent->getStandings()->add($standing);
        }

        // Setup upcoming season skeleton
        $upcomingSeason = new LeagueSeason();
        $upcomingSeason->setKingdom($kingdom);
        $upcomingSeason->setSeasonNumber(2);
        $upcomingSeason->setStatus(LeagueSeasonStatus::Scheduled);
        $upcomingSeason->setStartDate(new \DateTimeImmutable('2026-06-22'));

        $t1Upcoming = new LeagueTier();
        $this->setEntityId($t1Upcoming, 3);
        $t1Upcoming->setTierName('T1');
        $upcomingSeason->addTier($t1Upcoming);
        $t1GroupUpcoming = new LeagueGroup();
        $this->setEntityId($t1GroupUpcoming, 3);
        $t1GroupUpcoming->setGroupName('T1-G1');
        $t1Upcoming->addGroup($t1GroupUpcoming);

        $t2Upcoming = new LeagueTier();
        $this->setEntityId($t2Upcoming, 4);
        $t2Upcoming->setTierName('T2');
        $upcomingSeason->addTier($t2Upcoming);
        $t2GroupUpcoming = new LeagueGroup();
        $this->setEntityId($t2GroupUpcoming, 4);
        $t2GroupUpcoming->setGroupName('T2-G1');
        $t2Upcoming->addGroup($t2GroupUpcoming);

        // Repositories Mocking
        $seasonRepoMock = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepoMock->method('findOneBy')
            ->willReturnMap([
                [['kingdom' => $kingdom, 'status' => LeagueSeasonStatus::Active], null, $currentSeason],
                [['kingdom' => $kingdom, 'status' => LeagueSeasonStatus::Scheduled], null, $upcomingSeason],
            ]);

        $this->entityManagerMock->method('getRepository')
            ->with(LeagueSeason::class)
            ->willReturn($seasonRepoMock);

        // We expect EconomyService to credit Gold to all 20 teams
        $this->economyServiceMock->expects($this->atLeastOnce())
            ->method('addGold');

        $this->entityManagerMock->method('getReference')
            ->willReturnCallback(function (string $class, $id) {
                $team = new Team();
                $this->setEntityId($team, $id);
                return $team;
            });

        // Run transition
        $this->transitionService->executeTransition($kingdom);

        // Assertions
        $this->assertSame(LeagueSeasonStatus::Completed, $currentSeason->getStatus());
        $this->assertSame(LeagueSeasonStatus::Active, $upcomingSeason->getStatus());
    }

    public function testPrepareUpcomingSeasonWithCustomGroupNames(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setSeasonLength(77);
        $kingdom->setLeagueTiersConfig([
            'teams_per_group' => 10,
            'tiers' => [
                [
                    'name' => 'T1',
                    'groups' => ['Alpha Group', 'Beta Group'],
                    'promotion_slots' => 0,
                    'relegation_slots' => 2,
                    'rewards' => ['gold' => 5000]
                ],
                [
                    'name' => 'T2',
                    'groups' => 2,
                    'promotion_slots' => 2,
                    'relegation_slots' => 0,
                    'rewards' => ['gold' => 2500]
                ]
            ]
        ]);

        $seasonRepoMock = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepoMock->method('findOneBy')
            ->willReturn(null); // No previous season

        $this->entityManagerMock->method('getRepository')
            ->with(LeagueSeason::class)
            ->willReturn($seasonRepoMock);

        $newSeason = $this->transitionService->prepareUpcomingSeason($kingdom);

        $tiers = $newSeason->getTiers()->toArray();
        $this->assertCount(2, $tiers);

        // Tier 1 groups (custom names from array)
        $t1Groups = $tiers[0]->getGroups()->toArray();
        $this->assertCount(2, $t1Groups);
        $this->assertSame('Alpha Group', $t1Groups[0]->getGroupName());
        $this->assertSame('Beta Group', $t1Groups[1]->getGroupName());

        // Tier 2 groups (default fallback naming from count)
        $t2Groups = $tiers[1]->getGroups()->toArray();
        $this->assertCount(2, $t2Groups);
        $this->assertSame('T2-G1', $t2Groups[0]->getGroupName());
        $this->assertSame('T2-G2', $t2Groups[1]->getGroupName());
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflector = new \ReflectionClass($entity);
        $property = $reflector->getProperty('id');
        $property->setValue($entity, $id);
    }
}
