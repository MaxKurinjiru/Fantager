# Hero System

Reference: [product/overview.md](../product/overview.md#24-hero-system)

Status: Live (core) — match XP levels heroes ([#6](../product/known-issues.md) Resolved / A7.1)  
Screens: [03-hero-roster.md](../screens/03-hero-roster.md), [04-hero-detail.md](../screens/04-hero-detail.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none for combat traits. **#9 decided** (A7.5 Cut) — no in-combat synergy; Loner copy is flavor. **#6 decided** (A7.1 Implement) — [locked hero level invariant](#locked-hero-level-invariant). **#7 decided** (A7.2 flavor) — chemistry is flavor for matches (live for dashboard, fan club / attendance, league tie-break; combat does not read it). Lock: [team-system.md](team-system.md#locked-chemistry-flavor). **#13 decided** (A7.7 Cut) — roster extras parked; live card lock on [03-hero-roster.md](../screens/03-hero-roster.md#locked-roster-extras). **#14 decided** (A7.9 Implement) — [summoning-system.md](summoning-system.md#locked-genie-summon-pool).

Purpose: Document hero lifecycle, attributes, aging, mortality, relationships, and persistence.

## Remaining work

- [x] Generation, roster/detail APIs, traits, mastery/attunement, dismiss → graveyard
- [x] Match XP writes `Hero.xp` (Library `xp_gain_pct` applies)
- [x] Production `HeroService::levelUp()` via `HeroProgressionService` after league/friendly XP (A7.1 / #6)
- [x] In-combat race synergy / Loner combat hook Cut (A7.5 / #9) — `ignoresRaceSynergy` unused; copy is flavor
- [x] Roster extras Cut (A7.7 / #13) — live card contract in [03-hero-roster.md](../screens/03-hero-roster.md#locked-roster-extras)

---

## Summary

- Heroes are defined by race, age, primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) in the **1–20** range, and secondary stats (form, fatigue, morale).
- No rarity or class: value derives from training and equipment.
- See [hero-rating-system.md](hero-rating-system.md) for **base OVR** (0–100, cross-race) and **complex rating** (0–9999, intrinsic hero value for economy).

---

## Data model

Canonical columns: [entities.md](../architecture/entities.md) § Hero Domain.

| Entity | Table | Role |
|--------|-------|------|
| **Hero** | `hero` | Combatant or trainer; attributes, XP, ratings, trait, status |
| **WeaponMastery** | `weapon_mastery` | Per-style tier, XP, attunement |
| **SchoolMastery** | `school_mastery` | Per-school tier, XP |
| **HeroSpell** | `hero_spell` | Learned spells and equipped slots |
| **HeroChronicle** | `hero_chronicle` | Per-hero event log — [hero-chronicle-system.md](hero-chronicle-system.md) |
| **HeroTrainingHistory** | `hero_training_history` | Weekly training results — [training-system.md](training-system.md) |

**Hero status (`HeroStatus`):** live writers use `available`, `selling`, `dead`. `in_match` and `recovering` have no production setter. `retired` is reserved (`isReserved()`; exclusion filters treat it like dead — A6.2).

**Role:** `combatant` or `trainer`. Trainers are the same entity; they do not count toward Barracks roster cap.

---

## Age, mortality, and death

Race age data lives in `config/game/races.yaml` under each race's `age` block. Displayed age is `floor(age_raw / 10)` (`Hero::getAge()`).

| Milestone (player-facing) | Config key | Meaning |
|---------------------------|------------|---------|
| Minimum Age | `min` | Lowest recruitment age |
| Max Junior Age | `max_junior` | Upper bound for junior phase |
| Prime Age Limit | `prime_limit` | Last age with full training efficiency |
| **Mortality Threshold** | `mortality_threshold` | Age from which permanent death risk escalates after combat KOs |

**Implementation:** `RaceConfig::getMortalityThreshold()`, `resolveAgePhase()`, `isAtOrAboveMortalityThreshold()`. Twig helpers: `hero_age_phase()`, `hero_mortality_threshold()`, `hero_at_mortality_threshold()`.

**Daily aging** (Daily Reset tick): non-Undead heroes gain `round(1 × game_speed)` on `age` raw. At speed `1.00` that is **0.1 displayed years per day**. Undead do not age.

**Combat KO aging** (league and friendly resolution): each KO in a match adds escalating full years (`sum 1..D` KOs → years × 10 on raw). **Permanent death** only for elders at/above the mortality threshold: chance `5% + 4%` per year above the threshold, capped at 100%. A KO is not always death ([known-issues.md](../product/known-issues.md) #1 / #9). Permanent deaths write a graveyard memorial and remove the live hero — [graveyard-system.md](graveyard-system.md).

**Dismiss** is a player action (`POST /api/v1/heroes/{id}/dismiss`), not combat: 40% compensation, memorial `dismissed`, entity deleted. Team must keep ≥ 6 available combatants. See [team-system.md](team-system.md#hero-dismissal).

There is no live “death of old age” tick. `MemorialCause::{Age, Retired, Death}`, `ChronicleEventType::HeroRetired`, `HeroChronicleEventType::Retired`, and `HeroStatus::Retired` are **reserved / unused (A6.2)** — no production writers.

---

## Relationship matrix

Race affinities live in `config/game/race_relations.yaml` (0–100). They are **not** stored on the hero.

Live uses of the matrix (not a per-hero relationship graph):

- **Summoning** compatible-race pool (relationship ≥ 50; Genie adaptation ≥ 80 → Genie / Human / Elf) — [summoning-system.md](summoning-system.md#locked-genie-summon-pool)
- **Team chemistry** (combatants only; flavor for matches — [#7](../product/known-issues.md) Resolved) — [team-system.md](team-system.md#locked-chemistry-flavor)
- Arena adaptation and weekly training group modifiers — [headquarters-system.md](headquarters-system.md), [training-system.md](training-system.md)

In-combat race-synergy **tables** are **Cut** ([#9](../product/known-issues.md) Resolved / A7.5). Loner’s `ignoresRaceSynergy` is serialized but unused.

---

## Equipment and magic capacity

- **Items:** nine slots on the hero (`main_hand` … `ring` + `ring_2`). Equip/unequip is `PUT /api/v1/heroes/{heroId}/equipment`. Occupancy locked [#8](../product/known-issues.md). Full rules: [item-system.md](item-system.md#locked-item-occupancy).
- **Magic capacity:** integer on `Hero` (default **0** at generation). Weekly Magic training raises it (max **5**). Equipped spell count cannot exceed capacity. Gold is not charged for extra slots (`spell_slot_cost` reserved / unused, A6.2). See [spell-system.md](spell-system.md) and [training-system.md](training-system.md).

---

## Attribute calculations and derived stats

### Hero Generation & Stat Capping
When a hero is generated (either during starting roster initialization or when summoned from the Summoning Chamber), their primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) are rolled using the following rules:

**Inputs:** `HeroGenerator::createForTeam()` receives a `$chamberBonuses` array with the following keys:
- `summon_base_stat_bonus` (float) — added to the base stat value (default fallback: `BASE_STAT_VALUE = 1`)
- `summon_stat_random_bonus` (float) — added to the random roll ceiling (default fallback: `STAT_RANDOM_MAX = 2`)
- `summon_stat_total_cap` (float) — reserved / unread (A6.5); not applied and not shown on HQ cards or API `passive_bonuses`

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
   - `HeroGenerator` hardcodes the 0.6 factor; `summon_stat_total_cap` from chamber bonuses is reserved and not read (A6.5). HQ/API omit it from displayed bonuses.
   - This ensures that heroes are specialized: they can have one or two very high stats, but the other stats will be reduced to fit the budget.

4. **Organic Random Trimming:**
   - If the sum of the rolled stats exceeds maxTotal, the excess points are trimmed step-by-step.
   - In each step, an eligible stat (whose value is currently greater than its minimum limit) is decremented by 1 at random.
   - The minimum floor for each stat is protected using the race bonus:
     - `minLimit = max(1, raceBonus)`
   - This prevents a hero's primary racial strengths from being wiped out during trimming (e.g., an Orc's Strength will never drop below 3).

Starting roster:
- Every new team receives **10 heroes** at creation (6 match lineup + 4 reserves). See [team-system.md](team-system.md#starting-roster).
- Races are drawn from the same compatible pool as summoning for the team's assigned race (Genie → Genie / Human / Elf). See [summoning-system.md](summoning-system.md#locked-genie-summon-pool).
- Each hero starts at **level 1** with age random within **[Min Age, Max Junior Age]** for their race.

### Locked: hero level invariant

**Future work must keep this.** It is not optional polish.

| Rule | Meaning |
|------|---------|
| Lifetime XP | `Hero.xp` is a career total. Award paths only add. Level-up **never** subtracts XP. |
| Stored level is live truth | Combat, rating (via HP), UI, API, marketplace filters, and chronicles **read `Hero.level`**. Do not compute level from XP on read, in Twig, or in the combat snapshot builder. |
| Keep the column current | After match XP is written, apply the **current** curve and cap and update stored level if it should change (multi-level in one match is allowed). `levelUp()` records chronicle only. |
| Formula / cap change | Do **not** lazy-recalc on the next page load. Run a **migration or job** that rewrites `Hero.level` from stored XP (may go up or down). Until that job finishes, the stored value remains the correct live value. |
| First enable (A7.1) | Turning the curve on is a formula change: backfill stored levels from existing XP in the same slice, do not wait for each hero’s next match. |

Package **B** (locked with #6, 16 Oct 2026):

- Level is career identity. Combat HP already uses `level * 30 + KON * 12 * raceFactor`. Level-up does **not** grant attribute points and does **not** raise the training cap **1–20**.
- Library `xp_gain_pct` stays (`1 + pct / 100` on match XP).
- `Kingdom.xp_modifier` multiplies XP **at award time** (already baked into stored XP; changing the modifier does not relevel anyone until a job, and should not).
- `Kingdom.level_cap` live target **20**. XP may keep growing past the cap so a later cap raise can unlock levels via job.
- Cumulative cost to leave level `L` (need this much **more** total XP than `C(L)`): `400 × (L + 1)`. So 1→2 needs **800** total XP, 2→3 needs **2000** total, 3→4 needs **3600**. General minimum total XP to *be* level `n` (`n >= 2`): `C(n) = 400 × (n × (n + 1) / 2 − 1)`. `C(1) = 0`. `C(20) = 83600`.
- XP bar: progress inside the current stored level, using `C(level)` and `C(level + 1)` (`hero_xp_bar()` / API `xp_for_level`, `xp_for_next_level`).

Live writers: `HeroProgressionService::applyMatchXp()` (league and friendly resolution) raises stored level and writes chronicle `levelup` / `hero_levelup`. Formula or `level_cap` changes: `php bin/console app:hero-levels:refresh` (no chronicle). Curve YAML: `config/game/hero-progression.yaml`.

### Hero level vs match XP

League and friendly matches add to `Hero.xp` (Library `xp_gain_pct` and `Kingdom.xp_modifier` multiply at award). After the write, `HeroProgressionService` updates stored `Hero.level` from the current curve and cap. XP is never spent. Tracked in [product/known-issues.md](../product/known-issues.md) #6 (Resolved).

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
| Battle Hardened | `battle_hardened` | No live combat morale effect ([#9](../product/known-issues.md) Cut) |

#### Negative (pure downside)

| Trait | Key | Effect |
|-------|-----|--------|
| Volatile | `volatile` | No live combat morale effect ([#9](../product/known-issues.md) Cut). NPC lineup still treats it as a purely negative trait |
| Slacker | `slacker` | -15% attribute training speed |
| Fragile | `fragile` | -10% max HP |
| Glass Jaw | `glass_jaw` | When HP ≤ 50%: incoming physical damage ×1.10 |

#### Mixed / Tradeoff

| Trait | Key | Positive | Negative |
|-------|-----|----------|----------|
| Berserker | `berserker` | Crit +15%, crit damage 2.0× | Accuracy -8% |
| Glasscannon | `glass_cannon` | Spell power +15% | Armor -10% |
| Reckless | `reckless` | Crit +15% | Dodge -10% |
| Loner | `loner` | No live combat pairing effect (synergy tables Cut, [#9](../product/known-issues.md) Resolved) | Still counted in team chemistry |
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
- Situational modifiers (clutch threshold, glass jaw threshold, consistent damage) are passed as metadata and applied by the combat engine at runtime. Morale-decay flags and race-synergy flags are serialized but unused ([#9](../product/known-issues.md) Cut).
- Arena revenue bonus is consumed by `ArenaRevenueService` (not the combat engine).

Per-hero match history: [hero-chronicle-system.md](hero-chronicle-system.md). League fixtures resolve through wave Messenger + `CombatEngine` (see [combat-system.md](combat-system.md)).

---

## APIs

Combatant CHA also feeds team chemistry (flavor for matches — live for dashboard, fan club / attendance, league tie-break; combat does not read it — [#7](../product/known-issues.md) Resolved).

| Method | Path | Notes |
|--------|------|-------|
| GET | `/app/heroes` | Roster (server-rendered) |
| GET | `/app/heroes/{id}` | Detail (tabs: overview, equipment, spells) |
| GET | `/api/v1/heroes` | List; includes `trait`, `ratings.base_ovr`, `ratings.complex_rating` (`HeroService::serialize()`) |
| GET | `/api/v1/heroes/{id}` | Full detail; `level` is stored; `xp_for_level` / `xp_for_next_level` / `level_cap` from the curve |
| PUT | `/api/v1/heroes/{id}` | Rename |
| POST | `/api/v1/heroes/{id}/dismiss` | Dismiss → graveyard + compensation |
| PUT | `/api/v1/heroes/{heroId}/equipment` | Equip / unequip |

Canonical list: [architecture/routes.md](../architecture/routes.md). Spell learn/equip: [spell-system.md](spell-system.md).

---

## Implementation notes

| Piece | Location |
|-------|----------|
| Entity | `src/Entity/Hero/Hero.php` (+ mastery, spell, chronicle) |
| Generate | `App\Service\Hero\HeroGenerator` |
| CRUD / serialize | `App\Service\Hero\HeroService` |
| Dismiss | `HeroDismissalService` + `GraveyardService` |
| Mastery | `HeroMasteryService` |
| Ratings | `HeroRatingCalculator` — [hero-rating-system.md](hero-rating-system.md) |

**Tests:** `tests/Service/Hero/HeroMasteryServiceTest.php`, `HeroRatingCalculatorTest.php`, `HeroProgressionServiceTest.php`; trait enum `tests/Enum/HeroTraitTest.php`. Generator/level-up/dismiss coverage lives with summoning, graveyard, and league resolution tests. Daily aging is idempotent via `KingdomTickLog` (one daily-reset row per kingdom/team/scheduled time).
