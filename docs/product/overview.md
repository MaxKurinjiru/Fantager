# Game systems overview

> **Overview only — not the live contract.** On conflict, matching `docs/systems/*.md` and open rows in [known-issues.md](known-issues.md) win. Deferred design lives in [`docs/future/`](../future/), not in the core TOC below.

## Game Premise

Players take on the role of **arena managers** in a persistent fantasy world. They recruit heroes from 8 distinct races, train and equip them, assemble tactical formations, and lead their teams into asynchronous turn-based battles against other managers. Success depends on strategic team building, resource management, and competitive league performance — not the manager's own combat ability.

## Table of Contents

- [2.1 Kingdom System & Server Split](#21-kingdom-system--server-split)
- [2.2 Calendar & Server Ticks](#22-calendar--server-ticks)
- [2.3 Economy System](#23-economy-system)
- [2.4 Hero System](#24-hero-system)
- [2.5 Training System](#25-training-system)
- [2.6 Team System](#26-team-system)
- [2.6.1 Formation System](#261-formation-system)
- [2.7 Headquarters System](#27-headquarters-system)
- [2.8 Item System](#28-item-system)
- [2.9 Spell System](#29-spell-system)
- [2.10 Combat System](#210-combat-system)
- [2.11 Marketplace System](#211-marketplace-system)
- [2.12 League System](#212-league-system)
- [2.13 Graveyard System](#213-graveyard-system)
- [2.14 Community System](#214-community-system)

**Deferred (not core):** [Dungeons](../future/dungeon-system.md) · [Crafting](../future/crafting-system.md) · [Quests](../future/quest-system.md) · [World events](../future/world-events-system.md) — Milestone 8/9, parked. See [Deferred](#deferred-not-in-core).

## 2.1 Kingdom System & Server Split

Live rules: [kingdom-system.md](../systems/kingdom-system.md).

### Concept

A **Kingdom** is a shared game world in one application: rows are scoped by **`kingdom_id`**, not a separate server process. Players pick a Kingdom once at **registration**; gameplay, economy, and marketplace listings do not cross kingdoms.

> *Example: A player in "Kingdom of Ashenvale" never sees listings or league fixtures from "Kingdom of Ironforge."*

### Kingdom-Specific Global Settings

| Setting | Live? | Purpose |
|:---|:---|:---|
| **Main Language** | Yes | Primary interface language |
| **Time Zone** | Yes | Tick schedule in kingdom-local time |
| **Game Speed** | Yes | Scales some tick effects (aging, fan-club drift, …) |
| **Starting roster** | Yes | **10 heroes** per team at creation — [2.6](#26-team-system) |
| **Marketplace Tax Rate** | Yes | Fee on sales, routed to the Royal Treasury |
| **Season Length** | Yes | League season is **77 days** (11 weeks) — [calendar-system.md](../systems/calendar-system.md) |
| **Max Player Capacity** | Yes | Derived from `league_tiers_config` |
| **Level Cap / XP Modifier** | Unused | Stored on `Kingdom`; no production level-up yet ([known-issues #6](known-issues.md)) |

---

## 2.2 Calendar & Server Ticks

Live rules: [calendar-system.md](../systems/calendar-system.md).

The game advances on **scheduled ticks** in the kingdom timezone. A league season is **11 weeks (77 days)**: week 1 preparation, weeks 2–10 two league matches per week (Tue/Fri), week 11 transition.

**Live ticks (summary):** daily reset / aging / NPC maintenance; fatigue and form recovery; inactivity cleanup; Tue/Fri league matches (home ticket revenue + combat); Wed/Sun friendly matches; Thursday weekly training; Sunday arena adaptation; weekly reset (summon cooldowns, HQ maintenance, payroll, Royal Treasury). Full timetable: [calendar-system.md](../systems/calendar-system.md#weekly-server-tick-schedule).

**Not live:** dungeon-match ticks, crafting-queue ticks, dynamic world events ([future/world-events-system.md](../future/world-events-system.md)).

---

## 2.3 Economy System

Live rules: [economy-system.md](../systems/economy-system.md), [financial-crisis-system.md](../systems/financial-crisis-system.md).

Gold is the **sole** live currency. Training has **no gold cost** (fatigue is the sink). Essence is **not** in the live economy.

### Gold

- **Earned:** home-match arena ticket revenue, league / season rewards, marketplace sales (minus tax), weekly Royal Treasury share (`kingdom_reward`), item dismantle, dismissal compensation, HQ refunds
- **Spent:** HQ upgrades and weekly maintenance, hero/trainer salaries, summoning, marketplace purchases, spell learning, item repair
- Every change is a `FinancialRecord` row. Gold never goes negative; unpaid upkeep becomes **team debt**.

### Live controls

| Live | Not live (do not treat as shipped) |
|:---|:---|
| Kingdom marketplace tax → Royal Treasury | **Diminishing returns** on repeated actions (planned in economy-system) |
| Time-gated match/tick income; scaling HQ upgrade costs | **Price floor / ceiling** and suggested-price algorithms ([A7.10](../roadmap/current.md)) |
| Fatigue / form limits on hero use | **Email / trade anti-fraud** beyond the ledger (auth + inactivity email only) |
| Hero aging (non-Undead) on daily tick | Cross-kingdom trading |
| Listings expire on daily reset | Level-banded “early/mid/late game” gold phases (no production `levelUp()`, [#6](known-issues.md)) |

Marketplace: gold-only hero and item listings; seller pays tax; no cross-kingdom trade (`kingdom_id` scope). Details: [marketplace-system.md](../systems/marketplace-system.md).

### Financial Crisis & Bankruptcy

When weekly HQ maintenance (or payroll) cannot be paid, unpaid amounts accumulate as **`unpaid_debt`**.

| Phase | Effect |
|-------|--------|
| **Warning** | Outstanding debt; player notified |
| **Restricted** (≥ 2 weeks) | HQ passive bonuses suspended; upgrades, summoning, and marketplace purchases blocked |
| **Bankruptcy** (≥ 6 weeks, debt ≥ 4× weekly maintenance, no recovery) | Team released to NPC pool; player cooldown before claiming a new team |

**Recovery paths:** marketplace sales, hero dismissal (40% value), facility downgrade (25% refund on completion).

See [financial-crisis-system.md](../systems/financial-crisis-system.md).

---

## 2.4 Hero System

Live rules: [hero-system.md](../systems/hero-system.md). Rating: [hero-rating-system.md](../systems/hero-rating-system.md).

Heroes are roster units: **race**, primary attributes **1–20**, form / fatigue / morale, equipment, and spells. There are **no classes** and **no rarity**. Match XP writes `Hero.xp`; **displayed level does not increase** until an XP curve is designed ([known-issues #6](known-issues.md)). Optional **trait** on generation — [hero-system.md](../systems/hero-system.md#hero-traits).

### Playable Races

| # | Race |
|---:|:---|
| 1 | **Human** |
| 2 | **Elf** |
| 3 | **Dwarf** |
| 4 | **Orc** |
| 5 | **Undead** |
| 6 | **Giant** |
| 7 | **Ent** |
| 8 | **Genie** |

### Race Relationships

Affinities **0–100** live in `config/game/race_relations.yaml`. **Team chemistry** from the matrix is **display-only** until [#7](known-issues.md). In-combat race-synergy tables are **not** in the engine ([#9](known-issues.md)).

| Range | Tier | Live meaning |
|:---:|:---|:---|
| **90–100** | Highly Positive | Strong pairing in the chemistry number |
| **70–89** | Positive | Positive chemistry pairing; arena adaptation and training group bonus can include these races |
| **50–69** | Neutral | No chemistry bonus or penalty |
| **21–49** | Negative | Reduced chemistry |
| **0–20** | Hostile | −15 chemistry per hostile pair (display-only until #7). Weekly training applies a group penalty when a hostile pair shares a trainer |

### Relationship Matrix

| Race vs Race | Human | Elf | Dwarf | Orc | Undead | Giant | Ent | Genie |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Human** | 100 | 90 | 70 | 20 | 10 | 30 | 70 | 80 |
| **Elf** | 90 | 100 | 50 | 0 | 10 | 20 | 100 | 80 |
| **Dwarf** | 70 | 50 | 100 | 20 | 20 | 30 | 30 | 60 |
| **Orc** | 20 | 0 | 20 | 100 | 90 | 70 | 0 | 60 |
| **Undead** | 10 | 10 | 20 | 90 | 100 | 80 | 20 | 70 |
| **Giant** | 30 | 20 | 30 | 70 | 80 | 100 | 40 | 60 |
| **Ent** | 70 | 100 | 30 | 0 | 20 | 40 | 100 | 60 |
| **Genie** | 80 | 80 | 60 | 60 | 70 | 60 | 60 | 100 |

Live uses of the matrix (not in-combat synergy): summoning compatibility, arena-adaptation training bonus, weekly training **group** modifier, chemistry number on the dashboard.

**Genie dual rule (live until [A7.9](../roadmap/current.md)):** **Players** use relationship ≥ 50 (Genie scores ≥ 50 with every other race, so a Genie-adapted team can summon all races). **NPC marketplace** hero buys may apply a stricter Genie-only filter. Unifying those paths is a product decision — [summoning-system.md](../systems/summoning-system.md), [npc-simulation-system.md](../systems/npc-simulation-system.md).

### Primary Attributes

| Attribute | Abbreviation | Live use (summary) |
|:---|:---:|:---|
| Strength | STR | Melee damage |
| Dexterity | DEX | Accuracy, dodge, ranged damage |
| Constitution | KON | HP and armor (fatigue scales effective KON) |
| Speed | SPD | Initiative / turn order |
| Intelligence | INT | Spell power |
| Willpower | WIL | Magic resistance |
| Charisma | CHA | Buffers **post-match** morale deltas. Chemistry CHA offset is display-only ([#7](known-issues.md)). No trade or event bonus |
| Luck | LCK | Crit, accuracy, dodge in combat. No loot drops or world-event outcomes |

**Range:** integer **1–20** for heroes and trainers. Race flat bonuses and Thursday training stay inside that cap. Formulas: [combat-system.md](../systems/combat-system.md).

### Secondary Attributes

| Attribute | Live? |
|:---|:---|
| **Age** | Yes — daily tick (non-Undead), KO aging after matches, trainer aging on the training tick |
| **Form** | Yes — starting HP in combat; Form training recovers it |
| **Fatigue** | Yes — scales KON in combat; attribute/magic training adds +20; Form training reduces it. Fatigue **100 does not** block the weekly tick |
| **Morale** | Yes — kickoff outgoing-damage band; post-match updates. Mid-combat morale events are deferred ([#9](known-issues.md)) |

### Equipment & Magic Attributes

| Attribute | Live? |
|:---|:---|
| **Magic Capacity** | Yes — equipped spell slots, **1–5**; Magic training raises it on the Thursday tick |
| **School Mastery** | Yes — per school from combat XP (weekly Magic training does **not** grant mastery XP) |

### Equipment Slots

Live slots (**8**, one ring) — [item-system.md](../systems/item-system.md). Occupancy (two-handed occupying both hands, dual-wield, two rings) is **open** ([#8](known-issues.md)). Until then: `equip()` only clears the target slot.

| Slot | Type |
|:---|:---|
| **Main Hand** | Weapon |
| **Off-Hand** | Shield, Spell Accelerator, or a second weapon (no auto-clear of the other hand) |
| **Armor** | Head, Body, Hands, Feet |
| **Accessories** | 1 Amulet + **1 Ring** |

### Age System

Milestones live in `config/game/races.yaml`. Phase is used for display, marketplace filter, and **rating** — not for a training-efficiency curve.

| Milestone | Description |
|:---|:---|
| **Minimum Age** | Lowest recruitment age |
| **Max Junior Age** | Upper bound for starting / summoned age |
| **Prime Age Limit** | Last age labelled Prime |
| **Mortality Threshold** | Age from which a KO can become permanent death |

### Race Age Milestones

| Race | Min Age | Max Junior Age | Prime Age Limit | Mortality Threshold |
|:---|---:|---:|---:|---:|
| Human | 16 | 20 | 50 | 80 |
| Elf | 50 | 80 | 300 | 800 |
| Dwarf | 20 | 30 | 100 | 250 |
| Orc | 12 | 16 | 35 | 60 |
| Undead | 50 | 80 | 300 | 800 |
| Giant | 15 | 25 | 60 | 150 |
| Ent | 30 | 50 | 200 | 1000 |
| Genie | 100 | 150 | 500 | 2000 |

### Age Mechanics

| Phase | Live effect |
|:---|:---|
| **Junior** | Starting / summoned age is rolled in [Min Age, Max Junior Age]. **No** extra training-efficiency bonus in the Thursday tick |
| **Prime / Veteran** | Label + rating multiplier only. **No** combat stat penalty or training-speed curve by phase |
| **Elder** | KO can become **permanent death** (mortality roll). Still **no** training-speed penalty by phase |

*Undead skip daily aging; they still age from KOs and as trainers. Same milestone numbers as Elves.*

### Combat Death & Aging

KO in a match is **not** always permanent death. Post-match: years are added (including Undead; multiple KOs in one match stack). Heroes at or beyond Mortality Threshold take a mortality roll per KO; a failed roll moves them to the Graveyard. Mid-combat **Resurrection** is **deferred**. Details: [combat-system.md](../systems/combat-system.md#post-match-hero-rewards), [graveyard-system.md](../systems/graveyard-system.md).

### Morale System

**Hero morale** and **team morale** are separate values (team morale is not an average).

| Live | Not live |
|:---|:---|
| League result updates team morale and hero morale (lineup vs reserves) | Mid-combat `morale_change` (ally death, crits, CHA rallies) — [#9](known-issues.md) |
| Lineup average CHA **buffers** those post-match deltas | Fleeing / refusing orders |
| Kickoff **outgoing damage** band from hero morale ([§2.10](#210-combat-system)) | Accuracy / resistance / training-efficiency from morale |
| Friendly matches skip morale deltas | Win-streak / derby / relegation-pressure morale ([§2.12](#212-league-system)) |

### Race Attributes & Bonuses

Each race has unique restrictions, bonuses, and training modifiers that define their playstyle and progression characteristics.

#### Race-Specific Restrictions & Equipment

| Race | Equipment |
|:---|:---|
| Human, Elf, Dwarf, Orc, Genie | No slot bans |
| Undead | No slot bans. Combat blocks external heals from non-Undead casters |
| Giant | Cannot equip off-hand |
| Ent | Accessories only (no weapons, shields, or armor) |

#### Race-Specific Stat Bonuses

Races receive inherent **flat integer bonuses** to primary attributes (applied during hero generation). Human baseline = **+1** per stat. Positive values boost the stat floor; negative values reduce it.

| Race | STR | DEX | KON | SPD | INT | WIL | CHA | LCK |
|:---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Human | +1 | +1 | +1 | +1 | +1 | +1 | +1 | +1 |
| Elf | 0 | +2 | 0 | +2 | +2 | +1 | +2 | +1 |
| Dwarf | +2 | −1 | +2 | −1 | 0 | +2 | 0 | +1 |
| Orc | +3 | 0 | +1 | +1 | −1 | 0 | −2 | +1 |
| Undead | +1 | +1 | +1 | 0 | +2 | +1 | −2 | 0 |
| Giant | +3 | −1 | +3 | 0 | −1 | +1 | 0 | 0 |
| Ent | +1 | −2 | **+4** | −2 | +2 | +2 | +1 | 0 |
| Genie | 0 | +2 | 0 | +2 | +3 | +2 | +3 | +2 |

**Ent KON +4:** Ents’ elevated Constitution bonus reflects their natural bark armor. This is the highest single-stat bonus in the game.

> *These flat values are the canonical source of truth — see `config/game/races.yaml`. During hero generation the bonus is added to each rolled stat (before capping).*

#### Race-Specific Training Modifiers

Races have inherent training speed multipliers based on their lifespan. Long-lived races train slower per real-time unit, while short-lived races progress faster:

| Race | Lifespan Category | Training Speed Modifier | Justification |
|:---|:---|:---:|:---|
| Human | Standard | 1.0x | Baseline training speed |
| Elf | Very Long | 0.6x | Extended lifespan means training is spread over centuries; slower per-cycle gains |
| Dwarf | Long | 0.85x | Long lifespan; moderate training slowdown |
| Orc | Short | 1.2x | Brief lifespan; accelerated training to achieve peak quickly |
| Undead | Very Long | 0.6x | Effectively infinite lifespan; training gains spread thin |
| Giant | Long | 0.8x | Long lifespan; slow but steady progression |
| Ent | Extremely Long | 0.5x | Millennia-long lifespan; training gains highly attenuated |
| Genie | Extremely Long | 0.5x | Timeless beings; training gains highly attenuated |

Race `training_speed_modifier` applies to **attribute training** on the Thursday tick (not to Magic capacity or Form recovery). Training has **no Gold cost** and does **not** write `Hero.xp` ([#6](known-issues.md)).

#### Race-Specific Combat Bonuses

Live engine hooks only ([combat-system.md](../systems/combat-system.md)). Other flavour bonuses below are **not** in the engine.

| Race | Live in combat? |
|:---|:---|
| Human | No extra XP or combat bonus in the engine |
| Elf | No accuracy/dodge race bonus in the engine |
| Dwarf | No armor/crit race bonus in the engine |
| Orc | **Yes** — +20% melee vs non-Orcs when the hit resolves |
| Undead | External heals from non-Undead casters are blocked. No poison-immunity race hook |
| Giant | **7-hex flower** footprint. No extra main-hand damage bonus in the engine |
| Ent | **Yes** — +20% KON for HP and armor; **7-hex flower** footprint |
| Genie | No spell-effectiveness race bonus in the engine |

Two-hand / dual-wield occupancy is still [#8](known-issues.md).

### Level vs match XP

| Mechanic | Live? |
|:---|:---|
| **Starting level / age / stats** | Yes — level **1**, age in [Min Age, Max Junior Age], race flat bonuses, attributes 1–20 |
| **Match XP** | Yes — league and friendly matches add to `Hero.xp` (Library `xp_gain_pct` multiplies that XP only) |
| **Level-up** | **No** — `HeroService::levelUp()` is never called in production. Displayed level stays at generation ([#6](known-issues.md)) |
| **XP from training / crafting / events** | **No** — training has no XP; crafting is deferred |

---

## 2.5 Training System

Live rules: [training-system.md](../systems/training-system.md).

Training is trainer-centric: configure a trainer’s focus, assign heroes, wait for the **Thursday 10:00** tick (kingdom timezone). **No Gold cost.** Training does **not** write `Hero.xp` ([#6](known-issues.md)).

### Training Types

| Focus | Thursday tick | Fatigue |
|:---|:---|:---|
| **Attribute** | Raises one primary stat (1–20), capped at the trainer’s frozen value for that stat | +20 if the hero gained (cap 100) |
| **Magic** | Magic capacity +1 (scaled by `game_speed`), cap **5** | +20 if capacity increased |
| **Form** | Form +20 (scaled by `game_speed`), cap 100 | −20 (floor 0) |

Attribute gain uses trainer value, difference, difficulty, Training-facility `training_efficiency_pct`, race `training_speed_modifier`, arena adaptation, group race mix, kingdom `game_speed`, and trait (Quick Learner / Slacker / Perfectionist). Formula: [training-system.md](../systems/training-system.md#1-attribute-training).

**Not in the tick:** age-phase efficiency, hero/team morale, form as a gain penalty, chemistry ([#7](known-issues.md)).

### Trainers

Promotion is **permanent** (cannot fight again; stats frozen). Promote via training UI, or buy a trainer on the marketplace for Gold. Focus is configured **per trainer** (no fixed specialty). Items are unequipped on promotion.

| Rule | Live value |
|:---|:---|
| Trainer cap | `2 + floor((trainingFacilityLevel - 1) / 2)` |
| Slots per trainer | `3 + floor((trainingFacilityLevel - 1) / 2)` |
| Assignment | One trainer per hero; hero status stays `Available` (can still be in a lineup) |
| Trainer aging | Weekly tick, including Undead |
| Lock | Thursday **08:00–10:00** kingdom time — no configure / assign / unassign |

### Efficiency (attribute tick)

| Live | Not live |
|:---|:---|
| Training facility `training_efficiency_pct` | Made-up +5–25% bands |
| Arena: ×1.10 adapted race, ×1.05 relationship ≥ 70 | Training penalty from arena hostility |
| Group: +0.05 if another trainee is ≥ 70, −0.15 if ≤ 20 (hostile) | Chemistry as a training multiplier ([#7](known-issues.md)) |
| Kingdom `game_speed` | Age-phase / morale / low-form gain tables |
| Trait training multipliers | Junior “bonus efficiency” |

---

## 2.6 Team System

Live rules: [team-system.md](../systems/team-system.md).

One player, one team (1:1). NPC teams fill unused kingdom slots until a player claims them.

### Starting Roster & Match Eligibility

| Rule | Live |
|:---|:---|
| **Starting heroes** | **10** at creation, level **1**, age in [Min Age, Max Junior Age] |
| **Minimum to play** | **6** combat-ready heroes (available, not trainers, not dead) |
| **Understaffed** | No simulation — opponent **3–0**; both sides understaffed → **0–0** draw |

Kickoff eligibility is **roster size**, not “formation saved with 6 unique heroes”. Save rules: [§2.6.1](#261-formation-system).

### Team Components

| Component | Live |
|:---|:---|
| **Hero Roster** | Barracks capacity starts at **10** — [§2.7](#27-headquarters-system) |
| **Formations** | Up to **4** saved; one default — [§2.6.1](#261-formation-system) |
| **Team Identity** | Name, emblem, colors (`POST /api/v1/teams/{id}/settings`) |
| **Headquarters** | Facilities and passives — [§2.7](#27-headquarters-system) |
| **Fan club** | Background `fan_base` (dashboard badge; drives arena attendance) |

### Team Statistics

| Statistic | Live? |
|:---|:---|
| **Team Morale** | Yes — post-match; not an average of hero morale ([§2.4](#24-hero-system)) |
| **Team Reputation** | Yes — match results (not world events) |
| **Win/Loss Record** | Yes |
| **Team Chemistry** | Display-only from race pairs ([#7](known-issues.md)). Combat does not read it |

**Not live:** in-combat race synergy, CHA offset of hostile pairs **in combat**, team-wide buffs from world events.

---

## 2.6.1 Formation System

Live rules: [formation-system.md](../systems/formation-system.md). UI: [screens/07-formation-setup.md](../screens/07-formation-setup.md). Engine: [combat-system.md](../systems/combat-system.md#formation-ai-phased).

A formation is a **6-slot** layout (3 front, 3 back) that the combat engine reads at kickoff. Up to **4 saved** formations per team, plus optional **temporary** fixture lineups (deleted when the fixture completes). Combat is fully automated — no mid-match edits.

### Lineup

| Line | Slots | Live |
|:---|:---:|:---|
| **Front** | 3 | Starting hexes on the 17×11 grid |
| **Back** | 3 | Starting hexes behind the front row |

There is **no** abstract “front-line damage reduction” or line-break modifier. Positions are hexes; movement uses formation-wide `movement_goal`. Starting coordinates: [formation-system.md](../systems/formation-system.md#match-lineup-vs-roster).

Role labels (melee / ranged / healer) are **not** a saved system — only attributes, items, and spells.

### What the player UI can set

| Field | Live in UI |
|:---|:---|
| Slot heroes (drag-and-drop, empty slots allowed) | Yes |
| Formation name; one **default** | Yes |
| **Approach** (`aggressive` / `balanced` / `defensive`) | Yes — engine uses it for default target order and L0 heal threshold |
| **Movement goal** (copied onto every slot) | Yes |
| Per-slot `target_order` / `fallback` | **No** — engine may read L1 JSON if present (NPC tactics can fill it) |
| Per-slot `spell_priorities` | **No** — UI always saves `[]`; L0 pick or NPC-filled list |
| Action sequences, defend/flee, conditional tactics | **No** — L3 deferred |
| Clone, suggested/auto-fill formation | **No** |
| Sandbox `POST /api/v1/combat/simulate` | **No** ([#9](known-issues.md)). **Friendly matches** are a separate live path |

### Save vs kickoff

Canonical save rules: [formation-system.md](../systems/formation-system.md#validation-rules). Open decision: [#10](known-issues.md).

| Check | Live |
|:---|:---|
| Saved formation count | Max **4** (temporaries do not count) |
| Slots on **save** | **0–6** persisted. Duplicate `hero_id` is **not** rejected |
| Unique 6-hero lineup at save | **Not required** |
| Match entry | Team must have ≥ 6 combat-ready heroes or the fixture **forfeits** (no simulation) |
| During combat | Formation cannot be changed; no substitutions |

### Approach (engine defaults when `target_order` is empty)

| Approach | Default targeting |
|:---|:---|
| **Aggressive** | Back row, then front |
| **Balanced** | Front row, then back |
| **Defensive** | Living front row, then highest physical attack |

### Not live (do not treat as shipped)

| Claim | Status |
|:---|:---|
| Racial / role **synergy** bonuses | Deferred ([#7](known-issues.md) / [#9](known-issues.md)) |
| Per-hero targeting essays, dual-wield target lists, Flexible AI | No player editor ([A7.6](../roadmap/current.md)) |
| Spell-condition editor (On Low Health / On Low Morale / …) | UI writes `[]` |
| Mid-match formation switch or substitution | L3 deferred |
| Suggested formations, clone, simulate sandbox | Not in UI / API |

---

## 2.7 Headquarters System

Live rules: [headquarters-system.md](../systems/headquarters-system.md). Summoning: [summoning-system.md](../systems/summoning-system.md).

Each team has one **Headquarters** with **7 facilities** (no Forge). Facilities start at level **1**. Passives scale with facility level. Restricted-crisis teams lose those passives ([financial-crisis-system.md](../systems/financial-crisis-system.md)).

### Facilities

| Facility | Live bonus |
|:---|:---|
| **Training** | Weekly attribute-training `training_efficiency_pct` |
| **Medical** | Daily `fatigue_reduction_pct` and form `recovery_speed_pct` |
| **Library** | Match XP `xp_gain_pct` only. Does **not** change spell learning cost or school-mastery XP. That XP still does not level up ([#6](known-issues.md)) |
| **Treasury** | `gold_income_pct` on arena ticket gold. No storage-capacity mechanic |
| **Barracks** | Roster cap: **10 + round(2 × barracks level)**. No team-morale recovery bonus |
| **Summoning Chamber** | Summon quality (`summon_base_stat_bonus`, `summon_stat_random_bonus`). Cycle limit is `max(1, round(1 × game_speed))` — **not** shortened by upgrades. `summon_stat_total_cap` is stored and **unread** ([A6.5](../roadmap/current.md)) |
| **Arena** | `ticket_revenue_pct` and seating `arena_capacity` for **home-match ticket gold**. Attendance analytics UI is still open ([#4](known-issues.md)) |

### Arena adaptation

One preferred race (`race_optimization`). Changes wait for the Sunday `race_optimization` tick and then lock for one cycle.

| Live | Not live |
|:---|:---|
| Summoning pool = adapted race plus relationship ≥ 50 (all 8 if unset). Genie can summon all races; NPC marketplace may use a stricter Genie-only filter ([A7.9](../roadmap/current.md)) | Training penalty for hostile races |
| Thursday training: ×1.10 same race, ×1.05 relationship ≥ 70, else ×1.0 | Extra fatigue / form / morale decay from adaptation |

### Upgrades & maintenance

| Rule | Live |
|:---|:---|
| Currency | **Gold only** — paid to the Royal Treasury. No other resources |
| Concurrency | **One** upgrade or downgrade at a time |
| Cost / duration | Scale with facility level, HQ `total_level`, and kingdom `game_speed` — [headquarters-system.md](../systems/headquarters-system.md) |
| Completion | `daily_reset` tick |
| Cancel upgrade | Within **1 hour**, full gold refund |
| Downgrade | Min level **1**; **25%** refund of the last upgrade cost; lock until next `weekly_reset` |
| Weekly maintenance | `weekly_reset`; unpaid remainder → `unpaid_debt` |

**HQ `total_level`** is the sum of the seven facility levels (starts at **7**).

### Not live (do not treat as shipped)

| Claim | Status |
|:---|:---|
| Visual themes, facility layout, visitor access | Not in UI |
| HQ morale-over-time | Not implemented |
| Combat home-advantage buff | Not in the engine (arena is ticket gold only) |
| Crafting recipes, Forge, “advanced training methods” from HQ level | Crafting deferred ([future/crafting-system.md](../future/crafting-system.md)) |

---

## 2.8 Item System

Live rules: [item-system.md](../systems/item-system.md).

Items carry **attribute bonuses** and **durability**. Live sources: **merchant** (fixed-price basics) and **marketplace** (player listings). Crafting and loot drops are **deferred**.

### Equipment slots (8)

Canonical slots — [item-system.md](../systems/item-system.md). Occupancy (two-handed occupying both hands, dual-wield, two rings) is **open** ([#8](known-issues.md)). Until then: `ItemService::equip()` only clears the **target** slot.

| Slot | Type |
|:---|:---|
| **Main Hand** | Weapon (`main_hand`) |
| **Off-Hand** | Shield, Spell Accelerator, or a weapon whose `slot_type` is `off_hand` |
| **Armor** | Head, Body, Hands, Feet |
| **Accessories** | 1 Amulet + **1 Ring** |

Two-handed `sub_type` values exist on items; they do **not** auto-clear `off_hand`. Giants still cannot equip off-hand; Ents accessories only ([§2.4](#24-hero-system)).

### Rarity

`ItemRarity`: Common, Uncommon, Rare, Epic, Legendary, Mythic. Rarity scales dismantle gold and repair cost. Do not treat “unique / game-changing effects” as a live combat contract.

### Live mechanics

| Mechanic | Live |
|:---|:---|
| Equip / unequip | Hero-detail paperdoll (click); `/app/inventory` **redirects**. Race restrictions from `RaceConfig` |
| Durability | 0–100; loss after combat; at 0 the item contributes **no** combat bonuses |
| Repair | Gold to 100; cost = missing points × rarity rate |
| Dismantle | Unequipped `available` item → gold scrap by rarity |
| Marketplace escrow | `status = selling` — cannot equip or dismantle until cancelled |
| Enchanting | **Not designed** ([#2](known-issues.md)). No essence |

---

## 2.9 Spell System

Live rules: [spell-system.md](../systems/spell-system.md). Combat pick: [combat-system.md](../systems/combat-system.md).

Learn for **gold only** (`spell_learning_cost`). No essence. Library HQ does **not** discount learning or grant mastery XP.

### Proficiency

| Attribute | Live |
|:---|:---|
| **Magic Capacity** | Equipped spell slots **1–5**; Thursday Magic training raises it |
| **School Mastery** | Per school (Fire, Water, Air, Earth, Light, Dark). Learn requires mastery ≥ spell `required_mastery_tier`. Mastery XP comes from **combat**, not Magic training |

Seeded catalog is **Tier 1–2**. Mastery can still reach **Tier 5**; T3–T5 spells are unused until [A7.11](../roadmap/current.md). Offensive seeded spells set `requires_magical_weapon`.

### Types

`SpellType`: **Offensive**, **Defensive**, **Utility**. Seeded effects include damage, heal, burn/freeze/shock/haste/shield/curse, cleanse, and Dark life drain. There is **no** summon-creature spell. Mid-combat **Resurrection** is **deferred**.

When formation `spell_priorities` is empty, the engine uses the **L0** pick (heal under the approach threshold, else highest-tier offense). Player UI always saves `[]` ([§2.6.1](#261-formation-system)).

### Schools (live hooks only)

| School | Notes |
|:---|:---|
| **Fire / Water / Air / Earth** | Seeded T1–T2 as above |
| **Light** | Heal / bless / cleanse; holy extra vs Undead on seeded Light heal. **No** revive |
| **Dark** | Curse and life drain. External heals from **non-Undead** casters are blocked on Undead ([§2.4](#24-hero-system)) |

INT feeds spell power for everyone. **No** extra Genie/Elf spell-effectiveness race hook in the engine. Haste/Shock may apply as statuses; they do **not** change initiative yet ([#9](known-issues.md)).

---

## 2.10 Combat System

Live rules: [combat-system.md](../systems/combat-system.md). Open leftovers: [#9](known-issues.md).

Turn-based, **fully automated**. Players set formation **before** kickoff; there is no mid-battle input. The UI is a **replay** of `combat_log` at `/app/battles/{id}`. League Tue/Fri and friendly Wed/Sun use the same engine.

**Live inputs:** primary stats, equipment (scaled by durability), form (starting HP), fatigue (effective KON), kickoff morale (outgoing damage), Orc/Ent race hooks ([§2.4](#24-hero-system)). Displayed **level** is in the HP formula but stays at generation until [#6](known-issues.md). **Age** is post-match KO aging / mortality, not an in-combat stat curve.

### Combat flow

| Step | Live |
|---:|:---|
| **1** | Fixture lineup (saved formation or default) — [§2.6.1](#261-formation-system) |
| **2** | Roster eligibility: ≥ 6 combat-ready heroes or **forfeit** (no simulation) |
| **3** | Kickoff starts a **cohort**; forfeits skip the engine |
| **4** | Messenger **lockstep rounds** (max **200**). Failed battles become `stalled` so others continue |
| **5** | Post-match: standings, fan club, morale, mastery XP, hero XP / form / fatigue, durability, aging → graveyard |
| **6** | Persist `Battle` + `combat_log`; product UI is **replay only** |

Round **200** ends on current kill scores (HP is not a tiebreak). Preparation length and fumble-without-retarget are **locked**.

### Match scoring

Each **kill** (enemy lineup slot removed during the match) = **1** point. Max **6** per team.

| Outcome | Kill score | Simulation |
|:---|:---|:---|
| **Normal match** | 0–6 per team; higher score wins (draw if tied) | Full combat |
| **One team understaffed** | **3–0** for the eligible team | No simulation |
| **Both understaffed** | **0–0** | No simulation |

League table points (Win **3**, Draw **1**, Loss **0**) come from winner / loser / draw, not from kill totals directly.

### Morale in combat

Kickoff **outgoing damage** band on physical and offensive-spell damage (heals unchanged): 80–100 ×1.20, 60–79 ×1.10, 40–59 ×1.00, 20–39 ×0.90, 0–19 ×0.80.

Team morale updates **post-match** from the result. Mid-battle `morale_change` (ally death, crits, CHA rallies) and Volatile / Battle Hardened in-combat hooks are **deferred** ([#9](known-issues.md)).

### Not live (do not treat as shipped)

| Claim | Status |
|:---|:---|
| Replay HUD: logos, match type, morale widget, initiative queue, skip-to-end | Open ([#9](known-issues.md)) |
| Combat **home advantage** | Not in the engine (arena is ticket gold only) |
| Haste +30% / Shock −30% on initiative | Design text only |
| In-combat race synergy; `POST /api/v1/combat/simulate` | Deferred ([#7](known-issues.md) / [#9](known-issues.md)) |
| Live / in-progress match UI | Replay after completion only |

---

## 2.11 Marketplace System

Live rules: [marketplace-system.md](../systems/marketplace-system.md). Economy: [§2.3](#23-economy-system).

Same-kingdom **gold** listings: **hero**, **trainer**, and **item**. No cross-kingdom trade. Seller pays kingdom tax (`marketplace_tax_rate`) to the Royal Treasury; the buyer pays the listed buyout only.

| Rule | Live |
|:---|:---|
| Escrow | Listed hero `Selling`; listed item `selling` (cannot equip / dismantle) |
| Hero transfer | Age, form, fatigue, attributes, `xp`, and displayed level stay with the hero. **Morale resets to 50** |
| Roster / trainer caps | Buy and bid check Barracks / Training limits |
| Auctions | Gold reserved; settle on `daily_reset` when the listing expires |
| Merchant | Fixed-price basic items (`POST /app/marketplace/buy-basic`); `fee_amount = 0` |
| Price floor / ceiling / suggested price | **Not live** ([A7.10](../roadmap/current.md)) |

NPC teams also buy and sell on ticks ([npc-simulation-system.md](../systems/npc-simulation-system.md)). Player marketplace buys have **no** race filter. NPC hero buys score compatible races from the summoning pool, except a Genie-adapted NPC team may buy **Genie-only** (dual rule until [A7.9](../roadmap/current.md)).

---

## 2.12 League System

Live rules: [league-system.md](../systems/league-system.md). Calendar: [calendar-system.md](../systems/calendar-system.md).

Kingdom-scoped **tiers and groups**. Default 60-team kingdom: T1 (1 group), T2 (2), T3 (3), **10** teams per group — from `league_tiers_config`. A claimed NPC team keeps its standing and fixtures.

### Season

**77 days (11 weeks).** Week 1 preparation (no league matches). Weeks 2–10: double round-robin, **Tue/Fri 18:00** (18 rounds; each team 9 home / 9 away). Week 11 Friday 19:00: season transition (promotion / relegation, gold rewards, next-season fixtures).

Friendly matches are a **separate** live path (Wed/Sun) — [friendly-match-system.md](../systems/friendly-match-system.md).

### Table & kickoff

| Rule | Live |
|:---|:---|
| Table points | Win **3**, Draw **1**, Loss **0** |
| Match score | Kill-based 0–6 — [§2.10](#210-combat-system) |
| Understaffed | Forfeit **3–0** / **0–0**, no simulation |
| Promotion / relegation | Slot counts from `league_tiers_config`; cross-group ties use points → goal difference → wins → player over NPC → reputation → chemistry → team id |
| Season gold | Auto-credited from tier base × position × season growth × promo/releg modifier. Ledger `league_reward` |

Post-match **morale** is the win / draw / loss (and forfeit) delta, CHA-buffered — [§2.4](#24-hero-system). Chemistry is a **tie-break number only** until [#7](known-issues.md).

### Not live (do not treat as shipped)

| Claim | Status |
|:---|:---|
| Season length “2–4 weeks” | Live season is **77 days** |
| Win-streak / derby / relegation-pressure morale | Not implemented |
| Promotion morale boost as a separate event | Season gold only; match morale is the usual result delta |
| Veteran leadership, junior **bonus XP**, age-diversity bonus | Not implemented (age phase is display / rating / mortality) |
| Extra aging from “season fatigue” | Aging is daily tick + KO years, not a league-long curve |

---

## 2.13 Graveyard System

Live rules: [graveyard-system.md](../systems/graveyard-system.md).

The Graveyard is a **memorial wall**, not combat-death-only. Hero and trainer **dismissals** write the same `GraveyardMemorial` snapshot as permanent combat death. Records do **not** consume Barracks roster space.

### What writes a memorial

| Cause (`MemorialCause`) | Live |
|:---|:---|
| **`dismissed`** | Hero dismiss (`POST /api/v1/heroes/{id}/dismiss`, 40% gold) and trainer dismiss (`POST /api/v1/training/trainers/{id}/dismiss`, 30% gold) |
| **`combat_death`** | Permanent combat death after the post-match mortality roll (typically elder at or beyond Mortality Threshold) — [§2.4](#24-hero-system), [§2.10](#210-combat-system) |
| **`age` / `retired` / `death`** | Reserved enum values — **not** written today ([A6.2](../roadmap/current.md)) |

Trainers use the same table (`role_at_departure = trainer`). There is no separate staff-record entity.

### Snapshot (immutable)

Name, race, role at departure, age, `final_level`, `final_stats`, trait, `matches_played` / `matches_won`, `combats_fallen`, cause, `departed_at`, `original_hero_id`. Heroes **cannot** be revived or returned to the roster. Mid-combat Resurrection is **deferred** ([§2.9](#29-spell-system)).

UI: `/app/graveyard` (filter by role, cause, race; search; summary counts). Read API: `GET /api/v1/graveyard`, `GET /api/v1/graveyard/{id}`.

---

## 2.14 Community System

Live rules: [community-system.md](../systems/community-system.md). In-app alerts: [notification-system.md](../systems/notification-system.md).

Kingdom-scoped social features. Mail and forum do **not** cross kingdoms.

### Live

| Feature | Live |
|:---|:---|
| **In-app mail** | Yes — navbar modal (`/api/v1/messages*`). Player-to-player inbox, not a trade protocol (marketplace is [§2.11](#211-marketplace-system)) |
| **Forum** | Yes — `/app/community` threads and posts. Author can lock. `is_pinned` is stored but has **no write API** ([A6.3](../roadmap/current.md)) |
| **News** | Public `/news` archive. Admin / CMS to create articles is **not** implemented (seed / DB) |
| **Public profiles** | Same-kingdom player and team profile. Team identity, counts, league record. **Not** a roster of hero form / fatigue / level |
| **Leaderboards** | Kingdom rankings on `/app/league` (standings API). No `/api/v1/leaderboards` |
| **Content filter** | Yes — blacklist on mail and forum text |
| **In-app notifications** | Yes — navbar modal; title/body frozen at **write-time locale** ([A6.4](../roadmap/current.md)) |

### Email (outbound)

Live outbound mail is **auth and inactivity only**. There is **no** email for trades, marketplace, or league results.

| Live | Not live |
|:---|:---|
| Registration verify, password reset, email change | Trade / marketplace emails |
| Inactivity warning (21 days) and team release (28 days) — [player-inactivity-system.md](../systems/player-inactivity-system.md) | League-result or world-event emails |
| | Per-type notification preference emails ([A8.6](../roadmap/current.md)) |

### Not live (do not treat as shipped)

| Claim | Status |
|:---|:---|
| Public wiki CMS (`/wiki`) | Planned ([A8.5](../roadmap/current.md)) |
| Forum pin / unpin for admins | Field only ([A6.3](../roadmap/current.md)) |
| Cross-kingdom mail or forum | No |
| Email alerts for trades / league / events | Not implemented |

---

## Deferred (not in core)

Parked Milestone 8/9 design. **Not live.** One pointer — do not treat dungeons, crafting, quests, world events, or alliances as shipped systems. Full specs: [`docs/future/`](../future/).

| Parked | Spec |
|:---|:---|
| **Dungeons** | [future/dungeon-system.md](../future/dungeon-system.md) — backend removed; no dungeon-match ticks |
| **Crafting** | [future/crafting-system.md](../future/crafting-system.md) — backend, UI, Forge, essence, and kingdom Crafting Boost removed |
| **Quests** | [future/quest-system.md](../future/quest-system.md) |
| **World events** | [future/world-events-system.md](../future/world-events-system.md) |
| **Alliances / guilds** | Milestone 9 — [roadmap/history.md](../roadmap/history.md) |

Do not implement unless the user explicitly asks.

---

**End of overview**

> Live rules: `docs/systems/*.md`. Open design: [known-issues.md](known-issues.md). Active work: [roadmap/current.md](../roadmap/current.md).
