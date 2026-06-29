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
            ->willReturnCallback(function (Team $t) use ($team, $hero, $trainer) {
                $this->assertSame($team, $t);
                return [$hero, $trainer];
            });

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
            ->willReturnCallback(function (Team $t) use ($team, $hero, $trainer) {
                $this->assertSame($team, $t);
                return [$hero, $trainer];
            });

        $this->heroSalaryServiceMock
            ->method('calculateWeeklySalary')
            ->willReturnMap([
                [$hero, 100],
                [$trainer, 80],
            ]);

        $deductions = [];
        $this->economyServiceMock
            ->expects($this->exactly(2))
            ->method('deductGold')
            ->willReturnCallback(function ($t, $amount, $type, $actor, $context) use (&$deductions, $team) {
                $this->assertSame($team, $t);
                $deductions[] = [$amount, $type, $actor, $context];
                return true;
            });

        $this->entityManagerMock->expects($this->once())->method('flush');

        $this->service->processPayrollTick($kingdom, $team);

        $this->assertCount(2, $deductions);
        $matchHero = false;
        $matchTrainer = false;
        foreach ($deductions as $d) {
            $this->assertSame(FinancialRecordActor::System, $d[2]);
            $this->assertIsArray($d[3]);
            if ($d[0] === 100 && $d[1] === FinancialRecordType::HeroSalary) {
                $matchHero = true;
            } elseif ($d[0] === 80 && $d[1] === FinancialRecordType::TrainerSalary) {
                $matchTrainer = true;
            }
        }
        $this->assertTrue($matchHero);
        $this->assertTrue($matchTrainer);
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
            ->willReturnCallback(function (Team $t) use ($team, $hero) {
                $this->assertSame($team, $t);
                return [$hero];
            });

        $this->heroSalaryServiceMock
            ->method('calculateWeeklySalary')
            ->willReturnCallback(function (Hero $h) use ($hero) {
                $this->assertSame($hero, $h);
                return 200;
            });

        $calledDeduct = null;
        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold')
            ->willReturnCallback(function ($t, $amount, $type, $actor, $context) use (&$calledDeduct, $team) {
                $this->assertSame($team, $t);
                $this->assertSame(50, $amount);
                $this->assertSame(FinancialRecordType::HeroSalary, $type);
                $this->assertSame(FinancialRecordActor::System, $actor);
                $this->assertIsArray($context);
                $calledDeduct = true;
                return true;
            });

        $this->service->processPayrollTick($kingdom, $team);

        $this->assertTrue($calledDeduct);
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
            ->willReturnCallback(function (Team $t) use ($team, $trainer) {
                $this->assertSame($team, $t);
                return [$trainer];
            });

        $this->heroSalaryServiceMock
            ->method('calculateWeeklySalary')
            ->willReturnCallback(function (Hero $h) use ($trainer) {
                $this->assertSame($trainer, $h);
                return 120;
            });

        $this->economyServiceMock
            ->expects($this->never())
            ->method('deductGold');

        $calledRecord = null;
        $this->economyServiceMock
            ->expects($this->once())
            ->method('recordLedgerEntry')
            ->willReturnCallback(function ($t, $type, $actor, $context) use (&$calledRecord, $team) {
                $this->assertSame($team, $t);
                $this->assertSame(FinancialRecordType::TrainerSalary, $type);
                $this->assertSame(FinancialRecordActor::System, $actor);
                $this->assertTrue(isset($context['fully_unpaid']) && true === $context['fully_unpaid']);
                $calledRecord = true;
            });

        $this->service->processPayrollTick($kingdom, $team);

        $this->assertTrue($calledRecord);
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
            ->willReturnCallback(function (array $criteria) use ($kingdom, $team) {
                $this->assertSame(['kingdom' => $kingdom], $criteria);
                return [$team];
            });

        $this->entityManagerMock
            ->expects($this->once())
            ->method('getRepository')
            ->willReturnCallback(function ($className) use ($teamRepositoryMock) {
                $this->assertSame(Team::class, $className);
                return $teamRepositoryMock;
            });

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
