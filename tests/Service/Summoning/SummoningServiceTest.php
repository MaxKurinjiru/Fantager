<?php

declare(strict_types=1);

namespace App\Tests\Service\Summoning;

use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Service\Config\RaceConfig;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Economy\RoyalTreasuryService;
use App\Service\Headquarters\HeadquartersService;
use App\Service\Hero\HeroGenerator;
use App\Service\Summoning\SummoningService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SummoningServiceTest extends TestCase
{
    private HeroGenerator $heroGeneratorMock;
    private EconomyService $economyServiceMock;
    private FinancialCrisisService $financialCrisisServiceMock;
    private RoyalTreasuryService $royalTreasuryServiceMock;
    private HeadquartersService $hqServiceMock;
    private EntityManagerInterface $entityManagerMock;
    private RaceConfig $raceConfigMock;
    private TeamChronicleService $teamChronicleServiceMock;
    private SummoningService $service;

    protected function setUp(): void
    {
        $this->heroGeneratorMock = $this->createMock(HeroGenerator::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->financialCrisisServiceMock = $this->createMock(FinancialCrisisService::class);
        $this->royalTreasuryServiceMock = $this->createMock(RoyalTreasuryService::class);
        $this->hqServiceMock = $this->createMock(HeadquartersService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->raceConfigMock = $this->createMock(RaceConfig::class);
        $this->teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);

        $this->service = new SummoningService(
            $this->heroGeneratorMock,
            $this->economyServiceMock,
            $this->financialCrisisServiceMock,
            $this->royalTreasuryServiceMock,
            $this->hqServiceMock,
            $this->entityManagerMock,
            $this->raceConfigMock,
            $this->teamChronicleServiceMock
        );
    }

    public function testGetStatusWithZeroSummonsOnNormalSpeedIsAvailable(): void
    {
        $team = $this->createTeam(speed: '1.00', gold: 1000, summonsThisCycle: 0);

        $this->hqServiceMock
            ->method('getRosterLimit')
            ->willReturn(10);

        $heroRepoMock = $this->createMock(EntityRepository::class);
        $heroRepoMock->method('count')->willReturn(5);

        $hqRepoMock = $this->createMock(EntityRepository::class);
        $hqRepoMock->method('findOneBy')->willReturn(null);

        $this->entityManagerMock
            ->method('getRepository')
            ->willReturnMap([
                [Hero::class, $heroRepoMock],
                [\App\Entity\Headquarters\Headquarters::class, $hqRepoMock],
            ]);

        $status = $this->service->getStatus($team);

        $this->assertTrue($status['available']);
        $this->assertSame(0, $status['summons_used']);
        $this->assertSame(1, $status['summons_max']);
        $this->assertSame(500, $status['gold_cost']);
    }

    public function testGetStatusWithOneSummonOnNormalSpeedIsReached(): void
    {
        $team = $this->createTeam(speed: '1.00', gold: 1000, summonsThisCycle: 1);

        $this->hqServiceMock
            ->method('getRosterLimit')
            ->willReturn(10);

        $heroRepoMock = $this->createMock(EntityRepository::class);
        $heroRepoMock->method('count')->willReturn(5);

        $hqRepoMock = $this->createMock(EntityRepository::class);
        $hqRepoMock->method('findOneBy')->willReturn(null);

        $this->entityManagerMock
            ->method('getRepository')
            ->willReturnMap([
                [Hero::class, $heroRepoMock],
                [\App\Entity\Headquarters\Headquarters::class, $hqRepoMock],
            ]);

        $status = $this->service->getStatus($team);

        $this->assertFalse($status['available']);
        $this->assertSame('error.summoning_limit_reached', $status['reason']);
        $this->assertSame(1, $status['summons_used']);
        $this->assertSame(1, $status['summons_max']);
        // Base cost: 500, inflation: 1 + 0.5 * 1 = 1.5, cost = 750
        $this->assertSame(750, $status['gold_cost']);
    }

    public function testGetStatusWithFastSpeedScalesLimit(): void
    {
        // speed = 2.0 -> limit = round(1 * 2) = 2
        $team = $this->createTeam(speed: '2.00', gold: 1000, summonsThisCycle: 1);

        $this->hqServiceMock
            ->method('getRosterLimit')
            ->willReturn(10);

        $heroRepoMock = $this->createMock(EntityRepository::class);
        $heroRepoMock->method('count')->willReturn(5);

        $hqRepoMock = $this->createMock(EntityRepository::class);
        $hqRepoMock->method('findOneBy')->willReturn(null);

        $this->entityManagerMock
            ->method('getRepository')
            ->willReturnMap([
                [Hero::class, $heroRepoMock],
                [\App\Entity\Headquarters\Headquarters::class, $hqRepoMock],
            ]);

        $status = $this->service->getStatus($team);

        $this->assertTrue($status['available']);
        $this->assertSame(1, $status['summons_used']);
        $this->assertSame(2, $status['summons_max']);
    }

    private function createTeam(string $speed, int $gold, int $summonsThisCycle): Team
    {
        $kingdom = new Kingdom();
        $kingdom->setGameSpeed($speed);

        $team = new Team();
        $team->setKingdom($kingdom);
        $team->setGold($gold);
        $team->setSummonsThisCycle($summonsThisCycle);

        return $team;
    }
}
