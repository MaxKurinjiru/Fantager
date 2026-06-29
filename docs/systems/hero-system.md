# Hero System

Reference: [game-summary.md](../game-summary.md#24-hero-system)

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

For NPC teams (kingdom initialization), an empty array is passed — the built-in fallback constants are used.

1. **Base Value & Random Roll:**
   - For each attribute, the generator computes:
     - `effectiveBase` = `BASE_STAT_VALUE (1)` + round(`summon_base_stat_bonus`)
     - `effectiveRandomMax` = `STAT_RANDOM_MAX (2)` + round(`summon_stat_random_bonus`)
   - Each attribute is rolled three times, and one of the three rolls is randomly chosen to increase variance:
     $$\text{roll} = \text{random\_int}(0, \text{effectiveRandomMax})$$
     $$\text{base\_stat} = \text{effectiveBase} + \text{raceBonus} + \text{roll}$$

2. **Single-Stat Cap:**
   - A dynamic ceiling is applied to each individual attribute to prevent excessively high starting stats:
     $$\text{level} = \text{round}(\text{summon\_stat\_random\_bonus})$$
     $$\text{reduction} = 2 + \text{round}((\text{level} - 1) \times 0.11)$$
     $$\text{naturalMax} = \text{effectiveBase} + \text{effectiveRandomMax} + \text{raceBonus}$$
     $$\text{maxStatLimit} = \max(1, \text{naturalMax} - \text{reduction})$$
     $$\text{final\_stat} = \min(\text{maxStatLimit}, \text{base\_stat})$$
   - *Scaling targets (max race bonus = +3):*
     - Chamber level 1: max single stat **5** (with +3 race), total cap 12–14
     - Chamber level 5: max single stat **11**, total cap 36–43
     - Chamber level 10: max single stat **17**, total cap 68–72
   - *NPC teams (no chamber bonuses):* stats range **1–3** per attribute (before race bonuses).

3. **Dynamic Overall Cap ($maxTotal$):**
   - The total sum of all 8 attributes is dynamically limited to **60% of the sum of the individual max limits** for the selected race, with a safety floor of 8:
     $$maxTotal = \max(8, \text{round}(\sum \text{maxStatLimit} \times 0.6))$$
   - This ensures that heroes are specialized: they can have one or two very high stats, but the other stats will be reduced to fit the budget.

4. **Organic Random Trimming:**
   - If the sum of the rolled stats exceeds $maxTotal$, the excess points are trimmed step-by-step.
   - In each step, an eligible stat (whose value is currently greater than its minimum limit) is decremented by 1 at random.
   - The minimum floor for each stat is protected using the race bonus:
     $$\text{minLimit} = \max(1, \text{raceBonus})$$
   - This prevents a hero's primary racial strengths from being wiped out during trimming (e.g., an Orc's Strength will never drop below 3).

Starting roster:
- Every new team receives **10 heroes** at creation (6 match lineup + 4 reserves). See [team-system.md](team-system.md#starting-roster).
- Each hero starts at **level 1** with age random within **[Min Age, Max Junior Age]** for their race.

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
To gain passive bonuses from their weapon masteries, heroes must become attuned/sžitý with their equipped gear.
- **Progress Gains:**
  - **Combat (League Match):** +50 attunement progress and +15 XP to equipped gear types; +15 XP to schools of equipped spells.
  - **Weekly Training (Magic):** +50 attunement progress to equipped gear types; +25 XP to schools of equipped spells.
- **Daily Decay (Daily Reset tick):**
  - **Unused gear types:** lose **20% attunement progress** and **10 XP** per day.
  - **Unused magic schools:** lose **10 XP** per day.
  - If XP drops below the threshold for the current tier, the hero levels down in that mastery (down to a minimum of Tier 1).
  - Swapping gear does *not* instantly reset attunement. Inactive gear styles decay gradually day-by-day, allowing players to adjust equipment briefly without losing all progress.

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
| Hero detail — overview | Sidebar panel with full description | `trait_panel.html.twig` (overview tab, before attributes) |
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

`DerivedCombatStats` carries trait metadata for the combat engine:
- Immediate modifiers (HP, attack, accuracy, crit, dodge, spell power, armor) are applied in `CombatStatCalculator`.
- Situational modifiers (clutch threshold, glass jaw threshold, consistent damage, morale decay, race synergy flag) are passed as metadata and applied by the combat engine at runtime.
- Arena revenue bonus is consumed by `ArenaRevenueService` (not the combat engine).

See `StubRandomMatchSimulator` for the full list of TODO hooks awaiting the real engine.
