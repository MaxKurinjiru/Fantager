<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Enum\Race;
use App\Repository\Item\ItemRepository;
use App\Service\Combat\CombatStatCalculator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CombatStatCalculatorTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&ItemRepository */
    private $itemRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Hero\HeroMasteryService */
    private $heroMasteryServiceMock;
    private CombatStatCalculator $calculator;

    protected function setUp(): void
    {
        $this->itemRepositoryMock = $this->createMock(ItemRepository::class);
        $this->heroMasteryServiceMock = $this->createMock(\App\Service\Hero\HeroMasteryService::class);
        $this->heroMasteryServiceMock->method('getEquippedSubTypes')->willReturn([]);
        $this->heroMasteryServiceMock->method('getEquippedSpellSchools')->willReturn([]);

        $this->calculator = new CombatStatCalculator($this->itemRepositoryMock, $this->heroMasteryServiceMock);
    }

    private function createHero(Race $race = Race::Human): Hero
    {
        $hero = new Hero();
        $hero->setRace($race);
        $hero->setLevel(5);
        $hero->setForm(100);
        $hero->setMorale(50);
        $hero->setFatigue(0);

        // Effective stat = raw_value / 10
        // Set all raw values to 100 -> effective values = 10
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

    public function testBaseHumanStatsNoEquipment(): void
    {
        $hero = $this->createHero(Race::Human);

        $this->itemRepositoryMock->expects($this->once())
            ->method('findBy')
            ->with(['equippedHero' => $hero])
            ->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        // Max HP = (5 * 30) + (10 * 12) = 150 + 120 = 270
        $this->assertSame(270, $stats->getMaxHp());
        $this->assertSame(270, $stats->getCurrentHp());

        // Attack = 10 * 2 = 20
        $this->assertSame(20, $stats->getPhysicalAttack());

        // Spell Power = 10 * 3 = 30
        $this->assertSame(30, $stats->getSpellPower());

        // Armor = 10 * 1.5 = 15
        $this->assertSame(15, $stats->getArmorValue());
        $this->assertEqualsWithDelta(15.0 / 115.0, $stats->getPhysicalDamageReduction(), 0.0001);

        // Resistance = 10 * 2 = 20
        $this->assertSame(20, $stats->getMagicResistance());
        $this->assertEqualsWithDelta(20.0 / 120.0, $stats->getMagicDamageReduction(), 0.0001);

        // Initiative = 10 * 2 = 20
        $this->assertSame(20, $stats->getBaseInitiative());

        // Accuracy = 80 + 10 + 5 = 95.0
        $this->assertEqualsWithDelta(95.0, $stats->getAccuracyPercent(), 0.0001);

        // Dodge = (10 + 10) * 0.75 + 10 * 0.25 = 15 + 2.5 = 17.5
        $this->assertEqualsWithDelta(17.5, $stats->getDodgePercent(), 0.0001);

        // Crit = 5 + 10 + 2.5 = 17.5
        $this->assertEqualsWithDelta(17.5, $stats->getCritPercent(), 0.0001);
    }

    public function testEquippedStatsWithDurabilityScaling(): void
    {
        $hero = $this->createHero(Race::Human);

        // Item 1: Sword (main hand weapon, 100% durability)
        $sword = $this->createMock(Item::class);
        $sword->method('getDurability')->willReturn(100);
        $sword->method('getBonuses')->willReturn([
            'damage' => 20,
            'str' => 3,
        ]);

        // Item 2: Heavy Body Armor (80% durability)
        $armor = $this->createMock(Item::class);
        $armor->method('getDurability')->willReturn(80);
        $armor->method('getBonuses')->willReturn([
            'armor' => 40,
            'kon' => 2,
        ]);

        // Item 3: Broken Ring (0% durability)
        $ring = $this->createMock(Item::class);
        $ring->method('getDurability')->willReturn(0);
        $ring->method('getBonuses')->willReturn([
            'str' => 10,
            'lck' => 5,
        ]);

        $this->itemRepositoryMock->expects($this->once())
            ->method('findBy')
            ->with(['equippedHero' => $hero])
            ->willReturn([$sword, $armor, $ring]);

        $stats = $this->calculator->calculate($hero);

        // Effective STR = 10 (base) + 3 * 1.0 (sword) + 0 * 0 (broken ring) = 13.0
        // Effective KON = 10 (base) + 2 * 0.8 (armor) = 11.6
        // Item damage = 20 * 1.0 = 20
        // Item armor = 40 * 0.8 = 32

        // Max HP = (5 * 30) + (11.6 * 12) = 150 + 139.2 = 289.2 -> 289
        $this->assertSame(289, $stats->getMaxHp());

        // Attack = 20 (weapon damage) * (1 + 13.0 / 15.0) = 20 * 1.86667 = 37.333 -> 37
        $this->assertSame(37, $stats->getPhysicalAttack());

        // Armor = 32 (item armor) + 11.6 * 1.5 = 32 + 17.4 = 49.4 -> 49
        $this->assertSame(49, $stats->getArmorValue());
        $this->assertEqualsWithDelta(49.0 / 149.0, $stats->getPhysicalDamageReduction(), 0.0001);
    }

    public function testEntRaceBonus(): void
    {
        $hero = $this->createHero(Race::Ent);

        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        // Ent gets +20% HP calculations: Max HP = (5 * 30) + (10 * 12 * 1.2) = 150 + 144 = 294
        $this->assertSame(294, $stats->getMaxHp());

        // Ent gets -20% speed-based actions (Initiative): SPD * 2 * 0.8 = 10 * 2 * 0.8 = 16
        $this->assertSame(16, $stats->getBaseInitiative());
    }

    public function testElfRaceBonus(): void
    {
        $hero = $this->createHero(Race::Elf);

        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        // Elf gets +10% Accuracy: 95.0 + 10 = 105.0
        $this->assertEqualsWithDelta(105.0, $stats->getAccuracyPercent(), 0.0001);

        // Elf gets +10% Dodge: 17.5 + 10 = 27.5
        $this->assertEqualsWithDelta(27.5, $stats->getDodgePercent(), 0.0001);
    }

    public function testDwarfRaceBonus(): void
    {
        $hero = $this->createHero(Race::Dwarf);

        // Dwarf gets +15% armor effectiveness
        // Item: Heavy Shield (100% durability)
        $shield = $this->createMock(Item::class);
        $shield->method('getDurability')->willReturn(100);
        $shield->method('getBonuses')->willReturn([
            'armor' => 20,
        ]);

        $this->itemRepositoryMock->method('findBy')->willReturn([$shield]);

        $stats = $this->calculator->calculate($hero);

        // Base Armor = 20 (shield) + 10 * 1.5 = 35
        // Dwarf Armor = 35 * 1.15 = 40.25 -> 40
        $this->assertSame(40, $stats->getArmorValue());
    }

    public function testGenieRaceBonus(): void
    {
        $hero = $this->createHero(Race::Genie);

        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        // Genie gets +15% spell power: (10 * 3) * 1.15 = 30 * 1.15 = 34.5 -> 35
        $this->assertSame(35, $stats->getSpellPower());

        // Genie gets +5% crit (representing the Genie bonus): 17.5 + 5 = 22.5
        $this->assertEqualsWithDelta(22.5, $stats->getCritPercent(), 0.0001);
    }

    public function testGiantRaceBonus(): void
    {
        $hero = $this->createHero(Race::Giant);

        // Giant gets +10% weapon damage:
        $sword = $this->createMock(Item::class);
        $sword->method('getDurability')->willReturn(100);
        $sword->method('getBonuses')->willReturn([
            'damage' => 20,
        ]);

        $this->itemRepositoryMock->method('findBy')->willReturn([$sword]);

        $stats = $this->calculator->calculate($hero);

        // Weapon damage base scaling = 20 * (1 + 10 / 15.0) = 20 * 1.6667 = 33.333
        // Giant bonus: 33.333 * 1.10 = 36.667 -> 37
        $this->assertSame(37, $stats->getPhysicalAttack());
    }

    public function testMasteryBonusesAppliedWhenAttuned(): void
    {
        $hero = $this->createHero(Race::Human);

        // Mock equipped weapon subtype: sword
        $this->heroMasteryServiceMock = $this->createMock(\App\Service\Hero\HeroMasteryService::class);
        $this->heroMasteryServiceMock->method('getEquippedSubTypes')->willReturn([\App\Enum\ItemSubType::OneHandedSword]);
        $this->heroMasteryServiceMock->method('getEquippedSpellSchools')->willReturn([\App\Enum\School::Fire]);

        // Recreate calculator with the active mock
        $this->calculator = new CombatStatCalculator($this->itemRepositoryMock, $this->heroMasteryServiceMock);

        // Add WeaponMastery entity to hero: OneHandedSword, Tier 3, Attuned (100)
        $wm = new \App\Entity\Hero\WeaponMastery();
        $wm->setHero($hero);
        $wm->setStyle(\App\Enum\ItemSubType::OneHandedSword);
        $wm->setMasteryTier(3);
        $wm->setAttunementProgress(100);
        $hero->getWeaponMasteries()->add($wm);

        // Add SchoolMastery entity to hero: Fire, Tier 2
        $sm = new \App\Entity\Hero\SchoolMastery();
        $sm->setHero($hero);
        $sm->setSchool(\App\Enum\School::Fire);
        $sm->setMasteryTier(2);
        $hero->getSchoolMasteries()->add($sm);

        // Mock Item: Sword (Main hand)
        $sword = $this->createMock(Item::class);
        $sword->method('getDurability')->willReturn(100);
        $sword->method('getBonuses')->willReturn([
            'damage' => 20,
        ]);
        $this->itemRepositoryMock->method('findBy')->willReturn([$sword]);

        $stats = $this->calculator->calculate($hero);

        // Without mastery: Attack is 20 * (1 + 10 / 15.0) = 33.333 -> 33
        // With Sword Tier 3 Mastery (+10% Physical Attack): 33.333 * 1.10 = 36.667 -> 37
        $this->assertSame(37, $stats->getPhysicalAttack());

        // Without mastery: Spell Power is 10 * 3 = 30
        // With Fire Tier 2 Magic Mastery (+5% Spell Power): 30 * 1.05 = 31.5 -> 32
        $this->assertSame(32, $stats->getSpellPower());
    }

    public function testArmorMasteryBonusesAppliedWhenAttuned(): void
    {
        $hero = $this->createHero(Race::Human);

        // Mock equipped armor subtype: heavy armor
        $this->heroMasteryServiceMock = $this->createMock(\App\Service\Hero\HeroMasteryService::class);
        $this->heroMasteryServiceMock->method('getEquippedSubTypes')->willReturn([\App\Enum\ItemSubType::HeavyArmor]);
        $this->heroMasteryServiceMock->method('getEquippedSpellSchools')->willReturn([]);

        // Recreate calculator with the active mock
        $this->calculator = new CombatStatCalculator($this->itemRepositoryMock, $this->heroMasteryServiceMock);

        // Add WeaponMastery entity to hero: HeavyArmor, Tier 3, Attuned (100)
        $wm = new \App\Entity\Hero\WeaponMastery();
        $wm->setHero($hero);
        $wm->setStyle(\App\Enum\ItemSubType::HeavyArmor);
        $wm->setMasteryTier(3);
        $wm->setAttunementProgress(100);
        $hero->getWeaponMasteries()->add($wm);

        // Mock Item: Breastplate
        $breastplate = $this->createMock(Item::class);
        $breastplate->method('getDurability')->willReturn(100);
        $breastplate->method('getBonuses')->willReturn([
            'armor' => 20,
            'resistance' => 10,
        ]);
        $this->itemRepositoryMock->method('findBy')->willReturn([$breastplate]);

        $stats = $this->calculator->calculate($hero);

        // Without mastery: Armor is 20 (breastplate) + 10 * 1.5 = 35
        // With Heavy Armor Tier 3 Mastery (+10% Armor Value): 35 * 1.10 = 38.5 -> 39
        $this->assertSame(39, $stats->getArmorValue());

        // Without mastery: Resistance is 10 (breastplate) + 10 * 2.0 = 30
        // With Heavy Armor Tier 3 Mastery (+2% Magic Resistance): 30 * 1.02 = 30.6 -> 31
        $this->assertSame(31, $stats->getMagicResistance());
    }

    // ── Trait modifier tests ─────────────────────────────────────────────────

    /**
     * Hero bez traitu má neutrální trait-derived hodnoty v DerivedCombatStats.
     */
    public function testNoTraitHasNeutralDerivedValues(): void
    {
        $hero = $this->createHero(Race::Human);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertSame(1.5, $stats->getCritDamageMultiplier());
        $this->assertSame(1.0, $stats->getMoraleDecayMultiplier());
        $this->assertSame(0.0, $stats->getArenaRevenueBonus());
        $this->assertNull($stats->getClutchHpThreshold());
        $this->assertNull($stats->getGlassJawHpThreshold());
        $this->assertFalse($stats->isConsistentDamage());
        $this->assertFalse($stats->ignoresRaceSynergy());
    }

    /**
     * Fragile trait snižuje maxHp o 10 %.
     * Baseline: Level 5, KON 10 → maxHp = 5*30 + 10*12 = 270
     * Fragile (0.90): 270 * 0.90 = 243
     */
    public function testFragileReducesMaxHp(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Fragile);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $baseHp = 5 * 30 + 10 * 12; // 270
        $expectedHp = (int) round($baseHp * 0.90); // 243
        $this->assertSame($expectedHp, $stats->getMaxHp());
    }

    /**
     * Berserker trait: +15 % crit, -8 % accuracy, crit damage = 2.0×.
     * Baseline crit (Human, DEX=10, LCK=10): 5 + 10*1.0 + 10*0.25 = 17.5 %
     * With Berserker: 17.5 + 15.0 = 32.5 % (pod cap 50 %)
     *
     * Baseline accuracy: 80 + 10*1.0 + 10*0.5 = 95 %
     * With Berserker: 95 - 8 = 87 %
     */
    public function testBerserkerModifiesCritAndAccuracy(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Berserker);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertEqualsWithDelta(32.5, $stats->getCritPercent(), 0.01);
        $this->assertEqualsWithDelta(87.0, $stats->getAccuracyPercent(), 0.01);
        $this->assertSame(2.0, $stats->getCritDamageMultiplier());
        $this->assertSame(1.0, $stats->getMoraleDecayMultiplier()); // Berserker nemá morale efekt
    }

    /**
     * Glasscannon: spell power +15 %, armor -10 %.
     * Baseline armor (Human, KON=10, no equipment): KON*1.5 = 15
     * With Glasscannon: 15 * 0.90 = 13.5 → 14
     *
     * Baseline spell power (INT=10): 10*3 = 30
     * With Glasscannon: 30 * 1.15 = 34.5 → 35
     */
    public function testGlasscannonModifiesSpellPowerAndArmor(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Glasscannon);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertSame(35, $stats->getSpellPower());
        $this->assertSame(14, $stats->getArmorValue());
    }

    /**
     * Overconfident: phys attack +10 %, accuracy -8 %.
     * Baseline unarmed attack (STR=10): STR*2 = 20
     * With Overconfident: 20 * 1.10 = 22
     */
    public function testOverconfidentModifiesAttackAndAccuracy(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Overconfident);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertSame(22, $stats->getPhysicalAttack());
        $this->assertEqualsWithDelta(87.0, $stats->getAccuracyPercent(), 0.01); // 95 - 8
    }

    /**
     * Reckless: crit +15 %, dodge -10 %.
     * Baseline dodge (Human, DEX=10, SPD=10, LCK=10): (10+10)*0.75 + 10*0.25 = 17.5 %
     * With Reckless: 17.5 - 10.0 = 7.5 %
     */
    public function testRecklessModifiesCritAndDodge(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Reckless);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertEqualsWithDelta(32.5, $stats->getCritPercent(), 0.01); // 17.5 + 15
        $this->assertEqualsWithDelta(7.5, $stats->getDodgePercent(), 0.01);  // 17.5 - 10
    }

    /**
     * Clutch trait: threshold 0.30, clutch accuracy bonus +15, clutch armor ×1.10.
     */
    public function testClutchHasCorrectThresholdMetadata(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Clutch);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertSame(0.30, $stats->getClutchHpThreshold());
        $this->assertSame(15.0, $stats->getClutchAccuracyBonus());
        $this->assertSame(1.10, $stats->getClutchArmorMultiplier());
        $this->assertNull($stats->getGlassJawHpThreshold());
    }

    /**
     * GlassJaw trait: threshold 0.50, incoming damage multiplier 1.10.
     */
    public function testGlassJawHasCorrectThresholdMetadata(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::GlassJaw);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertSame(0.50, $stats->getGlassJawHpThreshold());
        $this->assertSame(1.10, $stats->getIncomingDamageMultiplier());
        $this->assertNull($stats->getClutchHpThreshold());
    }

    /**
     * Volatile trait: morale decay multiplier 2.0.
     * BattleHardened: morale decay multiplier 0.5.
     * Ostatní traity mají neutrál 1.0.
     */
    public function testMoraleDecayMultiplierFromTrait(): void
    {
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $volatile = $this->createHero(Race::Human);
        $volatile->setTrait(\App\Enum\HeroTrait::Volatile);
        $this->assertSame(2.0, $this->calculator->calculate($volatile)->getMoraleDecayMultiplier());

        $hardened = $this->createHero(Race::Human);
        $hardened->setTrait(\App\Enum\HeroTrait::BattleHardened);
        $this->assertSame(0.5, $this->calculator->calculate($hardened)->getMoraleDecayMultiplier());

        $neutral = $this->createHero(Race::Human);
        $neutral->setTrait(\App\Enum\HeroTrait::QuickLearner);
        $this->assertSame(1.0, $this->calculator->calculate($neutral)->getMoraleDecayMultiplier());
    }

    /**
     * Perfectionist trait: isConsistentDamage = true.
     */
    public function testPerfectionistIsConsistentDamage(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Perfectionist);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertTrue($stats->isConsistentDamage());
        $this->assertFalse($stats->ignoresRaceSynergy());
    }

    /**
     * Loner trait: ignoresRaceSynergy = true.
     */
    public function testLonerIgnoresRaceSynergy(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::Loner);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertTrue($stats->ignoresRaceSynergy());
        $this->assertFalse($stats->isConsistentDamage());
    }

    /**
     * AudienceFavorite: arenaRevenueBonus = 0.05.
     */
    public function testAudienceFavoriteArenaBonus(): void
    {
        $hero = $this->createHero(Race::Human);
        $hero->setTrait(\App\Enum\HeroTrait::AudienceFavorite);
        $this->itemRepositoryMock->method('findBy')->willReturn([]);

        $stats = $this->calculator->calculate($hero);

        $this->assertSame(0.05, $stats->getArenaRevenueBonus());
        $this->assertNull($stats->getClutchHpThreshold());
        $this->assertNull($stats->getGlassJawHpThreshold());
        $this->assertFalse($stats->isConsistentDamage());
        $this->assertFalse($stats->ignoresRaceSynergy());
    }
}
