<?php

declare(strict_types=1);

namespace App\Tests\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Headquarters\Facility;
use App\Entity\Training\Trainer;
use App\Entity\Training\TrainingQueue;
use App\Enum\FacilityType;
use App\Enum\HeroStatus;
use App\Enum\Race;
use App\Enum\TrainingStatus;
use App\Enum\TrainingType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Training\TrainerRepository;
use App\Service\Config\RaceConfig;
use App\Service\Training\TrainingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TrainingServiceTest extends TestCase
{
    private $trainerRepositoryMock;
    private $hqRepositoryMock;
    private $raceConfigMock;
    private $entityManagerMock;
    private TrainingService $trainingService;

    protected function setUp(): void
    {
        $this->trainerRepositoryMock = $this->createMock(TrainerRepository::class);
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->raceConfigMock = $this->createMock(RaceConfig::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->trainingService = new TrainingService(
            $this->trainerRepositoryMock,
            $this->hqRepositoryMock,
            $this->raceConfigMock,
            $this->entityManagerMock
        );
    }

    public function testGetNextTrainingTimeBeforeFriday(): void
    {
        // Thursday 2026-06-04 15:30:00
        $now = new \DateTimeImmutable('2026-06-04 15:30:00');
        $next = $this->trainingService->getNextTrainingTime($now);

        // Next Friday 10:00 is Friday 2026-06-05 10:00:00
        $this->assertSame('2026-06-05 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testGetNextTrainingTimeOnFridayBeforeTen(): void
    {
        // Friday 2026-06-05 09:00:00
        $now = new \DateTimeImmutable('2026-06-05 09:00:00');
        $next = $this->trainingService->getNextTrainingTime($now);

        // Should target today at 10:00:00
        $this->assertSame('2026-06-05 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testGetNextTrainingTimeOnFridayAfterTen(): void
    {
        // Friday 2026-06-05 10:30:00
        $now = new \DateTimeImmutable('2026-06-05 10:30:00');
        $next = $this->trainingService->getNextTrainingTime($now);

        // Should target next week's Friday at 10:00:00 -> 2026-06-12 10:00:00
        $this->assertSame('2026-06-12 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testIsTrainingLockedDuringLockPeriod(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        // Wednesday 15:00 UTC - lock starts at Wednesday 12:00 UTC
        $now = new \DateTimeImmutable('2026-06-03 15:00:00');
        $this->assertTrue($this->trainingService->isTrainingLockedForTeam($team, $now));
    }

    public function testIsTrainingLockedOutsideLockPeriod(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        // Monday 10:00 UTC - unlocked
        $now = new \DateTimeImmutable('2026-06-01 10:00:00');
        $this->assertFalse($this->trainingService->isTrainingLockedForTeam($team, $now));
    }

    public function testConfigureTrainerSuccessful(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $trainer = new Trainer();
        $trainer->setTeam($team);

        // Monday 10:00 UTC (unlocked)
        $now = new \DateTimeImmutable('2026-06-01 10:00:00');

        $this->trainingService->configureTrainer($trainer, TrainingType::Attribute, 'str', $team, $now);

        $this->assertSame(TrainingType::Attribute, $trainer->getTrainingType());
        $this->assertSame('str', $trainer->getTargetAttribute());
    }

    public function testConfigureTrainerLockedThrowsException(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $trainer = new Trainer();
        $trainer->setTeam($team);

        // Thursday 10:00 UTC (locked)
        $now = new \DateTimeImmutable('2026-06-04 10:00:00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Training configuration is currently locked.');

        $this->trainingService->configureTrainer($trainer, TrainingType::Attribute, 'str', $team, $now);
    }

    public function testAssignHeroSuccessful(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $trainer = new Trainer();
        $trainer->setTeam($team);

        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setStatus(HeroStatus::Available);

        // Monday 10:00 UTC (unlocked)
        $now = new \DateTimeImmutable('2026-06-01 10:00:00');

        $this->trainingService->assignHero($trainer, $hero, $team, $now);

        $this->assertSame($trainer, $hero->getTrainer());
        $this->assertSame(HeroStatus::Available, $hero->getStatus());
        $this->assertTrue($trainer->getHeroes()->contains($hero));
    }

    public function testAssignHeroSlotLimitExceeded(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $trainer = new Trainer();
        $trainer->setTeam($team);

        // Setup Training facility at level 1 -> gives 3 slots
        $hq = new Headquarters();
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setLevel(1);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        // Fill slots with 3 heroes
        for ($i = 0; $i < 3; $i++) {
            $h = new Hero();
            $h->setTeam($team);
            $trainer->addHero($h);
        }

        $newHero = new Hero();
        $newHero->setTeam($team);
        $newHero->setStatus(HeroStatus::Available);

        $now = new \DateTimeImmutable('2026-06-01 10:00:00');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Trainer does not have any available slots.');

        $this->trainingService->assignHero($trainer, $newHero, $team, $now);
    }

    public function testProcessTrainingTickAttributeWithTrainer(): void
    {
        $team = $this->createMock(Team::class);
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(142); // external 14
        $hero->setFatigue(10);

        $trainer = new Trainer();
        $trainer->setTeam($team);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(155); // external 15
        $trainer->addHero($hero);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setPassiveBonuses(['training_efficiency_pct' => 5.0]); // Level 1 (5%)
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        $this->raceConfigMock
            ->method('getTrainingSpeedModifier')
            ->willReturn(1.0);

        $this->trainerRepositoryMock
            ->method('createQueryBuilder')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('join')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('where')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('getQuery')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('getResult')
            ->willReturn([$trainer]);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(TrainingQueue::class));

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        // Formula check:
        // Base = 1.0
        // Trainer = (15 - 10) * 0.05 = 0.25
        // Diff = (15 - 14) * 0.05 = 0.05
        // Raw = 1.30
        // Difficulty (H=14) = 1.0 + (14/5)^1.5 = 5.68
        // ScaledBase = 1.30 / 5.68 = 0.2288
        // Facility = +5% -> 0.240
        // Raw gain = round(0.240 * 10) = 2 raw points
        // Final raw stat = 142 + 2 = 144
        $this->assertSame(144, $hero->getStrRaw());
        
        // Fatigue should increase by 20 -> 10 + 20 = 30
        $this->assertSame(30, $hero->getFatigue());
    }

    public function testProcessTrainingTickRecoveryRest(): void
    {
        $team = $this->createMock(Team::class);
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setForm(70);
        $hero->setFatigue(50);
        $hero->setStatus(HeroStatus::Available);

        $trainer = new Trainer();
        $trainer->setTeam($team);
        $trainer->setTrainingType(TrainingType::Form);
        $trainer->addHero($hero);

        $this->trainerRepositoryMock
            ->method('createQueryBuilder')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('join')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('where')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('getQuery')
            ->willReturnSelf();
        $this->trainerRepositoryMock
            ->method('getResult')
            ->willReturn([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        // Form should increase by 20 -> 70 + 20 = 90
        $this->assertSame(90, $hero->getForm());

        // Fatigue should decrease by 20 -> 50 - 20 = 30
        $this->assertSame(30, $hero->getFatigue());
    }
}
