<?php

declare(strict_types=1);

namespace App\Tests\Service\Hero;

use App\Config\HeroProgressionConfig;
use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\Race;
use App\Service\Hero\HeroProgressionService;
use App\Service\Hero\HeroService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class HeroProgressionServiceTest extends TestCase
{
    private HeroProgressionConfig $config;
    private HeroProgressionService $service;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroService */
    private $heroServiceMock;

    protected function setUp(): void
    {
        $this->config = new HeroProgressionConfig(dirname(__DIR__, 3));
        $this->heroServiceMock = $this->createMock(HeroService::class);
        $this->service = new HeroProgressionService($this->config, $this->heroServiceMock);
    }

    public function testCumulativeThresholdsMatchLockedCurve(): void
    {
        $this->assertSame(400, $this->config->getCoefficient());
        $this->assertSame(0, $this->config->cumulativeXpForLevel(1));
        $this->assertSame(800, $this->config->cumulativeXpForLevel(2));
        $this->assertSame(2000, $this->config->cumulativeXpForLevel(3));
        $this->assertSame(3600, $this->config->cumulativeXpForLevel(4));
        $this->assertSame(83600, $this->config->cumulativeXpForLevel(20));
    }

    public function testLevelForXpUsesCapAndDoesNotSpendXp(): void
    {
        $this->assertSame(20, $this->config->getLevelCap());
        $this->assertSame(1, $this->config->levelForXp(799));
        $this->assertSame(2, $this->config->levelForXp(800));
        $this->assertSame(2, $this->config->levelForXp(1999));
        $this->assertSame(3, $this->config->levelForXp(2000));
        $this->assertSame(20, $this->config->levelForXp(90000));
    }

    public function testApplyMatchXpMultipliesLibraryAndKingdomModifierThenLevels(): void
    {
        $hero = $this->createHero();
        $this->heroServiceMock->expects($this->once())->method('levelUp')->with($hero, 1);

        $awarded = $this->service->applyMatchXp($hero, $hero->getTeam(), 400, 1.0);

        $this->assertSame(800, $awarded);
        $this->assertSame(800, $hero->getXp());
        $this->assertSame(1, $hero->getLevel());
    }

    public function testSyncFromXpDoesNotLowerStoredLevel(): void
    {
        $hero = $this->createHero();
        $hero->setLevel(5);
        $hero->setXp(0);
        $this->heroServiceMock->expects($this->never())->method('levelUp');

        $this->service->syncFromXp($hero, $hero->getTeam());

        $this->assertSame(5, $hero->getLevel());
    }

    public function testRecalcStoredLevelCanLowerWithoutChronicle(): void
    {
        $hero = $this->createHero();
        $hero->setLevel(5);
        $hero->setXp(0);
        $this->heroServiceMock->expects($this->never())->method('levelUp');

        $changed = $this->service->recalcStoredLevel($hero, $hero->getTeam());

        $this->assertTrue($changed);
        $this->assertSame(1, $hero->getLevel());
        $this->assertSame(0, $hero->getXp());
    }

    public function testXpBarUsesStoredLevelNotXp(): void
    {
        $hero = $this->createHero();
        $hero->setLevel(1);
        $hero->setXp(90000);

        $bar = $this->service->xpBar($hero);

        $this->assertSame(0, $bar['xp_for_level']);
        $this->assertSame(800, $bar['xp_for_next_level']);
        $this->assertFalse($bar['at_cap']);
        $this->assertSame(100.0, $bar['progress_percent']);
    }

    public function testMatchBaseXpScalesWithHpAndKeepsTheKoBonus(): void
    {
        // 1 XP per 10 HP. An early ~90 HP bar is 9 XP; a late ~780 HP bar is 78 XP.
        $early = $this->service->matchBaseXp(true, 0, ['damage_hp' => 90]);
        $late = $this->service->matchBaseXp(true, 0, ['damage_hp' => 780]);

        $this->assertSame(20 + 15 + 9, $early);
        $this->assertSame(20 + 15 + 78, $late);

        // status_hp is half of one listed run, per cast. 48 HP → 5 XP.
        // Non-tick status is 2 XP times target level. KO is 10 XP times victim level.
        $caster = $this->service->matchBaseXp(false, 1, [
            'damage_hp' => 100,
            'heal_hp' => 50,
            'status_hp' => 48,
            'status_levels' => 10,
            'ko_levels' => 8,
            'utility_casts' => 2,
        ]);
        $this->assertSame(20 + 80 + 10 + 5 + 5 + 20 + 4, $caster);
        $this->assertSame(20, $this->service->matchBaseXp(false, 0, []));
        $this->assertSame(30, $this->service->matchBaseXp(false, 1, []));
    }

    private function createHero(): Hero
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test');
        $kingdom->setLanguage('en');
        $kingdom->setTimezone('UTC');
        $kingdom->setXpModifier('2.00');

        $team = new Team();
        $team->setName('T');
        $team->setKingdom($kingdom);

        $hero = new Hero();
        $hero->setName('Hero');
        $hero->setRace(Race::Human);
        $hero->setTeam($team);
        $hero->setLevel(1);
        $hero->setXp(0);

        return $hero;
    }
}
