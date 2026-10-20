<?php

declare(strict_types=1);

namespace App\Tests\Service\Formation;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\FormationPosition;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;
use App\Enum\Race;
use App\Service\Formation\FormationLineupScorer;
use PHPUnit\Framework\TestCase;

class FormationLineupScorerTest extends TestCase
{
    public function testFrontPrefersStrengthAndConstitution(): void
    {
        $scorer = new FormationLineupScorer();
        $tank = $this->hero(1, str: 180, kon: 180, intel: 50, spd: 50, dex: 50);
        $caster = $this->hero(2, str: 50, kon: 50, intel: 180, spd: 180, dex: 180);

        $this->assertGreaterThan($scorer->scores($caster)['front'], $scorer->scores($tank)['front']);
        $this->assertGreaterThan($scorer->scores($tank)['back'], $scorer->scores($caster)['back']);
    }

    public function testFatigueAndFormPenalizeReadiness(): void
    {
        $scorer = new FormationLineupScorer();
        $fresh = $this->hero(1, str: 100, kon: 100);
        $tired = $this->hero(2, str: 100, kon: 100);
        $tired->setFatigue(60);
        $tired->setForm(30);

        $this->assertSame(20.0, $scorer->scores($fresh)['front']);
        $this->assertEqualsWithDelta(6.0, $scorer->scores($tired)['front'], 0.01);
    }

    public function testNegativeTraitAlmostBenchesBothRows(): void
    {
        $scorer = new FormationLineupScorer();
        $slacker = $this->hero(1, str: 100, kon: 100, intel: 100, spd: 100, dex: 100);
        $slacker->setTrait(HeroTrait::Slacker);

        $this->assertEqualsWithDelta(2.0, $scorer->scores($slacker)['front'], 0.01);
        $this->assertEqualsWithDelta(3.0, $scorer->scores($slacker)['back'], 0.01);
    }

    public function testSortForRowUsesScoreThenId(): void
    {
        $scorer = new FormationLineupScorer();
        $low = $this->hero(8, str: 50, kon: 50);
        $high = $this->hero(9, str: 180, kon: 180);
        $sorted = $scorer->sortForRow([$low, $high], true);

        $this->assertSame([9, 8], array_map(static fn (Hero $hero): int => (int) $hero->getId(), $sorted));
        $this->assertTrue($scorer->isFrontRow(FormationPosition::Front2));
        $this->assertFalse($scorer->isFrontRow(FormationPosition::Back1));
    }

    private function hero(
        int $id,
        int $str = 100,
        int $kon = 100,
        int $intel = 100,
        int $spd = 100,
        int $dex = 100,
    ): Hero {
        $hero = new Hero();
        $hero->setTeam(new Team());
        $hero->setName('Hero '.$id);
        $hero->setRace(Race::Human);
        $hero->setAge(20);
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw($str);
        $hero->setKonRaw($kon);
        $hero->setIntelRaw($intel);
        $hero->setSpdRaw($spd);
        $hero->setDexRaw($dex);

        (new \ReflectionProperty(Hero::class, 'id'))->setValue($hero, $id);

        return $hero;
    }
}
