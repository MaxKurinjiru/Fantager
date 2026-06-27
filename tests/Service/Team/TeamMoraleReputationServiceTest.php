<?php

declare(strict_types=1);

namespace App\Tests\Service\Team;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\HeroRole;
use App\Repository\Formation\FormationRepository;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\Team\TeamRepository;
use App\Service\Team\TeamMoraleReputationService;
use App\ValueObject\Combat\MatchOutcome;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TeamMoraleReputationServiceTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&TeamRepository */
    private $teamRepository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroRepository */
    private $heroRepository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&FormationRepository */
    private $formationRepository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeadquartersRepository */
    private $hqRepository;
    /** @var \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface */
    private $em;

    private TeamMoraleReputationService $service;

    protected function setUp(): void
    {
        $this->teamRepository = $this->createMock(TeamRepository::class);
        $this->heroRepository = $this->createMock(HeroRepository::class);
        $this->formationRepository = $this->createMock(FormationRepository::class);
        $this->hqRepository = $this->createMock(HeadquartersRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new TeamMoraleReputationService(
            $this->teamRepository,
            $this->heroRepository,
            $this->formationRepository,
            $this->hqRepository,
            $this->em
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('id');
        $prop->setValue($entity, $id);
    }

    public function testApplyMatchResultNormalWin(): void
    {
        $homeTeam = new Team();
        $this->setEntityId($homeTeam, 1);
        $homeTeam->setReputation(100);
        $homeTeam->setMorale(50);

        $awayTeam = new Team();
        $this->setEntityId($awayTeam, 2);
        $awayTeam->setReputation(100);
        $awayTeam->setMorale(50);

        // Setup 6 active heroes for home (avg charisma 10)
        $homeActive = [];
        for ($i = 1; $i <= 6; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($homeTeam);
            $hero->setRole(HeroRole::Combatant);
            $hero->setMorale(50);
            $hero->setCha(10); // average CHA = 10 -> factor 0.0 -> gainMultiplier 1.0, lossMultiplier 1.0
            $homeActive[] = $hero;
        }

        // 4 reserves for home
        $homeReserves = [];
        for ($i = 7; $i <= 10; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($homeTeam);
            $hero->setRole(HeroRole::Combatant);
            $hero->setMorale(50);
            $hero->setCha(10);
            $homeReserves[] = $hero;
        }

        // Setup 6 active heroes for away
        $awayActive = [];
        for ($i = 11; $i <= 16; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($awayTeam);
            $hero->setRole(HeroRole::Combatant);
            $hero->setMorale(50);
            $hero->setCha(10);
            $awayActive[] = $hero;
        }

        $allHomeCombatants = array_merge($homeActive, $homeReserves);
        $allAwayCombatants = $awayActive;

        // Mock repositories
        $this->heroRepository->method('findCombatantsByTeam')->willReturnCallback(
            fn(Team $t) => $t === $homeTeam ? $allHomeCombatants : $allAwayCombatants
        );

        // Build formations
        $homeFormation = new Formation();
        $homeFormation->setTeam($homeTeam);
        foreach ($homeActive as $hero) {
            $slot = new FormationSlot();
            $slot->setHero($hero);
            $homeFormation->addSlot($slot);
        }

        $awayFormation = new Formation();
        $awayFormation->setTeam($awayTeam);
        foreach ($awayActive as $hero) {
            $slot = new FormationSlot();
            $slot->setHero($hero);
            $awayFormation->addSlot($slot);
        }

        $outcome = new MatchOutcome(3, 1); // Home wins 3-1, not a forfeit

        $this->em->expects($this->once())->method('flush');

        $this->service->applyMatchResult($homeTeam, $awayTeam, $outcome, $homeFormation, $awayFormation);

        // Assertions for Home Team (Winner)
        $this->assertSame(110, $homeTeam->getReputation()); // +10 Reputation
        $this->assertSame(58, $homeTeam->getMorale());      // +8 Team Morale

        // Home Active Heroes
        foreach ($homeActive as $hero) {
            $this->assertSame(60, $hero->getMorale()); // +10 Hero Morale
        }

        // Home Reserve Heroes
        foreach ($homeReserves as $hero) {
            $this->assertSame(54, $hero->getMorale()); // +4 Hero Morale

        }

        // Assertions for Away Team (Loser)
        $this->assertSame(95, $awayTeam->getReputation()); // -5 Reputation
        $this->assertSame(44, $awayTeam->getMorale());      // -6 Team Morale

        // Away Active Heroes
        foreach ($awayActive as $hero) {
            $this->assertSame(42, $hero->getMorale()); // -8 Hero Morale
        }
    }

    public function testApplyMatchResultCharismaInfluence(): void
    {
        $homeTeam = new Team();
        $this->setEntityId($homeTeam, 1);
        $homeTeam->setReputation(100);
        $homeTeam->setMorale(50);

        $awayTeam = new Team();
        $this->setEntityId($awayTeam, 2);
        $awayTeam->setReputation(100);
        $awayTeam->setMorale(50);

        // Setup 6 active heroes for home (avg charisma 20 -> factor = min(0.5, 10 * 0.02) = 0.20)
        // lossMultiplier = 0.80, gainMultiplier = 1.20
        $homeActive = [];
        for ($i = 1; $i <= 6; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($homeTeam);
            $hero->setRole(HeroRole::Combatant);
            $hero->setMorale(50);
            $hero->setCha(200);
            $homeActive[] = $hero;
        }

        // Setup 6 active heroes for away (avg charisma 5 -> factor = 0.0)
        $awayActive = [];
        for ($i = 11; $i <= 16; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($awayTeam);
            $hero->setRole(HeroRole::Combatant);
            $hero->setMorale(50);
            $hero->setCha(50);
            $awayActive[] = $hero;
        }

        $this->heroRepository->method('findCombatantsByTeam')->willReturnCallback(
            fn(Team $t) => $t === $homeTeam ? $homeActive : $awayActive
        );

        $homeFormation = new Formation();
        $homeFormation->setTeam($homeTeam);
        foreach ($homeActive as $hero) {
            $slot = new FormationSlot();
            $slot->setHero($hero);
            $homeFormation->addSlot($slot);
        }

        $awayFormation = new Formation();
        $awayFormation->setTeam($awayTeam);
        foreach ($awayActive as $hero) {
            $slot = new FormationSlot();
            $slot->setHero($hero);
            $awayFormation->addSlot($slot);
        }

        $outcome = new MatchOutcome(3, 1);

        $this->service->applyMatchResult($homeTeam, $awayTeam, $outcome, $homeFormation, $awayFormation);

        // Assertions for Home Team (Winner with 20 avg charisma)
        // Team morale gain: +8 * 1.20 = 9.6 -> round to 10
        $this->assertSame(60, $homeTeam->getMorale());

        // Hero morale gain: +10 * 1.20 = 12
        foreach ($homeActive as $hero) {
            $this->assertSame(62, $hero->getMorale());
        }

        // Let's run a loss scenario for the charisma team
        $homeTeam->setMorale(50);
        foreach ($homeActive as $hero) {
            $hero->setMorale(50);
        }

        $outcomeLoss = new MatchOutcome(1, 3); // Home loses
        $this->service->applyMatchResult($homeTeam, $awayTeam, $outcomeLoss, $homeFormation, $awayFormation);

        // Team morale loss: -6 * 0.80 = -4.8 -> round to -5 (new morale = 45)
        $this->assertSame(45, $homeTeam->getMorale());

        // Hero morale loss: -8 * 0.80 = -6.4 -> round to -6 (new morale = 44)
        foreach ($homeActive as $hero) {
            $this->assertSame(44, $hero->getMorale());
        }
    }

    public function testApplyMatchResultForfeit(): void
    {
        $homeTeam = new Team();
        $this->setEntityId($homeTeam, 1);
        $homeTeam->setReputation(100);
        $homeTeam->setMorale(50);

        $awayTeam = new Team();
        $this->setEntityId($awayTeam, 2);
        $awayTeam->setReputation(100);
        $awayTeam->setMorale(50);

        $homeActive = [];
        for ($i = 1; $i <= 6; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($homeTeam);
            $hero->setRole(HeroRole::Combatant);
            $hero->setMorale(50);
            $hero->setCha(10);
            $homeActive[] = $hero;
        }

        $awayActive = [];
        for ($i = 11; $i <= 16; $i++) {
            $hero = new Hero();
            $this->setEntityId($hero, $i);
            $hero->setTeam($awayTeam);
            $hero->setRole(HeroRole::Combatant);
            $hero->setMorale(50);
            $hero->setCha(10);
            $awayActive[] = $hero;
        }

        $this->heroRepository->method('findCombatantsByTeam')->willReturnCallback(
            fn(Team $t) => $t === $homeTeam ? $homeActive : $awayActive
        );

        $outcome = MatchOutcome::forfeit(3, 0); // Forfeit win for home

        $this->service->applyMatchResult($homeTeam, $awayTeam, $outcome, null, null);

        // Home Team (Forfeit Winner)
        $this->assertSame(110, $homeTeam->getReputation()); // +10
        $this->assertSame(55, $homeTeam->getMorale());      // +5
        foreach ($homeActive as $hero) {
            $this->assertSame(55, $hero->getMorale());      // +5
        }

        // Away Team (Forfeit Loser)
        $this->assertSame(90, $awayTeam->getReputation());  // -10
        $this->assertSame(35, $awayTeam->getMorale());      // -15
        foreach ($awayActive as $hero) {
            $this->assertSame(35, $hero->getMorale());      // -15
        }
    }

    public function testDailyEvolutionMoraleDecayAndRecoveryWithBarracks(): void
    {
        $kingdom = new Kingdom();

        $team1 = new Team();
        $this->setEntityId($team1, 1);
        $team1->setKingdom($kingdom);
        $team1->setMorale(80); // Needs decay (above 50)

        $team2 = new Team();
        $this->setEntityId($team2, 2);
        $team2->setKingdom($kingdom);
        $team2->setMorale(20); // Needs recovery (below 50)

        $this->teamRepository->method('findBy')->willReturn([$team1, $team2]);

        // Heroes for team 1
        $hero1 = new Hero();
        $hero1->setRole(HeroRole::Combatant);
        $hero1->setMorale(80); // Needs decay
        $this->heroRepository->method('findCombatantsByTeam')->willReturnCallback(
            fn(Team $t) => $t === $team1 ? [$hero1] : []
        );

        // HQ and Facilities setup
        // Team 1: Barracks Level 2
        $hq1 = new Headquarters();
        $facility1 = new Facility();
        $facility1->setType(FacilityType::Barracks);
        $facility1->setLevel(2);
        $hq1->addFacility($facility1);

        // Team 2: Barracks Level 4
        $hq2 = new Headquarters();
        $facility2 = new Facility();
        $facility2->setType(FacilityType::Barracks);
        $facility2->setLevel(4);
        $hq2->addFacility($facility2);

        $this->hqRepository->method('findOneBy')->willReturnCallback(
            fn(array $criteria) => $criteria['team']->getId() === 1 ? $hq1 : $hq2
        );

        $this->em->expects($this->once())->method('flush');

        $updated = $this->service->processDailyEvolutionTick($kingdom);

        $this->assertSame(2, $updated);

        // Team 1 Morale Decay:
        // Gap = 30
        // Decay rate = 0.05
        // decayMultiplier = 1.0 / (1.0 + 0.05 * 2) = 1.0 / 1.10 = 0.909
        // Delta = round(-30 * 0.05 * 0.909) = round(-1.36) = -1
        // Expected = 80 - 1 = 79
        $this->assertSame(79, $team1->getMorale());
        $this->assertSame(79, $hero1->getMorale());

        // Team 2 Morale Recovery:
        // Gap = -30
        // recoveryMultiplier = 1.0 + 0.10 * 4 = 1.40
        // Delta = round(-(-30) * 0.05 * 1.40) = round(30 * 0.05 * 1.40) = round(2.10) = 2
        // Expected = 20 + 2 = 22
        $this->assertSame(22, $team2->getMorale());
    }
}
