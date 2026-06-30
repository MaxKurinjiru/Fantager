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
use App\Enum\Race;
use App\Enum\TrainingType;
use App\Repository\Headquarters\HeadquartersRepository;
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
    private TrainingService $trainingService;

    protected function setUp(): void
    {
        $this->heroRepositoryMock = $this->createMock(HeroRepository::class);
        $this->hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $this->raceConfigMock = $this->createMock(RaceConfig::class);
        $this->teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $this->trainingService = new TrainingService(
            $this->heroRepositoryMock,
            $this->hqRepositoryMock,
            $this->raceConfigMock,
            $this->teamChronicleServiceMock,
            $this->entityManagerMock
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

        $now = new \DateTimeImmutable('2026-06-03 15:00:00');
        $this->assertTrue($this->trainingService->isTrainingLockedForTeam($team, $now));
    }

    public function testIsTrainingLockedOutsideLockPeriod(): void
    {
        $team = $this->createMock(Team::class);
        $kingdom = $this->createMock(Kingdom::class);
        $kingdom->method('getTimezone')->willReturn('UTC');
        $team->method('getKingdom')->willReturn($kingdom);

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

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);

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

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setTeam($team);

        $now = new \DateTimeImmutable('2026-06-04 10:00:00');

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
        $kingdom->method('getGameSpeed')->willReturn('1.00');
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
        $trainer->setAgeRaw(250);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(155);
        $trainer->addTrainee($hero);

        $hq = new Headquarters();
        $hq->setTeam($team);
        $facility = new Facility();
        $facility->setType(FacilityType::Training);
        $facility->setPassiveBonuses(['training_efficiency_pct' => 5.0]);
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
        $kingdom->method('getGameSpeed')->willReturn('1.00');
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
        $this->assertNull($item->getEquippedHero());
        $this->assertNull($item->getEquippedSlot());
    }
}
