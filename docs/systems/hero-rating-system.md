# Hero Rating System

Reference: [hero-system.md](hero-system.md), [combat-system.md](combat-system.md), [marketplace-system.md](marketplace-system.md)

Status: Live  
Screens: [03-hero-roster.md](../screens/03-hero-roster.md), [04-hero-detail.md](../screens/04-hero-detail.md), [15-marketplace.md](../screens/15-marketplace.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none

Purpose: Two cached **intrinsic** ratings — cross-race **base OVR** (0–100) and **complex rating** (0–9999) for market, salary, and dismiss pay. Equipment, spells, form, fatigue, morale, and team chemistry are excluded by design.

## Parked

Index: [known-issues.md](../product/known-issues.md) § Later product decisions. Unique to this system:

- Rating breakdown payload — API is two integers; do not invent a third field
- Simulation-driven YAML calibration (`config/game/hero-rating.yaml` weights are still placeholders; reference ceilings are the unarmed maxima)

---

## Summary

| Metric | Range | Purpose |
|--------|-------|---------|
| `base_ovr` | 0–100 | Fair comparison across races |
| `complex_rating` | 0–9999 | Marketplace value, dismiss compensation, NPC pricing, hero/trainer salaries |

Both columns are cached on `Hero` and refreshed by `HeroRatingCacheSubscriber` (Doctrine `onFlush`) when the hero or a mastery row changes. Bulk repair: `php bin/console app:hero-ratings:refresh`.

Gold amounts that need an intrinsic hero value use `complex_rating × gold_per_complex_point` (and the role factors below). Listing prices must sit in the suggested band — [marketplace-system.md](marketplace-system.md#locked-listing-price-band).

---

## Inputs

| Input | `base_ovr` | `complex_rating` |
|-------|------------|------------------|
| 8 primary attributes (STR–LCK, including CHA) | yes | yes |
| Level | yes | yes |
| Race | Human formulas, plus a discounted physical factor for Orc (×1.10) and Giant (×1.05) | actual race + combat passives, plus the same Orc/Giant factor |
| Trait | no | yes — additive bonus (combat and economic traits) |
| Weapon / school mastery | no | yes — additive bonus (not tied to equipped loadout) |
| Attunement | no | no |
| Learned spells | no | yes — small surcharge from spell tier, equipped or not |
| Magic capacity | no | yes — small surcharge per point |
| Age | no | yes — market-value multiplier |
| Equipment | no | no |
| Form / fatigue / morale | no | no |
| Team chemistry / morale | no | no |

Equipping items must not change either rating.

### Human-neutral base OVR

For `base_ovr`, race is hard-coded to Human in derived-stat formulas. Stored primary attributes are used as-is (no race-bonus subtraction).

### Hybrid score

From the same derived combat stats, compute a **physical** weighted sum and a **magic** weighted sum. Final hybrid: `average(physical, magic)`, then scale (`base_ovr` → 0–100, `complex_rating` → 0–9999). CHA has dedicated weights on both paths.

### Trainers

Same calculator. Trainers get **0** mastery bonus (they do not fight). Trait and age still apply. Marketplace and salary use the trainer YAML factors, not a second rating.

---

## Formula

```
derived_stats = CombatStatCalculator::calculateForProfile(hero, profile)

phys_score  = weighted_sum(derived_stats, physical_weights)
magic_score = weighted_sum(derived_stats, magic_weights)
hybrid      = (phys_score + magic_score) / 2

base_ovr = scale_to_100(hybrid)                 // HumanNeutral profile

complex_base = scale_to_9999(hybrid)            // FullIntrinsic profile
complex_rating = clamp(
    round((complex_base + trait_bonus + mastery_bonus + spell_knowledge_bonus) × age_multiplier),
    0, 9999
)
```

`CombatStatCalculator` profiles:

| Profile | Use |
|---------|-----|
| `Equipped` | Live combat / replay — existing `calculate(Hero)` |
| `HumanNeutral` | `base_ovr`: race = Human, no trait, no items, no mastery, form = 100 |
| `FullIntrinsic` | Complex combat base: actual race, no trait/items/mastery, form = 100 |

Mastery bonus (complex, combatants only): all `WeaponMastery` and `SchoolMastery` rows; **tier** only; ignore attunement and equipped gear. Spell knowledge (complex, combatants and trainers): `magic_capacity × 12`, plus `6 + 2 × tier` for each learned spell. Orc and Giant physical attack is multiplied by `race_physical_factor` before weighting, in both profiles. Reference ceilings for attack, spell power, armor, and resistance are the unarmed maxima (Giant/Orc attack ceiling is 44 so the ×1.10 factor is not clipped). Trait bonus: one YAML value per `HeroTrait`. Age multiplier: `RaceConfig::resolveAgePhase()` buckets (junior / prime / veteran / elder). Economy constants (`gold_per_complex_point`, salary, dismiss) are unchanged by this weight pass.

---

## Economy

Live constants: `config/game/hero-rating.yaml` (loaded by `HeroRatingConfig`).

| Constant | Live value | Use |
|----------|------------|-----|
| `gold_per_complex_point` | 0.5 | Suggested market price, dismiss estimate |
| `dismiss_compensation_ratio` | 0.4 | Combatant dismiss pay |
| `trainer_dismiss_compensation_ratio` | 0.3 | Trainer dismiss pay |
| `hero_salary_factor` | 0.02 | Weekly combatant salary |
| `trainer_salary_factor` | 0.03 | Weekly trainer salary |
| `trainer_market_multiplier` | 1.5 | NPC trainer suggested price |

Weekly salary: `complex_rating × gold_per_complex_point × role_factor` — [economy-system.md](economy-system.md#weekly-payroll).

---

## Cache, API, UI

| Surface | Contract |
|---------|----------|
| DB | `hero.base_ovr`, `hero.complex_rating` + indexes |
| `GET /api/v1/heroes` and hero detail | `ratings.base_ovr`, `ratings.complex_rating` (two integers) |
| Marketplace listings | same ratings; hero includes `trait`; filter/sort on cached columns |
| Twig | `hero_rating()`, `hero_gold_value()`, `hero_rating_badge`, detail `ratings_panel` |
| Graveyard | memorial snapshot includes ratings |
| Backfill | `app:hero-ratings:refresh` |

---

## Related code

- `HeroRatingCalculator`, `HeroRating` VO, `HeroSalaryService`, `HeroRatingCacheSubscriber`
- `CombatStatCalculator::calculateForProfile()` / `CombatStatProfile`
- Tests: `tests/Service/Hero/HeroRatingCalculatorTest.php`
