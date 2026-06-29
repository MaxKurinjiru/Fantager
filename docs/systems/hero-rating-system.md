# Hero Rating System — Implementation Plan

Reference: [hero-system.md](hero-system.md), [combat-formulas-draft.md](combat-formulas-draft.md), [marketplace-system.md](marketplace-system.md)

Purpose: Define a unified, intrinsic hero value model with two metrics — cross-race **base OVR** and hero-specific **complex rating** — and plan its backend rollout. External influences (equipment, spells, team morale/chemistry, form/fatigue) are excluded by design.

**Status:** Implemented (Phases 0–5 complete). Run `php bin/console app:hero-ratings:refresh` after migration to backfill existing heroes.

---

## Summary

| Metric | Range | Purpose |
|--------|-------|---------|
| `base_ovr` | 0–100 (FIFA-style) | Fair comparison across all races |
| `complex_rating` | 0–9999 | Marketplace value, dismiss compensation, NPC pricing, hero/trainer salaries |

Both values are computed **on-demand** (no DB cache in the first iteration). API exposes **two integers only** — no breakdown payload yet.

Gold amounts across the economy should derive from `complex_rating` (recalibrated constants), replacing ad-hoc formulas today in `HeroDismissalService` and `NpcSimulationService`.

---

## Locked Design Decisions

These decisions are final for v1 unless future simulations prove a follow-up change is needed.

### Included inputs

| Input | `base_ovr` | `complex_rating` |
|-------|------------|------------------|
| 8 primary attributes (STR–LCK, including CHA) | yes | yes |
| Level | yes | yes |
| Race | forced **Human** in combat formulas (no racial combat passives) | actual race + combat passives |
| Trait | no | yes — additive bonus (combat and economic traits) |
| Weapon / school mastery | no | yes — additive bonus (not tied to equipped loadout) |
| Attunement | no | no |
| Spells / spell loadout | no | no (treated like equipment) |
| Magic capacity | no | no (treated like equipment) |
| Age | no | yes — market-value multiplier |
| Equipment | no | no |
| Form / fatigue / morale | no | no |
| Team chemistry / morale | no | no |

### Human-neutral base OVR

For `base_ovr`, race is **hard-coded to Human** in derived-stat formulas. Primary attribute values are read from the hero entity as stored (no race-bonus subtraction in v1). If simulations show Orc/Giant skew, stat normalization can be added as a follow-up.

### Hybrid heroes (phys + magic)

Compute two weighted scores from the same derived combat stats:

1. **Physical variant** — HP, physical attack, armor, accuracy, dodge, crit, etc.
2. **Magic variant** — spell power, magic resistance, etc.

Final score for both metrics: **`average(physical_variant, magic_variant)`**, then scale (`base_ovr` → 0–100, `complex_rating` → 0–9999).

### Trainers

Same calculator and value object; role-specific rules:

| Aspect | Combatant | Trainer |
|--------|-----------|---------|
| `base_ovr` | standard | standard (frozen stats are the main quality signal) |
| Combat hybrid base in `complex_rating` | full | full |
| Mastery bonus | full | **0** (trainers do not fight; combat mastery is irrelevant) |
| Trait bonus | full | full (e.g. Quick Learner / Slacker matter for trainer value) |
| Age multiplier | yes | yes |
| Marketplace / salary | `complex_rating × hero factors` | `complex_rating × trainer factors` (separate YAML multipliers, not a duplicate rating) |

### Economy

- **`gold_per_complex_point`** (config) drives dismiss compensation, suggested marketplace price, NPC buy/sell heuristics, and hero/trainer salaries.
- Dismiss keeps **40% compensation ratio**; only the underlying estimated value changes.
- Numeric calibration is **deferred** until simulation runs; YAML holds placeholder weights.

---

## Architecture

### New components

```
config/game/hero-rating.yaml
src/Config/HeroRatingConfig.php
src/ValueObject/Hero/HeroRating.php
src/Service/Hero/HeroRatingCalculator.php
```

### Refactor existing combat layer

`CombatStatCalculator` gains profile-aware entry points without changing current equipped behaviour:

```
CombatStatProfile (enum or VO)
├── Equipped          ← existing calculate(Hero) behaviour (unchanged)
├── HumanNeutral      ← base_ovr: race = Human, no trait, no items, no mastery, form = 100
└── FullIntrinsic     ← complex combat base: actual race, no trait/items/mastery, form = 100
```

Extract shared formula methods so profiles differ only in **inputs**, not duplicated math.

### Mastery bonus (complex only)

- Source: all `WeaponMastery` and `SchoolMastery` rows on the hero.
- Use **tier** (and optionally XP) — **ignore attunement** and **ignore equipped gear linkage**.
- Aggregate into a single additive bonus (exact formula in YAML).

### Trait bonus (complex only)

- Map each `HeroTrait` case to an additive bonus or small multiplier contribution.
- Include non-combat traits (Quick Learner, Audience Favorite, etc.).

### Age multiplier (complex only)

- Apply after combat base + trait + mastery bonuses.
- Phase buckets from `RaceConfig::resolveAgePhase()` (junior / prime / veteran / elder).
- Coefficients in YAML (e.g. junior > prime > veteran > elder).

### Rating formula (conceptual)

```
derived_stats = CombatStatCalculator::calculateForProfile(hero, profile)

phys_score  = weighted_sum(derived_stats, physical_weights)
magic_score = weighted_sum(derived_stats, magic_weights)
hybrid      = (phys_score + magic_score) / 2

base_ovr = scale_to_100(hybrid)                                    // HumanNeutral profile

complex_base = scale_to_9999(hybrid)                               // FullIntrinsic profile
complex_rating = clamp(
    round((complex_base + trait_bonus + mastery_bonus) × age_multiplier),
    0, 9999
)
```

CHA participates in aggregation via dedicated weights in both phys/magic paths (social contribution).

---

## Configuration (`config/game/hero-rating.yaml`)

Placeholder structure — tune after simulations:

```yaml
physical_weights:
  max_hp: 0.12
  physical_attack: 0.20
  armor_value: 0.15
  # accuracy_percent, dodge_percent, crit_percent, base_initiative, cha: ...

magic_weights:
  spell_power: 0.25
  magic_resistance: 0.20
  # wil-derived contribution, cha: ...

scale:
  base_ovr_max: 100
  complex_rating_max: 9999
  # reference ceilings for normalizing raw hybrid score before scaling

mastery:
  points_per_tier: 25          # per weapon/school mastery tier above 1

trait:
  quick_learner: 120
  slacker: -80
  # ... one entry per HeroTrait case

age_multiplier:
  junior: 1.05
  prime: 1.00
  veteran: 0.98
  elder: 0.95

economy:
  gold_per_complex_point: 1.0   # placeholder — calibrate via simulation
  dismiss_compensation_ratio: 0.4
  hero_salary_factor: 1.0
  trainer_salary_factor: 1.2
  trainer_market_multiplier: 1.5
```

Load via `HeroRatingConfig` service (same pattern as `RaceConfig`).

---

## Implementation Phases

### Phase 0 — Documentation & config scaffold

**Goal:** Lock spec in repo; no runtime behaviour yet.

- [x] Finalize this document after review.
- [x] Add `config/game/hero-rating.yaml` with placeholder weights and economy constants.
- [x] Add `HeroRatingConfig` service + Symfony service binding.
- [x] Cross-link from [hero-system.md](hero-system.md) § Attribute calculations.
- [x] Note planned status in [docs/README.md](../README.md) implementation table.

**Deliverable:** Config loads; no API changes.

---

### Phase 1 — Core calculator

**Goal:** Pure domain logic with unit tests; no API or economy wiring.

- [x] Create `HeroRating` value object (`getBaseOvr(): int`, `getComplexRating(): int`).
- [x] Refactor `CombatStatCalculator`:
  - [x] Extract internal `computeDerivedStats(...)` from item/race/trait/mastery inputs.
  - [x] Add `calculateForProfile(Hero $hero, CombatStatProfile $profile): DerivedCombatStats`.
  - [x] Keep public `calculate(Hero $hero)` delegating to `Equipped` profile — **zero behaviour change** for existing callers.
- [x] Implement `HeroRatingCalculator::calculate(Hero $hero): HeroRating`:
  - [x] HumanNeutral → `base_ovr`.
  - [x] FullIntrinsic hybrid base + trait + mastery (role-aware) + age → `complex_rating`.
  - [x] Trainer: mastery bonus = 0.
- [x] Unit tests (`tests/Service/Hero/HeroRatingCalculatorTest.php`):
  - [x] Equipping items does not change either rating.
  - [x] Same stats, different races → same `base_ovr`, different `complex_rating`.
  - [x] Trait increases `complex_rating` only.
  - [x] Mastery increases `complex_rating` only (combatant).
  - [x] Trainer with masteries → mastery bonus still 0.
  - [x] Level increase raises both metrics.
  - [x] Age phase changes `complex_rating` only.
  - [x] Monotonicity: +1 STR after training does not lower ratings.

**Deliverable:** Green unit tests; calculator callable from DI.

---

### Phase 2 — API exposure

**Goal:** Clients can read ratings on hero endpoints.

- [x] Inject `HeroRatingCalculator` into `HeroService`.
- [x] Extend `HeroService::serialize()`:

  ```php
  'trait' => $hero->getTrait()?->value,
  'ratings' => [
      'base_ovr' => $rating->getBaseOvr(),
      'complex_rating' => $rating->getComplexRating(),
  ],
  ```

- [x] Extend marketplace listing payload in `MarketplaceService` (hero + trainer listings; hero includes `trait`).
- [x] Optional Twig helper `hero_rating(Hero $hero): HeroRating` in `GameExtension` for templates.
- [x] Twig helper `hero_trait_js_labels()` for Stimulus trait badges (summoning, marketplace).
- [x] Player-facing trait badges — see [hero-system.md](hero-system.md) § Player UI.
- [x] Update [route-map.md](../route-map.md) response shape notes for `GET /api/v1/heroes` and hero detail.

**Deliverable:** JSON includes `trait` and rating fields; trait visible in roster, detail, summoning reveal, and marketplace UI.

---

### Phase 3 — Economy integration

**Goal:** Replace legacy value estimates with rating-based gold.

- [x] `HeroDismissalService::estimateHeroValue()` → `complex_rating × gold_per_complex_point`.
- [x] `TrainerDismissalService` — same pattern.
- [x] `NpcSimulationService::calculateHeroMarketPrice()` → rating-based suggested price + `trainer_market_multiplier`.
- [x] `HeroSalaryService` (weekly salary from complex rating).
- [x] `TeamPayrollService` — weekly payroll tick, ledger entries, unpaid debt on insolvency.
- [x] Update [team-system.md](team-system.md) dismiss compensation description.

**Deliverable:** Dismiss, NPC market, and salary paths use one config source.

---

### Phase 4 — UI

- [x] `hero_rating_badge` Twig component (roster, detail, marketplace card).
- [x] `ratings_panel` on hero detail overview.
- [x] i18n: `heroes.rating.*` keys (EN + CS).
- [x] Marketplace filter/sort by `complex_rating` / `base_ovr`.
- [x] Twig helpers `hero_rating()`, `hero_gold_value()`.

**Deliverable:** Visible OVR on roster/detail; complex rating on marketplace.

---

### Phase 5 — Cache & memorial

- [x] DB columns `base_ovr`, `complex_rating` + indexes.
- [x] `HeroRatingCacheSubscriber` (Doctrine onFlush).
- [x] `app:hero-ratings:refresh` backfill command.
- [x] Graveyard memorial snapshot includes ratings.
- [ ] API breakdown payload (deferred).
- [ ] Stat normalization follow-up (deferred until simulation).
- [ ] Simulation-driven YAML calibration (deferred).

---

## Files to Create

| Path | Purpose |
|------|---------|
| `config/game/hero-rating.yaml` | Weights, bonuses, economy constants |
| `src/Config/HeroRatingConfig.php` | YAML loader |
| `src/ValueObject/Hero/HeroRating.php` | Result VO |
| `src/Enum/CombatStatProfile.php` | Profile enum (if not inline VO) |
| `src/Service/Hero/HeroRatingCalculator.php` | Main calculator |
| `tests/Service/Hero/HeroRatingCalculatorTest.php` | Unit tests |

## Files to Modify

| Path | Change |
|------|--------|
| `src/Service/Combat/CombatStatCalculator.php` | Profile-based calculation; extract shared formulas |
| `src/Service/Hero/HeroService.php` | Serialize ratings |
| `src/Service/Marketplace/MarketplaceService.php` | Listing payload |
| `src/Service/Hero/HeroDismissalService.php` | Rating-based estimate |
| `src/Service/Training/TrainerDismissalService.php` | Rating-based estimate (if applicable) |
| `src/Service/Team/NpcSimulationService.php` | Rating-based market price |
| `docs/systems/hero-system.md` | Link to this doc |
| `docs/README.md` | Implementation status row |

---

## Acceptance Criteria

1. `GET /api/v1/heroes` and hero detail return `ratings.base_ovr` and `ratings.complex_rating`.
2. Equipping items does not change either rating.
3. Dismiss compensation uses rating-based gold × 40%.
4. NPC marketplace pricing uses `complex_rating`.
5. UI shows OVR on roster cards, both ratings on detail and marketplace listings.
6. Marketplace supports filter/sort by cached `complex_rating` / `base_ovr`.
7. Run `php bin/console doctrine:migrations:migrate` then `app:hero-ratings:refresh` after deploy.

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Formula duplication vs `CombatStatCalculator` | Single calculator with profiles; shared private methods |
| Cross-race OVR skew without stat normalization | Document follow-up; v1 uses Human profile only |
| Economy shock after recalibration | Placeholder `gold_per_complex_point`; tune in simulation before production |
| Marketplace sort without DB cache | On-demand OK for v1; Phase 5 cache if perf issues |
| Hybrid averaging undervalues specialists | Weights adjustable in YAML; simulation validates |

---

## Related Code

- `HeroRatingCalculator`, `HeroSalaryService`, `HeroRatingCacheSubscriber`
- `config/game/hero-rating.yaml` — weights and economy constants
- `php bin/console app:hero-ratings:refresh` — backfill cached columns
