<?php

declare(strict_types=1);

namespace App\Tests\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Headquarters\Facility;
use App\Entity\Item\Item;
use App\Entity\Hero\HeroTrainingHistory;
use App\Enum\FacilityType;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;
use App\Enum\Race;
use App\Enum\School;
use App\Enum\TrainingType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Hero\HeroMasteryService;
use App\Repository\Hero\HeroRepository;
use App\Service\Config\RaceConfig;
use App\Exception\UserFacingException;
use App\Service\Training\TrainingService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TrainingServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroRepository */
    private $heroRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeadquartersRepository */
    private $hqRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&RaceConfig */
    private $raceConfigMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamChronicleService */
    private $teamChronicleServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroMasteryService */
    private $heroMasteryMock;
    private TrainingService $trainingService;

    protected function setUp(): void
    {
        $this->heroRepositoryMock = $this->createMock(HeroRepository::class);
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->raceConfigMock = $this->createMock(RaceConfig::class);
        $this->raceConfigMock->method('getAge')->willReturn([
            'min' => 16,
            'max_junior' => 20,
            'prime_limit' => 50,
            'mortality_threshold' => 80,
        ]);
        $this->teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $financialCrisisServiceMock = $this->createMock(\App\Service\Economy\FinancialCrisisService::class);
        $financialCrisisServiceMock->method('areHqBonusesActive')->willReturn(true);

        $this->trainingService = new TrainingService(
            $this->heroRepositoryMock,
            $this->hqRepositoryMock,
            $this->raceConfigMock,
            $this->teamChronicleServiceMock,
            $this->createMock(\App\Service\Hero\HeroChronicleService::class),
            $this->entityManagerMock,
            $this->createMock(\App\Service\Notification\NotificationHelper::class),
            $financialCrisisServiceMock,
            $this->heroMasteryMock = $this->createMock(HeroMasteryService::class),
            $this->createMock(\App\Service\Hero\HeroProgressionService::class),
        );
    }


    public function testGetNextTrainingTimeBeforeThursday(): void
    {
        $now = new \DateTimeImmutable('2026-06-03 15:30:00');
        $next = $this->trainingService->getNextTrainingTime($now);

        $this->assertSame('2026-06-04 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testGetNextTrainingTimeOnThursdayBeforeTen(): void
    {
        $now = new \DateTimeImmutable('2026-06-04 09:00:00');
        $next = $this->trainingService->getNextTrainingTime($now);

        $this->assertSame('2026-06-04 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testGetNextTrainingTimeOnThursdayAfterTen(): void
    {
        $now = new \DateTimeImmutable('2026-06-04 10:30:00');
        $next = $this->trainingService->getNextTrainingTime($now);

        $this->assertSame('2026-06-11 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testIsTrainingLockedDuringLockPeriod(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $now = new \DateTimeImmutable('2026-06-04 09:00:00', new \DateTimeZone('UTC'));
        $this->assertTrue($this->trainingService->isTrainingLockedForTeam($team, $now));
    }

    public function testIsTrainingLockedOutsideLockPeriod(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $now = new \DateTimeImmutable('2026-06-03 15:00:00', new \DateTimeZone('UTC'));
        $this->assertFalse($this->trainingService->isTrainingLockedForTeam($team, $now));
    }

    public function testConfigureTrainerSuccessful(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);

        $now = new \DateTimeImmutable('2026-06-01 10:00:00', new \DateTimeZone('UTC'));

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

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);

        $now = new \DateTimeImmutable('2026-06-04 09:00:00', new \DateTimeZone('UTC'));

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.trainer_config_locked');

        $this->trainingService->configureTrainer($trainer, TrainingType::Attribute, 'str', $team, $now);
    }

    public function testAssignHeroSuccessful(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setStatus(HeroStatus::Available);

        $now = new \DateTimeImmutable('2026-06-01 10:00:00');

        $this->trainingService->assignHero($trainer, $hero, $team, $now);

        $this->assertSame($trainer, $hero->getTrainer());
        $this->assertSame(HeroStatus::Available, $hero->getStatus());
        $this->assertTrue($trainer->getTrainees()->contains($hero));
    }

    public function testAssignHeroSlotLimitExceeded(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);

        $hq = new Headquarters();
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setLevel(1);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        for ($i = 0; $i < 3; $i++) {
            $h = new Hero();
            $h->setRole(HeroRole::Combatant);
            $h->setTeam($team);
            $trainer->addTrainee($h);
        }

        $newHero = new Hero();
        $newHero->setRole(HeroRole::Combatant);
        $newHero->setTeam($team);
        $newHero->setStatus(HeroStatus::Available);

        $now = new \DateTimeImmutable('2026-06-01 10:00:00');

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.trainer_no_slots');

        $this->trainingService->assignHero($trainer, $newHero, $team, $now);
    }

    public function testProcessTrainingTickAttributeWithTrainer(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(142);
        $hero->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(155);
        $trainer->addTrainee($hero);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setMetadata(['training_efficiency_pct' => 3.0]);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        $this->raceConfigMock
            ->method('getTrainingSpeedModifier')
            ->willReturn(1.0);

        $this->mockTrainerQueryResult([$trainer]);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) {
                $this->assertInstanceOf(HeroTrainingHistory::class, $entity);
            });

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(144, $hero->getStrRaw());
        $this->assertSame(30, $hero->getFatigue());
    }

    public function testProcessTrainingTickRecoveryRest(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setForm(70);
        $hero->setFatigue(50);
        $hero->setStatus(HeroStatus::Available);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Form);
        $trainer->addTrainee($hero);

        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(90, $hero->getForm());
        $this->assertSame(30, $hero->getFatigue());
    }

    /**
     * @param list<Hero> $trainers
     */
    private function mockTrainerQueryResult(array $trainers): void
    {
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn($trainers);

        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('join')->willReturnSelf();
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $this->heroRepositoryMock
            ->method('createQueryBuilder')
            ->willReturn($qbMock);
    }

    public function testPromoteToTrainerUnequipsItems(): void
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn(1);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setStatus(HeroStatus::Available);
        $hero->setTrait(HeroTrait::QuickLearner);

        $item = new Item();
        $item->setOwnerTeam($team);
        $item->setEquippedHero($hero);
        $item->setEquippedSlot(\App\Enum\ItemSlotType::MainHand);

        $this->heroRepositoryMock
            ->expects($this->once())
            ->method('countTrainersByTeam')
            ->with($team)
            ->willReturn(0);

        $hq = new Headquarters();
        $this->hqRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['team' => $team])
            ->willReturn($hq);

        $itemRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $itemRepo->expects($this->once())->method('findBy')
            ->with(['equippedHero' => $hero])
            ->willReturn([$item]);

        $formationSlotRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $formationSlotRepo->method('findBy')->willReturn([]);

        $this->entityManagerMock
            ->method('getRepository')
            ->willReturnCallback(function ($className) use ($itemRepo, $formationSlotRepo) {
                if ($className === Item::class) {
                    return $itemRepo;
                }
                if ($className === \App\Entity\Formation\FormationSlot::class) {
                    return $formationSlotRepo;
                }
                throw new \InvalidArgumentException("Unexpected class: $className");
            });

        $this->entityManagerMock->expects($this->once())->method('flush');

        $now = new \DateTimeImmutable('2026-06-01 10:00:00');
        $this->trainingService->promoteToTrainer($hero, $team, $now);

        $this->assertSame(HeroRole::Trainer, $hero->getRole());
        $this->assertNull($hero->getTrait());
        $this->assertNull($item->getEquippedHero());
        $this->assertNull($item->getEquippedSlot());
    }

    public function testProcessTrainingTickArenaAdaptationExactBonus(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);
        
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Elf);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(140);
        $hero->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(160);
        $trainer->addTrainee($hero);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $hq->setRaceOptimization('elf');
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setMetadata(['training_efficiency_pct' => 0.0]);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        $this->raceConfigMock
            ->expects($this->atLeastOnce())
            ->method('getTrainingSpeedModifier')
            ->with(Race::Elf)
            ->willReturn(1.0);

        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(143, $hero->getStrRaw());
    }

    public function testProcessTrainingTickArenaAdaptationCompatibleBonus(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);
        
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Elf);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(140);
        $hero->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(160);
        $trainer->addTrainee($hero);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $hq->setRaceOptimization('human');
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setMetadata(['training_efficiency_pct' => 0.0]);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        $this->raceConfigMock
            ->expects($this->atLeastOnce())
            ->method('getTrainingSpeedModifier')
            ->with(Race::Elf)
            ->willReturn(1.0);

        $this->raceConfigMock
            ->expects($this->atLeastOnce())
            ->method('getRelationship')
            ->with(Race::Elf, Race::Human)
            ->willReturn(90);

        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(143, $hero->getStrRaw());
    }

    public function testProcessTrainingTickGroupCompatibleBonus(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);
        
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Elf);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(140);
        $hero->setFatigue(10);

        $otherTrainee = new Hero();
        $otherTrainee->setRole(HeroRole::Combatant);
        $otherTrainee->setTeam($team);
        $otherTrainee->setRace(Race::Human);
        $otherTrainee->setStatus(HeroStatus::Available);
        $otherTrainee->setStrRaw(140);
        $otherTrainee->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(160);
        
        $trainer->addTrainee($hero);
        $trainer->addTrainee($otherTrainee);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setMetadata(['training_efficiency_pct' => 0.0]);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        $this->raceConfigMock
            ->method('getTrainingSpeedModifier')
            ->willReturn(1.0);

        $this->raceConfigMock
            ->method('getRelationship')
            ->willReturnCallback(function (Race $a, Race $b) {
                if (($a === Race::Elf && $b === Race::Human) || ($a === Race::Human && $b === Race::Elf)) {
                    return 90;
                }
                return 100;
            });

        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(143, $hero->getStrRaw());
    }

    public function testProcessTrainingTickGroupHostilePenalty(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);
        
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Elf);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(110);
        $hero->setFatigue(10);

        $otherTrainee = new Hero();
        $otherTrainee->setRole(HeroRole::Combatant);
        $otherTrainee->setTeam($team);
        $otherTrainee->setRace(Race::Orc);
        $otherTrainee->setStatus(HeroStatus::Available);
        $otherTrainee->setStrRaw(110);
        $otherTrainee->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(160);
        
        $trainer->addTrainee($hero);
        $trainer->addTrainee($otherTrainee);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setMetadata(['training_efficiency_pct' => 0.0]);
        $hq->addFacility($facility);

        $this->hqRepositoryMock
            ->method('findOneBy')
            ->willReturn($hq);

        $this->raceConfigMock
            ->method('getTrainingSpeedModifier')
            ->willReturn(1.0);

        $this->raceConfigMock
            ->method('getRelationship')
            ->willReturnCallback(function (Race $a, Race $b) {
                if (($a === Race::Elf && $b === Race::Orc) || ($a === Race::Orc && $b === Race::Elf)) {
                    return 0;
                }
                return 100;
            });

        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(113, $hero->getStrRaw());
    }

    public function testProcessTrainingTickNoFatigueWhenAttributeCapped(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(155);
        $hero->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(155);
        $trainer->addTrainee($hero);

        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(155, $hero->getStrRaw());
        $this->assertSame(10, $hero->getFatigue());
    }

    public function testProcessTrainingTickNoFatigueWhenMagicCapped(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setStatus(HeroStatus::Available);
        $hero->setMagicCapacity(5);
        $hero->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Magic);
        $trainer->addTrainee($hero);

        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable());

        $this->assertSame(5, $hero->getMagicCapacity());
        $this->assertSame(10, $hero->getFatigue());
    }

    public function testAttributeGainCapRisesWithTrainerStat(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(50);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(200);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(200);
        $trainer->addTrainee($hero);

        $this->raceConfigMock->method('getTrainingSpeedModifier')->willReturn(1.0);
        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable('2026-06-04 10:00:00'));

        // Uncapped formula is 11 raw. The old flat cap of 9 would have clipped it.
        $this->assertSame(61, $hero->getStrRaw());
    }

    public function testGroupModifierUsesEachHeroRelationship(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $elf = $this->makeTrainee($team, Race::Elf, 60);
        $human = $this->makeTrainee($team, Race::Human, 60);
        $orc = $this->makeTrainee($team, Race::Orc, 60);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(200);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(200);
        $trainer->addTrainee($elf);
        $trainer->addTrainee($human);
        $trainer->addTrainee($orc);

        $this->raceConfigMock->method('getTrainingSpeedModifier')->willReturn(1.0);
        $this->raceConfigMock->method('getRelationship')->willReturnCallback(
            static function (Race $a, Race $b): int {
                $pair = [$a->value, $b->value];
                sort($pair);

                return match ($pair) {
                    ['elf', 'human'] => 100,
                    ['elf', 'orc'], ['human', 'orc'] => 0,
                    default => 100,
                };
            }
        );
        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick(new \DateTimeImmutable('2026-06-04 10:00:00'));

        // Elf and Human average 50 (neutral). Orc averages 0 (−5%).
        $this->assertSame(70, $elf->getStrRaw());
        $this->assertSame(70, $human->getStrRaw());
        $this->assertSame(69, $orc->getStrRaw());
    }

    public function testMagicSchoolXpScalesWithTrainerStat(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Available);
        $hero->setMagicCapacity(2);
        $hero->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(200);
        $trainer->setWilRaw(50);
        $trainer->setAgeRaw(200);
        $trainer->setTrainingType(TrainingType::Magic);
        $trainer->setTargetAttribute(School::Fire->value);
        $trainer->addTrainee($hero);

        $now = new \DateTimeImmutable('2026-06-04 10:00:00');
        $this->heroMasteryMock->expects($this->once())
            ->method('recordSchoolExercise')
            ->with($hero, School::Fire, 10, $now);
        $this->mockTrainerQueryResult([$trainer]);

        $this->trainingService->processTrainingTick($now);

        $this->assertSame(3, $hero->getMagicCapacity());
        $this->assertSame(30, $hero->getFatigue());
    }

    public function testMagicAtCapacityCostsLessFatigue(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Available);
        $hero->setMagicCapacity(5);
        $hero->setFatigue(10);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(100);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(200);
        $trainer->setTrainingType(TrainingType::Magic);
        $trainer->setTargetAttribute(School::Water->value);
        $trainer->addTrainee($hero);

        $this->mockTrainerQueryResult([$trainer]);
        $this->trainingService->processTrainingTick(new \DateTimeImmutable('2026-06-04 10:00:00'));

        $this->assertSame(5, $hero->getMagicCapacity());
        $this->assertSame(20, $hero->getFatigue());
    }

    public function testFormTrainingScalesWithConstitution(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setStatus(HeroStatus::Available);
        $hero->setForm(40);
        $hero->setFatigue(50);

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setKonRaw(200);
        $trainer->setIntelRaw(100);
        $trainer->setWilRaw(100);
        $trainer->setAgeRaw(200);
        $trainer->setTrainingType(TrainingType::Form);
        $trainer->addTrainee($hero);

        $this->mockTrainerQueryResult([$trainer]);
        $this->trainingService->processTrainingTick(new \DateTimeImmutable('2026-06-04 10:00:00'));

        $this->assertSame(80, $hero->getForm());
        $this->assertSame(10, $hero->getFatigue());
    }

    public function testTrainerAgesByRaceSpanOnlyWhenTeaching(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $team->method('getKingdom')->willReturn($kingdom);

        $hero = $this->makeTrainee($team, Race::Human, 100);
        $teaching = new Hero();
        $teaching->setRole(HeroRole::Trainer);
        $teaching->setTeam($team);
        $teaching->setRace(Race::Human);
        $teaching->setKonRaw(100);
        $teaching->setIntelRaw(100);
        $teaching->setWilRaw(100);
        $teaching->setAgeRaw(160);
        $teaching->setTrainingType(TrainingType::Form);
        $teaching->addTrainee($hero);

        $idle = new Hero();
        $idle->setRole(HeroRole::Trainer);
        $idle->setTeam($team);
        $idle->setRace(Race::Human);
        $idle->setAgeRaw(160);
        $idle->setTrainingType(TrainingType::Attribute);
        $idle->setTargetAttribute('str');

        $this->raceConfigMock->method('getTrainingSpeedModifier')->willReturn(1.0);
        $this->assertSame(18, $this->trainingService->trainerWeeksUntilMortality($teaching));

        $this->mockTrainerQueryResult([$teaching, $idle]);
        $this->trainingService->processTrainingTick(new \DateTimeImmutable('2026-06-04 10:00:00'));

        // Human span 64 years / 22 weeks = 2.909 years = 29 raw tenths.
        $this->assertSame(189, $teaching->getAgeRaw());
        $this->assertSame(160, $idle->getAgeRaw());
    }

    private function makeTrainee(Team $team, Race $race, int $strRaw): Hero
    {
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setTeam($team);
        $hero->setRace($race);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw($strRaw);

        return $hero;
    }
}
