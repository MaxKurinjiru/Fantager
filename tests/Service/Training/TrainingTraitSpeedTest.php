<?php

declare(strict_types=1);

namespace App\Tests\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;
use App\Enum\Race;
use App\Enum\TrainingType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Config\RaceConfig;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Service\Training\TrainingService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TrainingTraitSpeedTest extends TestCase
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

    /**
     * Vytvoří trainer→hero dvojici se specifikovaným traitem a spustí training tick.
     * Vrátí raw gain nahraný na hrdinu (str stat).
     *
     * Všichni hrdinové mají stejné podmínky (race modifier = 1.0, žádná facility, speed = 1.00):
     *   - Trainer STR = 15 (effective = raw 150), Hero STR = 5 (effective = raw 50)
     *
     * Každé volání vytvoří čerstvé mocky, aby se předešlo kolizím při vícenásobném volání v jednom testu.
     */
    private function runTrainingTickAndGetStrGain(?HeroTrait $trait): int
    {
        $heroRepositoryMock = $this->createMock(HeroRepository::class);
        $hqRepositoryMock = $this->createMock(HeadquartersRepository::class);
        $raceConfigMock = $this->createMock(RaceConfig::class);
        $teamChronicleServiceMock = $this->createMock(TeamChronicleService::class);
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);

        $trainingService = new TrainingService(
            $heroRepositoryMock,
            $hqRepositoryMock,
            $raceConfigMock,
            $teamChronicleServiceMock,
            $entityManagerMock
        );

        $kingdom = $this->createMock(\App\Entity\Kingdom\Kingdom::class);
        $kingdom->method('getGameSpeed')->willReturn('1.00');
        $kingdom->method('getTimezone')->willReturn('Europe/Prague');

        $team = $this->createMock(Team::class);
        $team->method('getKingdom')->willReturn($kingdom);

        // Trainer: STR = 15 (raw = 150)
        $trainer = new Hero();
        $trainer->setTeam($team);
        $trainer->setRace(Race::Human);
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setStatus(HeroStatus::Available);
        $trainer->setTrainingType(TrainingType::Attribute);
        $trainer->setTargetAttribute('str');
        $trainer->setStrRaw(150);
        $trainer->setDexRaw(100); $trainer->setKonRaw(100); $trainer->setSpdRaw(100);
        $trainer->setIntelRaw(100); $trainer->setWilRaw(100); $trainer->setChaRaw(100); $trainer->setLckRaw(100);
        $trainer->setAgeRaw(250);

        // Trainee: STR = 5 (raw = 50), s volitelným traitem
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setRace(Race::Human);
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(50);
        $hero->setDexRaw(100); $hero->setKonRaw(100); $hero->setSpdRaw(100);
        $hero->setIntelRaw(100); $hero->setWilRaw(100); $hero->setChaRaw(100); $hero->setLckRaw(100);
        $hero->setTrait($trait);

        $trainer->addTrainee($hero);

        $raceConfigMock->method('getTrainingSpeedModifier')->willReturn(1.0);
        $hqRepositoryMock->method('findOneBy')->willReturn(null);

        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);
        $queryMock->method('getResult')->willReturn([$trainer]);

        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('join')->willReturnSelf();
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $heroRepositoryMock->method('createQueryBuilder')->willReturn($qbMock);

        $strBefore = $hero->getStrRaw();
        $trainingService->processTrainingTick(new \DateTimeImmutable('2026-06-05 10:00:00'));
        $strAfter = $hero->getStrRaw();

        return $strAfter - $strBefore;
    }

    /**
     * Hrdina bez traitu dostane základní gain.
     */
    public function testNoTraitGetNeutralGain(): void
    {
        $gain = $this->runTrainingTickAndGetStrGain(null);
        $this->assertGreaterThan(0, $gain, 'Hero without trait should gain at least 1 raw point.');
    }

    /**
     * QuickLearner dostane alespoň stejný gain jako hrdina bez traitu,
     * a při nízkém základním gainu (pod capem) dostane o ~20 % více.
     *
     * Poznámka: TrainingService aplikuje cap `min(9 * speed, gainRaw)`.
     * Pokud je base gain již na capu (9), QuickLearner gain je také 9 (cap).
     * V tomto testu ověřujeme hlavně, že QuickLearner NIKDY nezíská méně než no-trait hrdina.
     */
    public function testQuickLearnerGainsMoreOrEqualThanNoTrait(): void
    {
        $gainNoTrait = $this->runTrainingTickAndGetStrGain(null);
        $gainQuickLearner = $this->runTrainingTickAndGetStrGain(HeroTrait::QuickLearner);

        // QuickLearner nesmí nikdy dostat méně
        $this->assertGreaterThanOrEqual(
            $gainNoTrait,
            $gainQuickLearner,
            'QuickLearner must gain at least as much as a hero without a trait.'
        );
    }

    /**
     * Slacker dostane o 15 % méně raw gain než hrdina bez traitu.
     * Tolerance ±1 raw bod.
     */
    public function testSlackerGainsLessThanNoTrait(): void
    {
        $gainNoTrait = $this->runTrainingTickAndGetStrGain(null);
        $gainSlacker = $this->runTrainingTickAndGetStrGain(HeroTrait::Slacker);

        // Slacker gain must be <= no-trait gain
        $this->assertLessThanOrEqual(
            $gainNoTrait,
            $gainSlacker,
            'Slacker must gain at most as much as a hero without a trait.'
        );

        // Expected: 0.85×, tolerance ±1 raw
        $expected = (int) round($gainNoTrait * 0.85);
        $this->assertEqualsWithDelta(
            $expected,
            $gainSlacker,
            1,
            "Slacker gain should be ~0.85× the base gain (±1 raw for rounding)."
        );
    }

    /**
     * Perfectionist dostane o 10 % méně raw gain (ale konzistentní výstup v combat).
     */
    public function testPerfectionistGainsLessInTraining(): void
    {
        $gainNoTrait = $this->runTrainingTickAndGetStrGain(null);
        $gainPerfectionist = $this->runTrainingTickAndGetStrGain(HeroTrait::Perfectionist);

        $this->assertLessThanOrEqual(
            $gainNoTrait,
            $gainPerfectionist,
            'Perfectionist must gain at most as much as a hero without a trait in training.'
        );
    }

    /**
     * Traity bez efektu na trénink (AudienceFavorite, Volatile, Clutch, atd.)
     * musí dát stejný gain jako bez traitu (tolerance ±1).
     */
    public function testNonTrainingTraitsHaveNoEffect(): void
    {
        $gainNoTrait = $this->runTrainingTickAndGetStrGain(null);

        $neutralTrainingTraits = [
            HeroTrait::AudienceFavorite,
            HeroTrait::BattleHardened,
            HeroTrait::Volatile,
            HeroTrait::Clutch,
            HeroTrait::Fragile,
            HeroTrait::GlassJaw,
            HeroTrait::Berserker,
            HeroTrait::Glasscannon,
            HeroTrait::Reckless,
            HeroTrait::Loner,
            HeroTrait::Overconfident,
        ];

        foreach ($neutralTrainingTraits as $trait) {
            $gain = $this->runTrainingTickAndGetStrGain($trait);
            $this->assertEqualsWithDelta(
                $gainNoTrait,
                $gain,
                1,
                "Trait {$trait->value} should not affect training speed (±1 raw tolerance)."
            );
        }
    }
}
