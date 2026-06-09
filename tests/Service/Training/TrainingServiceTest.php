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
use App\Repository\Training\TrainerRepository;
use App\Repository\Training\TrainingQueueRepository;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Config\RaceConfig;
use App\Service\Economy\EconomyService;
use App\Service\Training\TrainingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TrainingServiceTest extends TestCase
{
    private $queueRepositoryMock;
    private $trainerRepositoryMock;
    private $hqRepositoryMock;
    private $raceConfigMock;
    private $economyServiceMock;
    private $entityManagerMock;
    private TrainingService $trainingService;

    protected function setUp(): void
    {
        $this->queueRepositoryMock = $this->createMock(TrainingQueueRepository::class);
        $this->trainerRepositoryMock = $this->createMock(TrainerRepository::class);
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->raceConfigMock = $this->createMock(RaceConfig::class);
        $this->economyServiceMock = $this->createMock(EconomyService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->trainingService = new TrainingService(
            $this->queueRepositoryMock,
            $this->trainerRepositoryMock,
            $this->hqRepositoryMock,
            $this->raceConfigMock,
            $this->economyServiceMock,
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

    public function testQueueValidationHeroStatEqualsTrainerStat(): void
    {
        $hero = new Hero();
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(150); // raw stat = 150 (external 15)

        $trainer = new Trainer();
        $trainer->setStrRaw(150); // raw stat = 150 (external 15)

        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $this->trainerRepositoryMock
            ->method('findOneBy')
            ->willReturn($trainer);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Hero stat is already equal to or higher than trainer stat.');

        $this->trainingService->queue($hero, TrainingType::Attribute, 'str', 1, $team);
    }

    public function testQueueSuccessfulWithTrainer(): void
    {
        $hero = new Hero();
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(140); // raw stat = 140 (external 14)

        $trainer = new Trainer();
        $trainer->setStrRaw(150); // raw stat = 150 (external 15)

        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('Europe/Prague');
        $team->method('getKingdom')->willReturn($kingdom);

        $this->trainerRepositoryMock
            ->method('findOneBy')
            ->willReturn($trainer);

        $this->economyServiceMock
            ->expects($this->once())
            ->method('deductGold');

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(TrainingQueue::class));

        $job = $this->trainingService->queue($hero, TrainingType::Attribute, 'str', 1, $team);

        $this->assertSame(HeroStatus::Training, $hero->getStatus());
        $this->assertSame(TrainingStatus::Pending, $job->getStatus());
        $this->assertSame($trainer, $job->getTrainer());
        // Verify scheduled time ends with Friday 08:00:00 UTC (since Europe/Prague is UTC+2 in summer)
        $this->assertSame('08:00:00', $job->getExecuteAt()->format('H:i:s'));
    }

    public function testProcessTrainingTickAttributeWithTrainer(): void
    {
        $hero = new Hero();
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Training);
        $hero->setStrRaw(142); // external 14

        $trainer = new Trainer();
        $trainer->setStrRaw(155); // external 15

        $team = $this->createMock(Team::class);
        $hero->setTeam($team);

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

        $job = new TrainingQueue();
        $job->setHero($hero);
        $job->setTrainingType(TrainingType::Attribute);
        $job->setTargetAttribute('str');
        $job->setTrainer($trainer);
        $job->setStatus(TrainingStatus::Pending);

        // Directly return job for findPendingDue mock
        $this->queueRepositoryMock
            ->method('findPendingDue')
            ->willReturn([$job]);

        // Directly return 0 count for countPendingForHero mock
        $this->queueRepositoryMock
            ->method('countPendingForHero')
            ->willReturn(0);

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
        $this->assertSame(2, $job->getStatGain());
        $this->assertSame(TrainingStatus::Completed, $job->getStatus());
        $this->assertSame(HeroStatus::Available, $hero->getStatus());
    }

    public function testProcessTrainingTickMagicCapped(): void
    {
        $hero = new Hero();
        $hero->setMagicCapacity(4);

        $job = new TrainingQueue();
        $job->setHero($hero);
        $job->setTrainingType(TrainingType::Magic);
        $job->setStatus(TrainingStatus::Pending);

        // Directly return job for findPendingDue mock
        $this->queueRepositoryMock
            ->method('findPendingDue')
            ->willReturn([$job]);

        // Directly return 0 count for countPendingForHero mock
        $this->queueRepositoryMock
            ->method('countPendingForHero')
            ->willReturn(0);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(5, $hero->getMagicCapacity());
        $this->assertSame(1, $job->getStatGain());
    }
}
