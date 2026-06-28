<?php

declare(strict_types=1);

namespace App\Enum;

enum HeroTrait: string
{
    // ── Pozitivní traity ────────────────────────────────────────────────────
    case QuickLearner = 'quick_learner';
    case Clutch = 'clutch';
    case AudienceFavorite = 'audience_favorite';
    case BattleHardened = 'battle_hardened';

    // ── Negativní traity ────────────────────────────────────────────────────
    case Volatile = 'volatile';
    case Slacker = 'slacker';
    case Fragile = 'fragile';
    case GlassJaw = 'glass_jaw';

    // ── Smíšené traity (tradeoff) ───────────────────────────────────────────
    case Berserker = 'berserker';
    case Glasscannon = 'glass_cannon';
    case Reckless = 'reckless';
    case Loner = 'loner';
    case Overconfident = 'overconfident';
    case Perfectionist = 'perfectionist';

    /**
     * UI category for badge styling: positive, negative, or mixed (tradeoff).
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::QuickLearner, self::Clutch, self::AudienceFavorite, self::BattleHardened => 'positive',
            self::Volatile, self::Slacker, self::Fragile, self::GlassJaw => 'negative',
            default => 'mixed',
        };
    }

    /**
     * Category icon for trait badges in the UI.
     */
    public function getIcon(): string
    {
        return match ($this->getCategory()) {
            'positive' => '✨',
            'negative' => '⚠️',
            default => '⚖️',
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // Training modifiers
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Multiplikátor na raw attribute training gain.
     * Neutrál = 1.0.
     */
    public function getTrainingSpeedMultiplier(): float
    {
        return match ($this) {
            self::QuickLearner => 1.20,
            self::Slacker => 0.85,
            self::Perfectionist => 0.90,
            default => 1.00,
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // HP
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Multiplikátor na maxHp. Neutrál = 1.0.
     */
    public function getHpMultiplier(): float
    {
        return match ($this) {
            self::Fragile => 0.90,
            default => 1.00,
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // Physical attack / spell power / armor
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Multiplikátor na physicalAttack. Neutrál = 1.0.
     */
    public function getPhysAttackMultiplier(): float
    {
        return match ($this) {
            self::Overconfident => 1.10,
            default => 1.00,
        };
    }

    /**
     * Multiplikátor na spellPower. Neutrál = 1.0.
     */
    public function getSpellPowerMultiplier(): float
    {
        return match ($this) {
            self::Glasscannon => 1.15,
            default => 1.00,
        };
    }

    /**
     * Multiplikátor na armorValue. Neutrál = 1.0.
     */
    public function getArmorMultiplier(): float
    {
        return match ($this) {
            self::Glasscannon => 0.90,
            default => 1.00,
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // Accuracy / crit / dodge
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Aditivní bonus k accuracyPercent (může být záporný).
     */
    public function getAccuracyBonus(): float
    {
        return match ($this) {
            self::Berserker => -8.0,
            self::Overconfident => -8.0,
            default => 0.0,
        };
    }

    /**
     * Aditivní bonus k critPercent (může být záporný). Před aplikací capu 50 %.
     */
    public function getCritBonus(): float
    {
        return match ($this) {
            self::Berserker => 15.0,
            self::Reckless => 15.0,
            default => 0.0,
        };
    }

    /**
     * Aditivní bonus k dodgePercent (může být záporný). Před aplikací capu 50 %.
     */
    public function getDodgeBonus(): float
    {
        return match ($this) {
            self::Reckless => -10.0,
            default => 0.0,
        };
    }

    /**
     * Multiplikátor na crit damage. Standardní crit = 1.5×.
     * Berserker způsobuje 2.0× crit damage.
     */
    public function getCritDamageMultiplier(): float
    {
        return match ($this) {
            self::Berserker => 2.00,
            default => 1.50,
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // Morale
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Multiplikátor poklesu morálky při smrti spojence.
     *   BattleHardened: 0.5  — morálka klesá jen na 50 %
     *   Volatile:       2.0  — morálka klesá 2× rychleji
     *   neutrál:        1.0.
     */
    public function getMoraleDecayMultiplier(): float
    {
        return match ($this) {
            self::BattleHardened => 0.50,
            self::Volatile => 2.00,
            default => 1.00,
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // Clutch mechanic (Clutch trait — aktivuje se pod thresholdem HP)
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Pod jakou relativní hranicí HP (0.0–1.0) se aktivují clutch bonusy.
     * Null = žádný clutch efekt.
     *
     * Příklad: 0.30 = aktivuje se pod 30 % max HP.
     */
    public function getClutchHpThreshold(): ?float
    {
        return match ($this) {
            self::Clutch => 0.30,
            default => null,
        };
    }

    /**
     * Aditivní bonus k accuracyPercent aktivovaný clutch thresholdem.
     */
    public function getClutchAccuracyBonus(): float
    {
        return match ($this) {
            self::Clutch => 15.0,
            default => 0.0,
        };
    }

    /**
     * Multiplikátor na armorValue aktivovaný clutch thresholdem.
     */
    public function getClutchArmorMultiplier(): float
    {
        return match ($this) {
            self::Clutch => 1.10,
            default => 1.00,
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // GlassJaw mechanic (penalizace pod prahem HP)
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Pod jakou relativní hranicí HP (0.0–1.0) se aktivuje příchozí penalizace poškození.
     * Null = žádný efekt.
     *
     * Příklad: 0.50 = aktivuje se pod 50 % max HP.
     */
    public function getGlassJawHpThreshold(): ?float
    {
        return match ($this) {
            self::GlassJaw => 0.50,
            default => null,
        };
    }

    /**
     * Multiplikátor na příchozí physical damage, aplikovaný pod GlassJaw prahem.
     * Neutrál = 1.0. GlassJaw = 1.10 (přijímá o 10 % více poškození).
     */
    public function getIncomingDamageMultiplier(): float
    {
        return match ($this) {
            self::GlassJaw => 1.10,
            default => 1.00,
        };
    }

    // ────────────────────────────────────────────────────────────────────────
    // Consistency (Perfectionist)
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Pokud true, combat engine volí střed damage range místo náhodné hodnoty.
     * Hrdina způsobuje konzistentní, předvídatelné poškození.
     *
     * Platí pro Perfectionist.
     */
    public function isConsistentDamage(): bool
    {
        return self::Perfectionist === $this;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Race synergy (Loner)
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Pokud true, hrdina ignoruje jak pozitivní, tak negativní race relationship bonusy.
     * Je to "vlk samotář" — nezávislý na složení týmu.
     *
     * Platí pro Loner.
     */
    public function ignoresRaceSynergy(): bool
    {
        return self::Loner === $this;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Economy (AudienceFavorite)
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Procentuální bonus k příjmu ze vstupného z arény, pokud je hrdina nasazen v zápase.
     * Neutrál = 0.0. AudienceFavorite = 0.05 (= +5 %).
     */
    public function getArenaRevenueBonus(): float
    {
        return match ($this) {
            self::AudienceFavorite => 0.05,
            default => 0.00,
        };
    }
}
