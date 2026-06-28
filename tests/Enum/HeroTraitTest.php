<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\HeroTrait;
use PHPUnit\Framework\TestCase;

class HeroTraitTest extends TestCase
{
    /**
     * Všechny traity musí vracet platné hodnoty ze všech metod modifikátorů
     * — žádné výjimky, žádné neočekávané null.
     */
    public function testAllTraitsReturnValidModifiers(): void
    {
        foreach (HeroTrait::cases() as $trait) {
            $this->assertIsFloat($trait->getTrainingSpeedMultiplier(), "{$trait->value}: getTrainingSpeedMultiplier()");
            $this->assertIsFloat($trait->getHpMultiplier(), "{$trait->value}: getHpMultiplier()");
            $this->assertIsFloat($trait->getPhysAttackMultiplier(), "{$trait->value}: getPhysAttackMultiplier()");
            $this->assertIsFloat($trait->getSpellPowerMultiplier(), "{$trait->value}: getSpellPowerMultiplier()");
            $this->assertIsFloat($trait->getArmorMultiplier(), "{$trait->value}: getArmorMultiplier()");
            $this->assertIsFloat($trait->getAccuracyBonus(), "{$trait->value}: getAccuracyBonus()");
            $this->assertIsFloat($trait->getCritBonus(), "{$trait->value}: getCritBonus()");
            $this->assertIsFloat($trait->getDodgeBonus(), "{$trait->value}: getDodgeBonus()");
            $this->assertIsFloat($trait->getCritDamageMultiplier(), "{$trait->value}: getCritDamageMultiplier()");
            $this->assertIsFloat($trait->getMoraleDecayMultiplier(), "{$trait->value}: getMoraleDecayMultiplier()");
            $this->assertIsFloat($trait->getClutchAccuracyBonus(), "{$trait->value}: getClutchAccuracyBonus()");
            $this->assertIsFloat($trait->getClutchArmorMultiplier(), "{$trait->value}: getClutchArmorMultiplier()");
            $this->assertIsFloat($trait->getIncomingDamageMultiplier(), "{$trait->value}: getIncomingDamageMultiplier()");
            $this->assertIsBool($trait->isConsistentDamage(), "{$trait->value}: isConsistentDamage()");
            $this->assertIsBool($trait->ignoresRaceSynergy(), "{$trait->value}: ignoresRaceSynergy()");
            $this->assertIsFloat($trait->getArenaRevenueBonus(), "{$trait->value}: getArenaRevenueBonus()");
            $this->assertContains($trait->getCategory(), ['positive', 'negative', 'mixed'], "{$trait->value}: getCategory()");
            $this->assertNotEmpty($trait->getIcon(), "{$trait->value}: getIcon()");
        }
    }

    /**
     * Všechny multiplikátory musí být kladné číslo > 0.
     */
    public function testMultipliersArePositive(): void
    {
        foreach (HeroTrait::cases() as $trait) {
            $this->assertGreaterThan(0.0, $trait->getTrainingSpeedMultiplier(), "{$trait->value}: training multiplier must be > 0");
            $this->assertGreaterThan(0.0, $trait->getHpMultiplier(), "{$trait->value}: HP multiplier must be > 0");
            $this->assertGreaterThan(0.0, $trait->getPhysAttackMultiplier(), "{$trait->value}: phys attack multiplier must be > 0");
            $this->assertGreaterThan(0.0, $trait->getSpellPowerMultiplier(), "{$trait->value}: spell power multiplier must be > 0");
            $this->assertGreaterThan(0.0, $trait->getArmorMultiplier(), "{$trait->value}: armor multiplier must be > 0");
            $this->assertGreaterThan(0.0, $trait->getCritDamageMultiplier(), "{$trait->value}: crit damage multiplier must be > 0");
            $this->assertGreaterThan(0.0, $trait->getMoraleDecayMultiplier(), "{$trait->value}: morale decay multiplier must be > 0");
        }
    }

    // ── Konkrétní hodnoty pro všechny traity ────────────────────────────────

    public function testQuickLearner(): void
    {
        $t = HeroTrait::QuickLearner;
        $this->assertSame(1.20, $t->getTrainingSpeedMultiplier());
        $this->assertSame(1.0, $t->getHpMultiplier());
        $this->assertSame(0.0, $t->getAccuracyBonus());
        $this->assertSame(1.5, $t->getCritDamageMultiplier());
        $this->assertSame(1.0, $t->getMoraleDecayMultiplier());
        $this->assertNull($t->getClutchHpThreshold());
        $this->assertNull($t->getGlassJawHpThreshold());
        $this->assertFalse($t->isConsistentDamage());
        $this->assertFalse($t->ignoresRaceSynergy());
        $this->assertSame(0.0, $t->getArenaRevenueBonus());
    }

    public function testClutch(): void
    {
        $t = HeroTrait::Clutch;
        $this->assertSame(1.0, $t->getTrainingSpeedMultiplier());
        $this->assertSame(0.30, $t->getClutchHpThreshold());
        $this->assertSame(15.0, $t->getClutchAccuracyBonus());
        $this->assertSame(1.10, $t->getClutchArmorMultiplier());
        $this->assertNull($t->getGlassJawHpThreshold());
        $this->assertFalse($t->isConsistentDamage());
    }

    public function testAudienceFavorite(): void
    {
        $t = HeroTrait::AudienceFavorite;
        $this->assertSame(0.05, $t->getArenaRevenueBonus());
        $this->assertSame(1.0, $t->getTrainingSpeedMultiplier());
        $this->assertNull($t->getClutchHpThreshold());
    }

    public function testBattleHardened(): void
    {
        $t = HeroTrait::BattleHardened;
        $this->assertSame(0.50, $t->getMoraleDecayMultiplier());
        $this->assertSame(1.0, $t->getTrainingSpeedMultiplier());
        $this->assertSame(0.0, $t->getArenaRevenueBonus());
    }

    public function testVolatile(): void
    {
        $t = HeroTrait::Volatile;
        $this->assertSame(2.00, $t->getMoraleDecayMultiplier());
        $this->assertSame(1.0, $t->getTrainingSpeedMultiplier());
    }

    public function testSlacker(): void
    {
        $t = HeroTrait::Slacker;
        $this->assertSame(0.85, $t->getTrainingSpeedMultiplier());
        $this->assertSame(1.0, $t->getHpMultiplier());
        $this->assertSame(1.0, $t->getMoraleDecayMultiplier());
    }

    public function testFragile(): void
    {
        $t = HeroTrait::Fragile;
        $this->assertSame(0.90, $t->getHpMultiplier());
        $this->assertSame(1.0, $t->getTrainingSpeedMultiplier());
        $this->assertSame(1.0, $t->getPhysAttackMultiplier());
    }

    public function testGlassJaw(): void
    {
        $t = HeroTrait::GlassJaw;
        $this->assertSame(0.50, $t->getGlassJawHpThreshold());
        $this->assertSame(1.10, $t->getIncomingDamageMultiplier());
        $this->assertNull($t->getClutchHpThreshold());
        $this->assertSame(1.0, $t->getHpMultiplier());
    }

    public function testBerserker(): void
    {
        $t = HeroTrait::Berserker;
        $this->assertSame(2.00, $t->getCritDamageMultiplier());
        $this->assertSame(-8.0, $t->getAccuracyBonus());
        $this->assertSame(15.0, $t->getCritBonus());
        $this->assertSame(0.0, $t->getDodgeBonus());
        $this->assertSame(1.0, $t->getTrainingSpeedMultiplier());
    }

    public function testGlasscannon(): void
    {
        $t = HeroTrait::Glasscannon;
        $this->assertSame(1.15, $t->getSpellPowerMultiplier());
        $this->assertSame(0.90, $t->getArmorMultiplier());
        $this->assertSame(1.0, $t->getPhysAttackMultiplier());
        $this->assertSame(1.5, $t->getCritDamageMultiplier()); // neutrál
    }

    public function testReckless(): void
    {
        $t = HeroTrait::Reckless;
        $this->assertSame(15.0, $t->getCritBonus());
        $this->assertSame(-10.0, $t->getDodgeBonus());
        $this->assertSame(0.0, $t->getAccuracyBonus());
        $this->assertSame(1.5, $t->getCritDamageMultiplier()); // neutrál
    }

    public function testLoner(): void
    {
        $t = HeroTrait::Loner;
        $this->assertTrue($t->ignoresRaceSynergy());
        $this->assertSame(1.0, $t->getTrainingSpeedMultiplier());
        $this->assertSame(0.0, $t->getArenaRevenueBonus());
    }

    public function testOverconfident(): void
    {
        $t = HeroTrait::Overconfident;
        $this->assertSame(1.10, $t->getPhysAttackMultiplier());
        $this->assertSame(-8.0, $t->getAccuracyBonus());
        $this->assertSame(1.0, $t->getSpellPowerMultiplier());
        $this->assertSame(1.0, $t->getHpMultiplier());
    }

    public function testPerfectionist(): void
    {
        $t = HeroTrait::Perfectionist;
        $this->assertTrue($t->isConsistentDamage());
        $this->assertSame(0.90, $t->getTrainingSpeedMultiplier());
        $this->assertSame(1.0, $t->getHpMultiplier());
        $this->assertFalse($t->ignoresRaceSynergy());
    }

    /**
     * Ověří, že neutrální traity neovlivňují hodnoty, které jsou pro ně irelevantní.
     */
    public function testNeutralTraitsHaveNoSideEffects(): void
    {
        // QuickLearner nemá žádné combat efekty
        $t = HeroTrait::QuickLearner;
        $this->assertSame(1.0, $t->getHpMultiplier());
        $this->assertSame(1.0, $t->getPhysAttackMultiplier());
        $this->assertSame(1.0, $t->getSpellPowerMultiplier());
        $this->assertSame(1.0, $t->getArmorMultiplier());
        $this->assertSame(0.0, $t->getAccuracyBonus());
        $this->assertSame(0.0, $t->getCritBonus());
        $this->assertSame(0.0, $t->getDodgeBonus());
        $this->assertSame(1.5, $t->getCritDamageMultiplier());
        $this->assertFalse($t->isConsistentDamage());
        $this->assertFalse($t->ignoresRaceSynergy());
        $this->assertNull($t->getClutchHpThreshold());
        $this->assertNull($t->getGlassJawHpThreshold());

        // Slacker nemá žádné combat efekty
        $t = HeroTrait::Slacker;
        $this->assertSame(1.0, $t->getHpMultiplier());
        $this->assertSame(1.0, $t->getMoraleDecayMultiplier());
        $this->assertSame(0.0, $t->getArenaRevenueBonus());
        $this->assertNull($t->getClutchHpThreshold());
        $this->assertFalse($t->isConsistentDamage());
    }

    /**
     * Berserker vs. BattleHardened mají opačné morale efekty od Volatile —
     * klíčové pro vyvážení herní mechaniky.
     */
    public function testMoraleDecayOrdering(): void
    {
        $this->assertSame(0.50, HeroTrait::BattleHardened->getMoraleDecayMultiplier());
        $this->assertSame(1.00, HeroTrait::Berserker->getMoraleDecayMultiplier());
        $this->assertSame(2.00, HeroTrait::Volatile->getMoraleDecayMultiplier());
    }
}
