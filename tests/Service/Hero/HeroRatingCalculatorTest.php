<?php

declare(strict_types=1);

namespace App\Tests\Service\Hero;

use App\Config\HeroRatingConfig;
use App\Entity\Hero\Hero;
use App\Entity\Hero\SchoolMastery;
use App\Entity\Hero\WeaponMastery;
use App\Entity\Item\Item;
use App\Enum\HeroRole;
use App\Enum\HeroTrait;
use App\Enum\ItemSubType;
use App\Enum\Race;
use App\Enum\School;
use App\Repository\Item\ItemRepository;
use App\Service\Combat\CombatStatCalculator;
use App\Service\Config\RaceConfig;
use App\Service\Hero\HeroMasteryService;
use App\Service\Hero\HeroRatingCalculator;
use App\ValueObject\Hero\HeroRating;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class HeroRatingCalculatorTest extends TestCase
{
    private string $projectDir;
    private HeroRatingConfig $heroRatingConfig;
    private RaceConfig $raceConfig;
    /** @var \PHPUnit\Framework\MockObject\MockObject&ItemRepository */
    private $itemRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&HeroMasteryService */
    private $heroMasteryServiceMock;
    private HeroRatingCalculator $calculator;

    protected function setUp(): void
    {
        $this->projectDir = dirname(__DIR__, 3);
        $this->heroRatingConfig = new HeroRatingConfig($this->projectDir);
        $this->raceConfig = new RaceConfig($this->projectDir);
        $this->itemRepositoryMock = $this->createMock(ItemRepository::class);
        $this->heroMasteryServiceMock = $this->createMock(HeroMasteryService::class);
        $this->heroMasteryServiceMock->method('getEquippedSubTypes')->willReturn([]);
        $this->heroMasteryServiceMock->method('getEquippedSpellSchools')->willReturn([]);

        $combatCalculator = new CombatStatCalculator(
            $this->itemRepositoryMock,
            $this->heroMasteryServiceMock,
        );

        $this->calculator = new HeroRatingCalculator(
            $combatCalculator,
            $this->heroRatingConfig,
            $this->raceConfig,
        );
    }

    public function testRatingsStayWithinConfiguredBounds(): void
    {
        $hero = $this->createHero(Race::Human);

        $rating = $this->calculator->calculate($hero);

        $this->assertGreaterThanOrEqual(0, $rating->getBaseOvr());
        $this->assertLessThanOrEqual(100, $rating->getBaseOvr());
        $this->assertGreaterThanOrEqual(0, $rating->getComplexRating());
        $this->assertLessThanOrEqual(9999, $rating->getComplexRating());
    }

    public function testEquipmentDoesNotChangeRatings(): void
    {
        $hero = $this->createHero(Race::Human);
        $withoutItems = $this->calculator->calculate($hero);

        $item = new Item();
        $item->setBonuses(['str' => 5, 'damage' => 40]);
        $this->itemRepositoryMock->method('findBy')->willReturn([$item]);

        $withItems = $this->calculator->calculate($hero);

        $this->assertSame($withoutItems->getBaseOvr(), $withItems->getBaseOvr());
        $this->assertSame($withoutItems->getComplexRating(), $withItems->getComplexRating());
    }

    public function testSameStatsDifferentRacesShareBaseOvrButNotComplex(): void
    {
        $human = $this->createHero(Race::Human);
        $elf = $this->createHero(Race::Elf);

        $humanRating = $this->calculator->calculate($human);
        $elfRating = $this->calculator->calculate($elf);

        $this->assertSame($humanRating->getBaseOvr(), $elfRating->getBaseOvr());
        $this->assertNotSame($humanRating->getComplexRating(), $elfRating->getComplexRating());
    }

    public function testTraitIncreasesComplexRatingOnly(): void
    {
        $neutral = $this->createHero(Race::Human);
        $withTrait = $this->createHero(Race::Human);
        $withTrait->setTrait(HeroTrait::QuickLearner);

        $neutralRating = $this->calculator->calculate($neutral);
        $traitRating = $this->calculator->calculate($withTrait);

        $this->assertSame($neutralRating->getBaseOvr(), $traitRating->getBaseOvr());
        $this->assertGreaterThan($neutralRating->getComplexRating(), $traitRating->getComplexRating());
    }

    public function testMasteryIncreasesComplexRatingOnly(): void
    {
        $hero = $this->createHero(Race::Human);
        $withoutMastery = $this->calculator->calculate($hero);

        $weaponMastery = new WeaponMastery();
        $weaponMastery->setHero($hero);
        $weaponMastery->setStyle(ItemSubType::OneHandedSword);
        $weaponMastery->setMasteryTier(4);
        $hero->getWeaponMasteries()->add($weaponMastery);

        $withMastery = $this->calculator->calculate($hero);

        $this->assertSame($withoutMastery->getBaseOvr(), $withMastery->getBaseOvr());
        $this->assertGreaterThan($withoutMastery->getComplexRating(), $withMastery->getComplexRating());
    }

    public function testTrainerIgnoresMasteryBonus(): void
    {
        $trainer = $this->createHero(Race::Human);
        $trainer->setRole(HeroRole::Trainer);

        $withoutMastery = $this->calculator->calculate($trainer);

        $weaponMastery = new WeaponMastery();
        $weaponMastery->setHero($trainer);
        $weaponMastery->setStyle(ItemSubType::Staff);
        $weaponMastery->setMasteryTier(5);
        $trainer->getWeaponMasteries()->add($weaponMastery);

        $schoolMastery = new SchoolMastery();
        $schoolMastery->setHero($trainer);
        $schoolMastery->setSchool(School::Fire);
        $schoolMastery->setMasteryTier(5);
        $trainer->getSchoolMasteries()->add($schoolMastery);

        $withMastery = $this->calculator->calculate($trainer);

        $this->assertSame($withoutMastery->getComplexRating(), $withMastery->getComplexRating());
    }

    public function testHigherLevelIncreasesBothRatings(): void
    {
        $low = $this->createHero(Race::Human, level: 1);
        $high = $this->createHero(Race::Human, level: 10);

        $lowRating = $this->calculator->calculate($low);
        $highRating = $this->calculator->calculate($high);

        $this->assertGreaterThan($lowRating->getBaseOvr(), $highRating->getBaseOvr());
        $this->assertGreaterThan($lowRating->getComplexRating(), $highRating->getComplexRating());
    }

    public function testJuniorAgeIncreasesComplexRatingOnly(): void
    {
        $junior = $this->createHero(Race::Human, displayAge: 18);
        $elder = $this->createHero(Race::Human, displayAge: 85);

        $juniorRating = $this->calculator->calculate($junior);
        $elderRating = $this->calculator->calculate($elder);

        $this->assertSame($juniorRating->getBaseOvr(), $elderRating->getBaseOvr());
        $this->assertGreaterThan($elderRating->getComplexRating(), $juniorRating->getComplexRating());
    }

    public function testEstimateGoldValueUsesComplexRating(): void
    {
        $hero = $this->createHero(Race::Human);
        $rating = $this->calculator->calculate($hero);

        $expected = (int) round(
            $rating->getComplexRating() * $this->heroRatingConfig->getGoldPerComplexPoint()
        );

        $this->assertSame($expected, $this->calculator->estimateGoldValue($hero));
    }

    public function testEstimateMarketPriceAppliesTrainerMultiplier(): void
    {
        $trainer = $this->createHero(Race::Human);
        $trainer->setRole(HeroRole::Trainer);

        $goldValue = $this->calculator->estimateGoldValue($trainer);
        $marketPrice = $this->calculator->estimateMarketPrice($trainer);

        $this->assertSame(
            (int) round($goldValue * $this->heroRatingConfig->getTrainerMarketMultiplier()),
            $marketPrice
        );
    }

    private function createHero(Race $race = Race::Human, int $level = 5, int $displayAge = 18): Hero
    {
        $hero = new Hero();
        $hero->setRace($race);
        $hero->setLevel($level);
        $hero->setAgeRaw($displayAge * 10);
        $hero->setForm(100);
        $hero->setMorale(50);
        $hero->setFatigue(0);

        $hero->setStrRaw(100);
        $hero->setDexRaw(100);
        $hero->setKonRaw(100);
        $hero->setSpdRaw(100);
        $hero->setIntelRaw(100);
        $hero->setWilRaw(100);
        $hero->setLckRaw(100);
        $hero->setChaRaw(100);

        return $hero;
    }
}
