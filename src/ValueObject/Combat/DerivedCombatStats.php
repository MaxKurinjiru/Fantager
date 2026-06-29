<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

final class DerivedCombatStats
{
    public function __construct(
        private int $maxHp,
        private int $currentHp,
        private int $physicalAttack,
        private int $spellPower,
        private int $armorValue,
        private float $physicalDamageReduction,
        private int $magicResistance,
        private float $magicDamageReduction,
        private int $baseInitiative,
        private float $accuracyPercent,
        private float $dodgePercent,
        private float $critPercent,
        // ── Trait-derived modifiers ─────────────────────────────────────────
        /** Multiplikátor crit damage. Standardní = 1.5. Berserker = 2.0. */
        private float $critDamageMultiplier = 1.5,
        /** Multiplikátor poklesu morálky při smrti spojence. Neutrál = 1.0. */
        private float $moraleDecayMultiplier = 1.0,
        /** Procentuální bonus k příjmu ze vstupného (AudienceFavorite). Neutrál = 0.0. */
        private float $arenaRevenueBonus = 0.0,
        /**
         * Pod jakou relativní hranicí HP (0.0–1.0) se aktivují clutch bonusy.
         * Null = žádný clutch efekt (Clutch trait).
         */
        private ?float $clutchHpThreshold = null,
        /** Bonus k accuracyPercent aktivovaný clutch thresholdem. */
        private float $clutchAccuracyBonus = 0.0,
        /** Multiplikátor na armorValue aktivovaný clutch thresholdem. */
        private float $clutchArmorMultiplier = 1.0,
        /**
         * Pod jakou relativní hranicí HP se aktivuje příchozí penalizace (GlassJaw).
         * Null = žádný efekt.
         */
        private ?float $glassJawHpThreshold = null,
        /** Multiplikátor na příchozí physical damage pod GlassJaw prahem. Neutrál = 1.0. */
        private float $incomingDamageMultiplier = 1.0,
        /**
         * Pokud true, combat engine volí střed damage range (Perfectionist).
         * Způsobuje konzistentní, předvídatelné poškození.
         */
        private bool $isConsistentDamage = false,
        /**
         * Pokud true, hrdina ignoruje race relationship synergy bonusy i postihy (Loner).
         */
        private bool $ignoresRaceSynergy = false,
    ) {
    }

    public function getMaxHp(): int
    {
        return $this->maxHp;
    }

    public function getCurrentHp(): int
    {
        return $this->currentHp;
    }

    public function getPhysicalAttack(): int
    {
        return $this->physicalAttack;
    }

    public function getSpellPower(): int
    {
        return $this->spellPower;
    }

    public function getArmorValue(): int
    {
        return $this->armorValue;
    }

    public function getPhysicalDamageReduction(): float
    {
        return $this->physicalDamageReduction;
    }

    public function getMagicResistance(): int
    {
        return $this->magicResistance;
    }

    public function getMagicDamageReduction(): float
    {
        return $this->magicDamageReduction;
    }

    public function getBaseInitiative(): int
    {
        return $this->baseInitiative;
    }

    public function getAccuracyPercent(): float
    {
        return $this->accuracyPercent;
    }

    public function getDodgePercent(): float
    {
        return $this->dodgePercent;
    }

    public function getCritPercent(): float
    {
        return $this->critPercent;
    }

    public function getCritDamageMultiplier(): float
    {
        return $this->critDamageMultiplier;
    }

    public function getMoraleDecayMultiplier(): float
    {
        return $this->moraleDecayMultiplier;
    }

    public function getArenaRevenueBonus(): float
    {
        return $this->arenaRevenueBonus;
    }

    public function getClutchHpThreshold(): ?float
    {
        return $this->clutchHpThreshold;
    }

    public function getClutchAccuracyBonus(): float
    {
        return $this->clutchAccuracyBonus;
    }

    public function getClutchArmorMultiplier(): float
    {
        return $this->clutchArmorMultiplier;
    }

    public function getGlassJawHpThreshold(): ?float
    {
        return $this->glassJawHpThreshold;
    }

    public function getIncomingDamageMultiplier(): float
    {
        return $this->incomingDamageMultiplier;
    }

    public function isConsistentDamage(): bool
    {
        return $this->isConsistentDamage;
    }

    public function ignoresRaceSynergy(): bool
    {
        return $this->ignoresRaceSynergy;
    }
}
