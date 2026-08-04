<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\Combat\Battle;
use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Enum\BattleResult;
use App\Enum\HeroStatus;
use App\Enum\LeagueFixtureStatus;
use App\Enum\MatchType;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueStandingRepository;
use App\Service\Combat\CombatEngine;
use App\Service\Combat\CombatMatchRequestBuilder;
use App\Service\Combat\CombatSeedGenerator;
use App\Service\Config\RaceConfig;
use App\Service\Graveyard\GraveyardService;
use App\Service\Hero\HeroChronicleService;
use App\Service\Hero\HeroMasteryService;
use App\Service\League\LeagueFixtureCompletionService;
use App\Service\League\LeagueMatchResolutionService;
use App\Service\League\LeagueStandingService;
use App\Service\Team\FanClubService;
use App\Service\Team\TeamMoraleReputationService;
use App\Service\Team\TeamRosterService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\ValueObject\Combat\CombatantSnapshot;
use App\ValueObject\Combat\CombatMatchRequest;
use App\ValueObject\Combat\CombatSide;
use App\ValueObject\Combat\DerivedCombatStats;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class CombatDeathAndDurabilityTest extends TestCase
{
    public function testCombatEngineTracksKilledHeroesAndHitCounts(): void
    {
        $engine = new CombatEngine();

        // Create a match where side A is extremely strong (attack = 9999) to guarantee immediate KO
        $sideA = new CombatSide(1, 101, \App\Enum\FormationApproach::Balanced, [
            new CombatantSnapshot(11, 'Attacker', \App\Enum\FormationPosition::Front1, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 9999)),
            new CombatantSnapshot(12, 'Dummy A2', \App\Enum\FormationPosition::Front2, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(13, 'Dummy A3', \App\Enum\FormationPosition::Front3, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(14, 'Dummy A4', \App\Enum\FormationPosition::Back1, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(15, 'Dummy A5', \App\Enum\FormationPosition::Back2, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(16, 'Dummy A6', \App\Enum\FormationPosition::Back3, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
        ]);

        $sideB = new CombatSide(2, 102, \App\Enum\FormationApproach::Balanced, [
            new CombatantSnapshot(21, 'Victim', \App\Enum\FormationPosition::Front1, Race::Human, 1, 1, 0, 50, $this->buildStats(1, 1)), // 1 HP
            new CombatantSnapshot(22, 'Dummy B2', \App\Enum\FormationPosition::Front2, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(23, 'Dummy B3', \App\Enum\FormationPosition::Front3, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(24, 'Dummy B4', \App\Enum\FormationPosition::Back1, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(25, 'Dummy B5', \App\Enum\FormationPosition::Back2, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
            new CombatantSnapshot(26, 'Dummy B6', \App\Enum\FormationPosition::Back3, Race::Human, 1, 100, 0, 50, $this->buildStats(100, 1)),
        ]);

        $request = new CombatMatchRequest($sideA, $sideB, MatchType::League, 42);
        $result = $engine->simulate($request);
        $log = $result->getCombatLog();

        $this->assertArrayHasKey('result', $log);
        $this->assertArrayHasKey('killed_hero_ids', $log['result']);
        $this->assertArrayHasKey('item_hit_counts', $log['result']);

        // Victim (hero 21) should be in killed_hero_ids
        $this->assertContains(21, $log['result']['killed_hero_ids']);

        // Item hit counts should have registered hits on hero 21
        $this->assertArrayHasKey('21', $log['result']['item_hit_counts']);
        $this->assertGreaterThanOrEqual(1, $log['result']['item_hit_counts']['21']);
    }

    public function testLeagueMatchResolutionAppliesDeathsAndDurability(): void
    {
        $fixtureRepository = $this->createMock(LeagueFixtureRepository::class);
        $standingRepository = $this->createMock(LeagueStandingRepository::class);
        $teamRosterService = $this->createMock(TeamRosterService::class);
        $fixtureCompletionService = $this->createMock(LeagueFixtureCompletionService::class);
        $fanClubService = $this->createMock(FanClubService::class);
        $teamMoraleReputationService = $this->createMock(TeamMoraleReputationService::class);
        $teamChronicleService = $this->createMock(TeamChronicleService::class);
        $heroMasteryService = $this->createMock(HeroMasteryService::class);
        $heroChronicleService = $this->createMock(HeroChronicleService::class);
        $formationRepository = $this->createMock(\App\Repository\Formation\FormationRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $requestBuilder = $this->createMock(CombatMatchRequestBuilder::class);
        $seedGenerator = new CombatSeedGenerator();
        $combatEngine = $this->createMock(CombatEngine::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $graveyardService = $this->createMock(GraveyardService::class);
        $raceConfig = $this->createMock(RaceConfig::class);
        $raceConfig->method('isAtOrAboveMortalityThreshold')->willReturn(true);
        $raceConfig->method('getMortalityThreshold')->willReturn(80);

        $service = new LeagueMatchResolutionService(
            $fixtureRepository,
            $standingRepository,
            $teamRosterService,
            new LeagueStandingService(),
            $fixtureCompletionService,
            $fanClubService,
            $teamMoraleReputationService,
            $teamChronicleService,
            $heroMasteryService,
            $heroChronicleService,
            $formationRepository,
            $em,
            $requestBuilder,
            $seedGenerator,
            $combatEngine,
            $messageBus,
            $graveyardService,
            $raceConfig,
            $this->createMock(\App\Service\Notification\NotificationHelper::class)
        );


        // Mock setup
        $fixture = $this->createMock(LeagueFixture::class);
        $group = $this->createMock(LeagueGroup::class);
        $fixture->method('getGroup')->willReturn($group);

        $homeTeam = $this->createMock(Team::class);
        $awayTeam = $this->createMock(Team::class);
        $fixture->method('getHomeTeam')->willReturn($homeTeam);
        $fixture->method('getAwayTeam')->willReturn($awayTeam);

        $standing = $this->createMock(LeagueStanding::class);
        $standingRepository->method('findOneBy')->willReturn($standing);

        $fixtureRepo = $this->createMock(EntityRepository::class);
        $fixtureRepo->method('findOneBy')->willReturn($fixture);


        // Create a battle with mock outcomes
        $battle = $this->createMock(Battle::class);
        $battle->method('getResult')->willReturn(BattleResult::WinA);
        $battle->method('getScoreA')->willReturn(1);
        $battle->method('getScoreB')->willReturn(0);
        $battle->method('getCurrentRound')->willReturn(25); // 25 rounds
        $battle->method('getCombatLog')->willReturn([
            'result' => [
                'score_a' => 1,
                'score_b' => 0,
                'killed_hero_ids' => [21],
                'item_hit_counts' => [
                    '21' => 9,  // victim hit 9 times
                    '11' => 3,  // hero 11 hit 3 times
                ]
            ]
        ]);

        // Setup formations and slots
        $homeFormation = $this->createMock(Formation::class);
        $awayFormation = $this->createMock(Formation::class);
        $battle->method('getFormationA')->willReturn($homeFormation);
        $battle->method('getFormationB')->willReturn($awayFormation);

        $slotA = $this->createMock(FormationSlot::class);
        $heroA = $this->createMock(Hero::class);
        $heroA->method('getId')->willReturn(11);
        $slotA->method('getHero')->willReturn($heroA);
        $homeFormation->method('getSlots')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$slotA]));

        $slotB = $this->createMock(FormationSlot::class);
        $heroB = $this->createMock(Hero::class);
        $heroB->method('getId')->willReturn(21);
        $heroB->method('getTeam')->willReturn($awayTeam);
        $heroB->method('getRace')->willReturn(Race::Human);
        $heroB->method('getAge')->willReturn(150);
        $heroB->method('getAgeRaw')->willReturn(1500);
        $heroB->method('setAgeRaw')->willReturnSelf();
        $slotB->method('getHero')->willReturn($heroB);
        $awayFormation->method('getSlots')->willReturn(new \Doctrine\Common\Collections\ArrayCollection([$slotB]));

        // Mock entity manager find for the killed hero
        $em->method('find')->willReturnCallback(function ($class, $id) use ($heroB) {
            if ($class === Hero::class && $id === 21) {
                return $heroB;
            }
            return null;
        });

        // Mock items repository to return some items
        $itemRepo = $this->createMock(EntityRepository::class);
        $item1 = new Item();
        $item1->setDurability(100);
        $item2 = new Item();
        $item2->setDurability(95);

        $itemRepo->method('findBy')->willReturnCallback(function (array $criteria) use ($heroA, $heroB, $item1, $item2) {
            if (($criteria['equippedHero'] ?? null) === $heroA) {
                return [$item1];
            }
            if (($criteria['equippedHero'] ?? null) === $heroB) {
                return [$item2];
            }
            return [];
        });

        $em->method('getRepository')->willReturnCallback(function ($class) use ($itemRepo, $fixtureRepo) {
            if ($class === Item::class) {
                return $itemRepo;
            }
            if ($class === LeagueFixture::class) {
                return $fixtureRepo;
            }
            return $this->createMock(EntityRepository::class);
        });

        // Setup expectations
        $graveyardService->expects($this->once())
            ->method('prepareCombatDeath')
            ->with($heroB);

        $graveyardService->expects($this->once())
            ->method('recordMemorial')
            ->with($heroB, $awayTeam, MemorialCause::CombatDeath);

        $heroB->expects($this->once())
            ->method('setStatus')
            ->with(HeroStatus::Dead);

        // Execute
        $service->completeBattle($battle);

        // Check durability calculations:
        // Rounds = 25 → floor(25/10) = 2
        // Hero 11: hits = 3 → floor(3/3) = 1. Total loss = 2 + 1 = 3. New durability: 100 - 3 = 97.
        $this->assertEquals(97, $item1->getDurability());

        // Hero 21: hits = 9 → floor(9/3) = 3. Total loss = 2 + 3 = 5. New durability: 95 - 5 = 90.
        $this->assertEquals(90, $item2->getDurability());
    }

    private function buildStats(int $hp, int $atk): DerivedCombatStats
    {
        return new DerivedCombatStats(
            maxHp: $hp,
            currentHp: $hp,
            physicalAttack: $atk,
            spellPower: 10,
            armorValue: 10,
            physicalDamageReduction: 0.1,
            magicResistance: 10,
            magicDamageReduction: 0.1,
            baseInitiative: 10,
            accuracyPercent: 100.0,
            dodgePercent: 0.0,
            critPercent: 0.0
        );
    }
}
