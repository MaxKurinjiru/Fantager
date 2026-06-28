<?php

declare(strict_types=1);

namespace App\Tests\Service\Economy;

use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Repository\Hero\HeroRepository;
use App\Service\Economy\EconomyService;
use App\Service\Economy\TeamPayrollService;
use App\Service\Hero\HeroSalaryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TeamPayrollServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroRepository */
    private $heroRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroSalaryService */
    private $heroSalaryServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EconomyService */
    private $economyServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
    private TeamPayrollService $service;

    protected function setUp(): void
    {
        $this->heroRepositoryMock = $this->createMock(HeroRepository::class);
        $this->heroSalaryServiceMock = $this->createMock(HeroSalaryService::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->service = new TeamPayrollService(
            $this->heroRepositoryMock,
            $this->heroSalaryServiceMock,
            $this->economyServiceMock,
            $this->entityManagerMock,
        );
    }

    public function testCalculateWeeklyPayrollBreakdownSeparatesHeroesAndTrainers(): void
    {
        $team = new Team();
        $hero = $this->createHero(HeroRole::Combatant);
        $trainer = $this->createHero(HeroRole::Trainer);

        $this->heroRepositoryMock
            ->expects($this->once())
            ->method('findPayrollEligibleByTeam')
            ->with($team)
            ->willReturn([$hero, $trainer]);

        $this->heroSalaryServiceMock
            ->expects($this->exactly(2))
            ->method('calculateWeeklySalary')
            ->willReturnMap([
                [$hero, 100],
                [$trainer, 150],
            ]);

        $breakdown = $this->service->calculateWeeklyPayrollBreakdown($team);

        $this->assertSame(100, $breakdown['heroes_due']);
        $this->assertSame(150, $breakdown['trainers_due']);
        $this->assertSame(250, $breakdown['total']);
        $this->assertSame(1, $breakdown['hero_count']);
        $this->assertSame(1, $breakdown['trainer_count']);
    }

    public function testProcessPayrollTickDeductsGoldForBothPortions(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setGold(500);
        $team->setKingdom($kingdom);

        $hero = $this->createHero(HeroRole::Combatant);
        $trainer = $this->createHero(HeroRole::Trainer);

        $this->heroRepositoryMock
            ->method('findPayrollEligibleByTeam')
            ->with($team)
            ->willReturn([$hero, $trainer]);

        $this->heroSalaryServiceMock
            ->method('calculateWeeklySalary')
            ->willReturnMap([
                [$hero, 100],
                [$trainer, 80],
            ]);

        $this->economyServiceMock
            ->expects($this->exactly(2))
            ->method('deductGold')
            ->with(
                $team,
                $this->logicalOr(100, 80),
                $this->logicalOr(FinancialRecordType::HeroSalary, FinancialRecordType::TrainerSalary),
                FinancialRecordActor::System,
                $this->isArray(),
            );

        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->service->processPayrollTick($kingdom, $team);
    }

    public function testProcessPayrollTickAddsDebtWhenGoldInsufficient(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setGold(50);
        $team->setKingdom($kingdom);

        $hero = $this->createHero(HeroRole::Combatant);

        $this->heroRepositoryMock
            ->method('findPayrollEligibleByTeam')
            ->with($team)
            ->willReturn([$hero]);

        $this->heroSalaryServiceMock
            ->method('calculateWeeklySalary')
            ->with($hero)
            ->willReturn(200);

        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold')
            ->with($team, 50, FinancialRecordType::HeroSalary, FinancialRecordActor::System, $this->isArray());

        $this->service->processPayrollTick($kingdom, $team);

        $this->assertSame(150, $team->getUnpaidDebt());
    }

    public function testProcessPayrollTickRecordsFullyUnpaidLedgerEntry(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setGold(0);
        $team->setKingdom($kingdom);

        $trainer = $this->createHero(HeroRole::Trainer);

        $this->heroRepositoryMock
            ->method('findPayrollEligibleByTeam')
            ->with($team)
            ->willReturn([$trainer]);

        $this->heroSalaryServiceMock
            ->method('calculateWeeklySalary')
            ->with($trainer)
            ->willReturn(120);

        $this->economyServiceMock
            ->expects($this->never())
            ->method('deductGold');

        $this->economyServiceMock
            ->expects($this->once())
            ->method('recordLedgerEntry')
            ->with(
                $team,
                FinancialRecordType::TrainerSalary,
                FinancialRecordActor::System,
                $this->callback(static fn (array $context): bool => isset($context['fully_unpaid']) && true === $context['fully_unpaid']),
            );

        $this->service->processPayrollTick($kingdom, $team);

        $this->assertSame(120, $team->getUnpaidDebt());
    }

    public function testProcessPayrollTickForKingdomProcessesAllTeams(): void
    {
        $kingdom = new Kingdom();
        $team = new Team();
        $team->setGold(1000);
        $team->setKingdom($kingdom);

        $teamRepositoryMock = $this->createMock(EntityRepository::class);
        $teamRepositoryMock
            ->expects($this->once())
            ->method('findBy')
            ->with(['kingdom' => $kingdom])
            ->willReturn([$team]);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('getRepository')
            ->with(Team::class)
            ->willReturn($teamRepositoryMock);

        $this->heroRepositoryMock
            ->method('findPayrollEligibleByTeam')
            ->willReturn([]);

        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->service->processPayrollTick($kingdom);
    }

    private function createHero(HeroRole $role): Hero
    {
        $hero = new Hero();
        $hero->setRole($role);
        $hero->setStatus(HeroStatus::Available);
        $hero->setName('Test Hero');

        return $hero;
    }
}
