<?php

declare(strict_types=1);

namespace App\Tests\Service\Economy;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueStanding;
use App\Entity\League\LeagueTier;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\LeagueSeasonStatus;
use App\Enum\RoyalTreasuryContributionSource;
use App\Repository\League\LeagueStandingRepository;
use App\Repository\Team\TeamRepository;
use App\Service\Economy\EconomyService;
use App\Service\Economy\RoyalTreasuryService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class RoyalTreasuryServiceTest extends TestCase
{
    private TeamRepository $teamRepositoryMock;
    private LeagueStandingRepository $leagueStandingRepositoryMock;
    private EconomyService $economyServiceMock;
    private RoyalTreasuryService $service;

    protected function setUp(): void
    {
        $this->teamRepositoryMock = $this->createMock(TeamRepository::class);
        $this->leagueStandingRepositoryMock = $this->createMock(LeagueStandingRepository::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);

        $this->service = new RoyalTreasuryService(
            $this->teamRepositoryMock,
            $this->leagueStandingRepositoryMock,
            $this->economyServiceMock,
        );
    }

    public function testCollectFeeIncreasesKingdomBalance(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setRoyalTreasuryGold(100);

        $this->service->collectFee($kingdom, 50, RoyalTreasuryContributionSource::SummonFee);

        $this->assertSame(150, $kingdom->getRoyalTreasuryGold());
    }

    public function testCollectFeeIgnoresNonPositiveAmount(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setRoyalTreasuryGold(100);

        $this->service->collectFee($kingdom, 0, RoyalTreasuryContributionSource::MarketplaceTax);

        $this->assertSame(100, $kingdom->getRoyalTreasuryGold());
    }

    public function testWeeklyDistributionCapsAtFiftyPercent(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setRoyalTreasuryGold(1000);

        $team = $this->createTeamWithId(1, $kingdom, 100);

        $this->teamRepositoryMock
            ->method('findBy')
            ->willReturn([$team]);

        $this->leagueStandingRepositoryMock
            ->method('findIndexedByTeamForActiveSeason')
            ->willReturn([]);

        $this->economyServiceMock
            ->expects($this->once())
            ->method('addGold')
            ->with(
                $team,
                500,
                FinancialRecordType::KingdomReward,
                FinancialRecordActor::System,
            );

        $result = $this->service->processWeeklyDistribution($kingdom);

        $this->assertSame(500, $result['distributed']);
        $this->assertSame(1, $result['teams_paid']);
        $this->assertSame(500, $kingdom->getRoyalTreasuryGold());
    }

    public function testWeeklyDistributionWeightsHigherTierAndReputation(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setRoyalTreasuryGold(1000);

        $strongTeam = $this->createTeamWithId(1, $kingdom, 500);
        $weakTeam = $this->createTeamWithId(2, $kingdom, 0);

        $strongStanding = $this->createStanding($strongTeam, 'T1', 50);
        $weakStanding = $this->createStanding($weakTeam, 'T3', 0);

        $this->teamRepositoryMock
            ->method('findBy')
            ->willReturn([$strongTeam, $weakTeam]);

        $this->leagueStandingRepositoryMock
            ->method('findIndexedByTeamForActiveSeason')
            ->willReturn([
                1 => $strongStanding,
                2 => $weakStanding,
            ]);

        $amounts = [];
        $this->economyServiceMock
            ->expects($this->exactly(2))
            ->method('addGold')
            ->willReturnCallback(function (Team $team, int $amount) use (&$amounts): void {
                $amounts[$team->getId()] = $amount;
            });

        $result = $this->service->processWeeklyDistribution($kingdom);

        $this->assertSame(500, $result['distributed']);
        $this->assertSame(2, $result['teams_paid']);
        $this->assertGreaterThan($amounts[2], $amounts[1]);
    }

    public function testWeeklyDistributionDoesNothingWhenTreasuryEmpty(): void
    {
        $kingdom = new Kingdom();

        $this->teamRepositoryMock->expects($this->never())->method('findBy');
        $this->economyServiceMock->expects($this->never())->method('addGold');

        $result = $this->service->processWeeklyDistribution($kingdom);

        $this->assertSame(0, $result['distributed']);
        $this->assertSame(0, $result['teams_paid']);
    }

    private function createTeamWithId(int $id, Kingdom $kingdom, int $reputation): Team
    {
        $team = new Team();
        $this->setEntityId($team, $id);
        $team->setKingdom($kingdom);
        $team->setName('Team '.$id);
        $team->setReputation($reputation);

        return $team;
    }

    private function createStanding(Team $team, string $tierName, int $points): LeagueStanding
    {
        $season = new LeagueSeason();
        $season->setKingdom($team->getKingdom());
        $season->setSeasonNumber(1);
        $season->setStartDate(new \DateTimeImmutable('2026-01-05'));
        $season->setEndDate(new \DateTimeImmutable('2026-03-29'));
        $season->setStatus(LeagueSeasonStatus::Active);

        $tier = new LeagueTier();
        $tier->setSeason($season);
        $tier->setTierName($tierName);
        $tier->setPromotionSlots(0);
        $tier->setRelegationSlots(0);
        $tier->setRewards([]);

        $group = new LeagueGroup();
        $group->setTier($tier);
        $group->setGroupName('G1');

        $standing = new LeagueStanding();
        $standing->setTeam($team);
        $standing->setGroup($group);
        $standing->setPoints($points);

        return $standing;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
