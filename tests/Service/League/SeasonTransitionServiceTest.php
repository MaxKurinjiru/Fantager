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
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Doctrine\ORM\EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\League\LeagueFixtureScheduler */
    private $fixtureSchedulerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\EconomyService */
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
            $this->createMock(\App\Service\Notification\NotificationHelper::class),
            new \App\Service\League\LeagueGroupSeeder(),
            $this->createMock(\App\Service\League\SeasonReportService::class),
        );
    }


    public function testPrepareUpcomingSeasonForFirstTime(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setSeasonLength(77);
        $kingdom->setLeagueTiersConfig($this->lockedConfig());

        $seasonRepoMock = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepoMock->method('findOneBy')
            ->willReturn(null); // No previous season

        $this->entityManagerMock->method('getRepository')
            ->willReturnCallback(function ($className) use ($seasonRepoMock) {
                $this->assertSame(LeagueSeason::class, $className);
                return $seasonRepoMock;
            });

        $this->entityManagerMock->method('persist')
            ->willReturnCallback(function ($entity) {
                $this->assertTrue(
                    $entity instanceof LeagueSeason
                    || $entity instanceof LeagueTier
                    || $entity instanceof LeagueGroup
                );
            });

        $newSeason = $this->transitionService->prepareUpcomingSeason($kingdom);

        $this->assertSame(1, $newSeason->getSeasonNumber());
        $this->assertSame(LeagueSeasonStatus::Scheduled, $newSeason->getStatus());
        $tiers = $newSeason->getTiers()->toArray();
        $this->assertCount(3, $tiers);
        $this->assertSame(['1-A'], array_map(static fn (LeagueGroup $group): string => $group->getGroupName(), $tiers[0]->getGroups()->toArray()));
        $this->assertSame(['2-A', '2-B'], array_map(static fn (LeagueGroup $group): string => $group->getGroupName(), $tiers[1]->getGroups()->toArray()));
        $this->assertSame(['3-A', '3-B', '3-C'], array_map(static fn (LeagueGroup $group): string => $group->getGroupName(), $tiers[2]->getGroups()->toArray()));
    }

    public function testExecuteTransitionWithPromotionRelegationAndRewards(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $kingdom->setTimezone('UTC');
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
        $t1Current->setPromotionSlots(0);
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
        $t2Current->setRelegationSlots(0);
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
        $t1Upcoming->setPromotionSlots(0);
        $t1Upcoming->setRelegationSlots(1);
        $upcomingSeason->addTier($t1Upcoming);
        $t1GroupUpcoming = new LeagueGroup();
        $this->setEntityId($t1GroupUpcoming, 3);
        $t1GroupUpcoming->setGroupName('T1-G1');
        $t1Upcoming->addGroup($t1GroupUpcoming);

        $t2Upcoming = new LeagueTier();
        $this->setEntityId($t2Upcoming, 4);
        $t2Upcoming->setTierName('T2');
        $t2Upcoming->setPromotionSlots(1);
        $t2Upcoming->setRelegationSlots(0);
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
            ->willReturnCallback(function ($className) use ($seasonRepoMock) {
                $this->assertSame(LeagueSeason::class, $className);
                return $seasonRepoMock;
            });

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

    public function testPrepareUpcomingSeasonCopiesFinishedSeasonAndIgnoresJson(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setSeasonLength(77);
        $kingdom->setLeagueTiersConfig([
            'teams_per_group' => 8,
            'tiers' => [
                ['name' => 'Only', 'groups' => 1, 'promotion_slots' => 0, 'relegation_slots' => 0],
            ],
        ]);

        $finished = new LeagueSeason();
        $finished->setKingdom($kingdom);
        $finished->setSeasonNumber(4);
        $finished->setStatus(LeagueSeasonStatus::Active);
        $finished->setEndDate(new \DateTimeImmutable('2026-08-16'));

        $tier = new LeagueTier();
        $this->setEntityId($tier, 1);
        $tier->setTierName('Alpha');
        $tier->setPromotionSlots(0);
        $tier->setRelegationSlots(2);
        $tier->setRewards(['gold' => 5000]);
        $finished->addTier($tier);

        $alpha = new LeagueGroup();
        $this->setEntityId($alpha, 1);
        $alpha->setGroupName('Alpha Group');
        $tier->addGroup($alpha);
        $beta = new LeagueGroup();
        $this->setEntityId($beta, 2);
        $beta->setGroupName('Beta Group');
        $tier->addGroup($beta);

        $seasonRepoMock = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepoMock->method('findOneBy')->willReturn($finished);

        $this->entityManagerMock->method('getRepository')
            ->willReturnCallback(function ($className) use ($seasonRepoMock) {
                $this->assertSame(LeagueSeason::class, $className);
                return $seasonRepoMock;
            });

        $newSeason = $this->transitionService->prepareUpcomingSeason($kingdom);

        $this->assertSame(5, $newSeason->getSeasonNumber());
        $copied = $newSeason->getTiers()->toArray();
        $this->assertCount(1, $copied);
        $this->assertSame('Alpha', $copied[0]->getTierName());
        $this->assertSame(2, $copied[0]->getRelegationSlots());
        $this->assertSame(
            ['Alpha Group', 'Beta Group'],
            array_map(static fn (LeagueGroup $group): string => $group->getGroupName(), $copied[0]->getGroups()->toArray())
        );
    }

    public function testExecuteTransitionRejectsADifferentGroupCount(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');

        $currentSeason = new LeagueSeason();
        $currentSeason->setKingdom($kingdom);
        $currentSeason->setSeasonNumber(1);
        $currentSeason->setStatus(LeagueSeasonStatus::Active);
        $currentTier = new LeagueTier();
        $this->setEntityId($currentTier, 1);
        $currentTier->setTierName('T1');
        $currentTier->setPromotionSlots(0);
        $currentTier->setRelegationSlots(0);
        $currentSeason->addTier($currentTier);
        $currentTier->addGroup(new LeagueGroup());

        $upcomingSeason = new LeagueSeason();
        $upcomingSeason->setKingdom($kingdom);
        $upcomingSeason->setStatus(LeagueSeasonStatus::Scheduled);
        $upcomingTier = new LeagueTier();
        $this->setEntityId($upcomingTier, 2);
        $upcomingTier->setTierName('T1');
        $upcomingTier->setPromotionSlots(0);
        $upcomingTier->setRelegationSlots(0);
        $upcomingSeason->addTier($upcomingTier);
        $upcomingTier->addGroup(new LeagueGroup());
        $upcomingTier->addGroup(new LeagueGroup());

        $seasonRepoMock = $this->createMock(LeagueSeasonRepository::class);
        $seasonRepoMock->method('findOneBy')
            ->willReturnMap([
                [['kingdom' => $kingdom, 'status' => LeagueSeasonStatus::Active], null, $currentSeason],
                [['kingdom' => $kingdom, 'status' => LeagueSeasonStatus::Scheduled], null, $upcomingSeason],
            ]);

        $this->entityManagerMock->method('getRepository')
            ->willReturnCallback(function ($className) use ($seasonRepoMock) {
                $this->assertSame(LeagueSeason::class, $className);
                return $seasonRepoMock;
            });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('group counts');

        $this->transitionService->executeTransition($kingdom);
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedConfig(): array
    {
        return [
            'teams_per_group' => 10,
            'tiers' => [
                [
                    'name' => 'T1',
                    'groups' => ['1-A'],
                    'promotion_slots' => 0,
                    'relegation_slots' => 4,
                    'rewards' => ['gold' => 5000],
                ],
                [
                    'name' => 'T2',
                    'groups' => ['2-A', '2-B'],
                    'promotion_slots' => 4,
                    'relegation_slots' => 6,
                    'rewards' => ['gold' => 2500],
                ],
                [
                    'name' => 'T3',
                    'groups' => ['3-A', '3-B', '3-C'],
                    'promotion_slots' => 6,
                    'relegation_slots' => 0,
                    'rewards' => ['gold' => 1000],
                ],
            ],
        ];
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflector = new \ReflectionClass($entity);
        $property = $reflector->getProperty('id');
        $property->setValue($entity, $id);
    }
}
