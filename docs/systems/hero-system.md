# Hero System

Reference: [product/overview.md](../product/overview.md#24-hero-system)

Purpose: Document hero lifecycle, attributes, aging, relationships, and persistence.

Sections to fill:
- Hero data model
- Attribute calculations and derived stats
- Age, mortality, and death mechanics
- Relationship matrix and effects
- Equipment and magic capacity integration
- API endpoints and DTOs
- Tests and edge cases
- Implementation notes

Summary:
- Heroes are defined by race, age, primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) in the **1–20** range, and secondary stats (form, fatigue, morale).
- No rarity or class: value derives from training and equipment.

See [hero-rating-system.md](hero-rating-system.md) for **base OVR** (0–100, cross-race) and **complex rating** (0–9999, intrinsic hero value for economy).

## Attribute calculations and derived stats

### Hero Generation & Stat Capping
When a hero is generated (either during starting roster initialization or when summoned from the Summoning Chamber), their primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) are rolled using the following rules:

**Inputs:** `HeroGenerator::createForTeam()` receives a `$chamberBonuses` array with the following keys:
- `summon_base_stat_bonus` (float) — added to the base stat value (default fallback: `BASE_STAT_VALUE = 1`)
- `summon_stat_random_bonus` (float) — added to the random roll ceiling (default fallback: `STAT_RANDOM_MAX = 2`)
- `summon_stat_total_cap` (float) — reserved for future total-cap overrides

For NPC teams (kingdom initialization), `KingdomInitializationService` extracts Summoning Chamber passive bonuses from the new HQ via `extractChamberBonuses()` and passes them into `HeroGenerator::createForTeam()` (same keys as player summons). An empty array is only used if no chamber facility is present (then built-in fallback constants apply).

1. **Base Value & Random Roll:**
   - For each attribute, the generator computes:
     - `effectiveBase` = `BASE_STAT_VALUE (1)` + round(`summon_base_stat_bonus`)
     - `effectiveRandomMax` = `STAT_RANDOM_MAX (2)` + round(`summon_stat_random_bonus`)
   - Each attribute is rolled three times, and one of the three rolls is randomly chosen to increase variance:
     - `roll = random_int(0, effectiveRandomMax)`
     - `base_stat = effectiveBase + raceBonus + roll`

2. **Single-Stat Cap:**
   - A dynamic ceiling is applied to each individual attribute to prevent excessively high starting stats:
     - `level = (int) summon_stat_random_bonus` (integer truncation; fallback 1 when the key is missing)
     - `reduction = 2 + round((level - 1) × 0.11)`
     - `naturalMax = effectiveBase + effectiveRandomMax + raceBonus`
     - `maxStatLimit = max(1, naturalMax - reduction)`
     - `final_stat = min(maxStatLimit, base_stat)`
   - *Scaling targets (max race bonus = +3):*
     - Chamber level 1: max single stat **5** (with +3 race), total cap 12–14
     - Chamber level 5: max single stat **11**, total cap 36–43
     - Chamber level 10: max single stat **17**, total cap 68–72
   - *NPC teams (chamber level 1 bonuses from kingdom init):* roughly **1–3** per attribute before race bonuses (same chamber formula as player summons at level 1).

3. **Dynamic Overall Cap (maxTotal):**
   - The total sum of all 8 attributes is dynamically limited to **60% of the sum of the individual max limits** for the selected race, with a safety floor of 8:
     - `maxTotal = max(8, round(sum(maxStatLimit) × 0.6))`
   - `HeroGenerator` hardcodes the 0.6 factor; `summon_stat_total_cap` from chamber bonuses is reserved and not read.
   - This ensures that heroes are specialized: they can have one or two very high stats, but the other stats will be reduced to fit the budget.

4. **Organic Random Trimming:**
   - If the sum of the rolled stats exceeds maxTotal, the excess points are trimmed step-by-step.
   - In each step, an eligible stat (whose value is currently greater than its minimum limit) is decremented by 1 at random.
   - The minimum floor for each stat is protected using the race bonus:
     - `minLimit = max(1, raceBonus)`
   - This prevents a hero's primary racial strengths from being wiped out during trimming (e.g., an Orc's Strength will never drop below 3).

Starting roster:
- Every new team receives **10 heroes** at creation (6 match lineup + 4 reserves). See [team-system.md](team-system.md#starting-roster).
- Each hero starts at **level 1** with age random within **[Min Age, Max Junior Age]** for their race.

### Hero level vs match XP

League and friendly matches add to `Hero.xp` (Library facility `xp_gain_pct` multiplies that XP only). `HeroService::levelUp()` and chronicle event `levelup` exist but **no production path** converts accumulated XP into `Hero.level`. Displayed level stays at generation until an XP curve is designed. Tracked in [product/known-issues.md](../product/known-issues.md) #6.

## Age Milestones & Terminology

Race age data lives in `config/game/races.yaml` under each race's `age` block.

| Milestone (player-facing) | Config key | Meaning |
|---------------------------|------------|---------|
| Minimum Age | `min` | Lowest recruitment age |
| Max Junior Age | `max_junior` | Upper bound for junior phase |
| Prime Age Limit | `prime_limit` | Last age with full training efficiency |
| **Mortality Threshold** | `mortality_threshold` | Age from which permanent death risk escalates after combat deaths |

**Implementation:** `RaceConfig::getMortalityThreshold()`, `resolveAgePhase()`, `isAtOrAboveMortalityThreshold()`. Twig helpers: `hero_age_phase()`, `hero_mortality_threshold()`, `hero_at_mortality_threshold()`.

Mechanics & APIs:
- Hero generation: Summoning Chamber produces junior heroes with randomized base stats within race ranges.
- GET /api/heroes — list for a player; GET /api/heroes/{id} — detail; POST/PUT for updates (rename, equipment changes)

Edge cases:
- Ensure death is final; move to Graveyard with immutable record
- Aging and mortality processing must be idempotent per tick

## Weapon & Magic School Mastery

To support distinct playstyles, heroes can gain experience and level up their mastery in specific weapon types and magic schools.

### 1. Weapon & Gear Types
Masteries and attunement are tracked individually for the following 13 types of equipment:
- **Swords:** `one_handed_sword`, `two_handed_sword`
- **Axes:** `one_handed_axe`, `two_handed_axe`
- **Maces:** `one_handed_mace`, `two_handed_mace`
- **Daggers:** `dagger`
- **Ranged:** `bow`, `crossbow`
- **Magical:** `wand`, `staff`
- **Off-hand:** `shield`, `spell_accelerator`

### 2. Magic Schools
Spell-casting heroes gain mastery in the 6 elemental magic schools:
- `fire`, `water`, `air`, `earth`, `light`, `dark`

### 3. Leveling Curve (T1 to T5)
Mastery tiers range from **Tier 1** to **Tier 5 (Max)**. Leveling up requires reaching the following total XP thresholds:
- **Tier 1:** 0 – 99 XP
- **Tier 2:** 100 – 299 XP
- **Tier 3:** 300 – 599 XP
- **Tier 4:** 600 – 999 XP
- **Tier 5 (Max):** 1000 XP

### 4. Attunement & Decay Mechanics

Source of truth: `HeroMasteryService`. To gain weapon-mastery XP efficiently, heroes must raise attunement on equipped gear styles.

- **Combat (League Match)** — `processMatchParticipation`:
  - Equipped gear types: **+50** attunement progress (capped at 100), then weapon XP = `round(BASE_MATCH_XP × attunement / 100)` with `BASE_MATCH_XP = 30` (uses attunement **after** the +50).
  - Equipped spell schools: **+15 XP** fixed (no attunement on schools).
- **Weekly Magic training** — **not implemented** for mastery/attunement. `TrainingType::Magic` only raises the hero’s **magic capacity** (and fatigue); it does not call `HeroMasteryService`.
- **Daily Decay** — `processDailyDecayTick` (Daily Reset):
  - **Unused gear types:** lose **20 absolute attunement points** (not 20%), then XP decay = `round(20 × (1 − attunement / 100))` using attunement **after** that decay (`BASE_DECAY_XP = 20`). At 100% attunement, unused styles still lose 20 attunement but **0 XP** that day.
  - **Unused magic schools:** lose **10 XP** per day (fixed; no attunement).
  - If XP drops below the threshold for the current tier, the hero levels down in that mastery (down to a minimum of Tier 1).
  - Swapping gear does *not* instantly reset attunement. Inactive gear styles decay gradually day-by-day.

## Hero Traits

Enum: `App\Enum\HeroTrait` — authoritative source of all modifiers.

### Assignment

- Each hero has a **60% chance** of receiving a trait when generated (`HeroGenerator::createForTeam()`).
- The trait is assigned randomly with equal probability across all 14 cases.
- Trait is **immutable** after assignment — it cannot be trained or changed. It defines the hero's personality permanently.
- Heroes without a trait (`trait = null`) are fully functional — no modifiers applied.

### Trait Categories

#### Positive (pure upside)

| Trait | Key | Effect |
|-------|-----|--------|
| Quick Learner | `quick_learner` | +20% attribute training speed |
| Clutch | `clutch` | When HP ≤ 30%: +15% accuracy, armor ×1.10 |
| Audience Favorite | `audience_favorite` | +5% arena ticket revenue when fielded |
| Battle Hardened | `battle_hardened` | Morale decay on ally death ×0.5 (slower) |

#### Negative (pure downside)

| Trait | Key | Effect |
|-------|-----|--------|
| Volatile | `volatile` | Morale decay on ally death ×2.0 (faster) |
| Slacker | `slacker` | -15% attribute training speed |
| Fragile | `fragile` | -10% max HP |
| Glass Jaw | `glass_jaw` | When HP ≤ 50%: incoming physical damage ×1.10 |

#### Mixed / Tradeoff

| Trait | Key | Positive | Negative |
|-------|-----|----------|----------|
| Berserker | `berserker` | Crit +15%, crit damage 2.0× | Accuracy -8% |
| Glasscannon | `glass_cannon` | Spell power +15% | Armor -10% |
| Reckless | `reckless` | Crit +15% | Dodge -10% |
| Loner | `loner` | Ignores negative race synergy | Ignores positive race synergy |
| Overconfident | `overconfident` | Physical attack +10% | Accuracy -8% |
| Perfectionist | `perfectionist` | Consistent (non-random) damage | Training speed -10% |

### Player UI

Traits are **visible to players** wherever hero identity is shown. Heroes with `trait = null` show no badge (trait is an exceptional property, not guaranteed).

| Surface | Location | Component / file |
|---------|----------|------------------|
| Hero roster | Card under level/race line | `templates/components/hero/trait_badge.html.twig` (compact) in `card.html.twig` |
| Hero detail — header | Meta row next to ratings | Same badge in `header_card.html.twig` |
| Hero detail — overview | Trait badge with name (description via tooltip / i18n) | `trait_badge.html.twig` (overview tab) |
| Summoning reveal | Below level on recruit card | `summoning_controller.js` + `portal_chamber.html.twig` |
| Marketplace browse | Listing detail row | `marketplace_controller.js` + `js_templates.html.twig` |
| Marketplace sell | Sell picker card | `trait_badge.html.twig` in `sell_tab.html.twig` |

**Badge styling** (`assets/styles/components/_hero.scss`):

- `.hero-trait-badge--positive` — green (Quick Learner, Clutch, Audience Favorite, Battle Hardened)
- `.hero-trait-badge--negative` — red (Volatile, Slacker, Fragile, Glass Jaw)
- `.hero-trait-badge--mixed` — amber (Berserker, Glass Cannon, Reckless, Loner, Overconfident, Perfectionist)

Category comes from `HeroTrait::getCategory()`; icon from `HeroTrait::getIcon()`. Tooltip and panel body use the translated description key.

**i18n keys:** `heroes.trait_label`, `heroes.trait_panel_title`, `heroes.trait_panel_desc`, and per trait `heroes.traits.{key}.name` / `heroes.traits.{key}.desc` in `translations/messages.{en,cs}.yaml`.

**Stimulus / API labels:** Twig helper `hero_trait_js_labels()` in `GameExtension` builds translated name/desc/category/icon maps for `summoning_controller.js` and `marketplace_controller.js` (`data-*-traits-value`).

**API field:** `trait` — nullable string (enum value, e.g. `"berserker"`) on `HeroService::serialize()` and hero entities inside marketplace listings (`MarketplaceService::serializeListing()`).

### Combat Engine Integration

`DerivedCombatStats` carries trait metadata for the combat engine. Full formulas: [combat-system.md](combat-system.md).

- Immediate modifiers (HP, attack, accuracy, crit, dodge, spell power, armor) are applied in `CombatStatCalculator`.
- Situational modifiers (clutch threshold, glass jaw threshold, consistent damage, morale decay, race synergy flag) are passed as metadata and applied by the combat engine at runtime.
- Arena revenue bonus is consumed by `ArenaRevenueService` (not the combat engine).

Per-hero match history: [hero-chronicle-system.md](hero-chronicle-system.md). League fixtures resolve through wave Messenger + `CombatEngine` (see [combat-system.md](combat-system.md)).
