# Combat System

Reference: [game-summary.md](../game-summary.md#210-combat-system)

Purpose: Document combat simulation, match eligibility, scoring, derived combat stats, turn order, status effects, and result processing.

---

## Automation Model

Combat is **fully automated**. Players configure behaviour **before** the match via the [Formation System](formation-system.md) (`approach` today; per-slot targeting / spell priorities in later AI layers). The engine simulates the entire bout server-side; the UI is a **replay viewer** over `combat_log` — see [screens/12-combat-battle.md](../screens/12-combat-battle.md).

There is **no** mid-battle player input (no turn submission, target picking, Auto-Battle toggle, or surrender during simulation). Architecture, log format, and AI phases: [Simulation Contract](#simulation-contract), [Engine Architecture Decisions](#engine-architecture-decisions), and [Formation AI](#formation-ai-phased).

---

## Implementation Status

| Component | Status | Notes |
|-----------|--------|-------|
| Match eligibility & forfeit rules | ✅ Implemented | `LeagueMatchResolutionService::resolveForfeitOutcome()` |
| Kill-based scoring & standings | ✅ Implemented | `StubRandomMatchSimulator` until full engine ships |
| `Battle` entity persistence | ✅ Implemented | Scores, formations, result enum |
| Post-match side effects | ✅ Implemented | Standings, fan club, morale, hero/team chronicle, mastery XP |
| `CombatStatCalculator` + `DerivedCombatStats` | ✅ Implemented | Profile-aware (`Equipped`, `HumanNeutral`, `FullIntrinsic`) |
| Deterministic turn engine | ⏳ Pending | Milestone 6 Step 6.1 — see [Simulation Contract](#simulation-contract) |
| `combat_log` JSON + replay UI | ⏳ Pending | Event-stream contract decided; UI pending |
| Simulation contract (VO / API layers) | ✅ Documented | Pending code — see below |
| Combat death → graveyard | ⏳ Pending | Blocked on full engine |
| Formation AI (L0–L2) | ⏳ Pending | Phased — see [Formation AI](#formation-ai-phased) |

Status effect reference config: [config/game/status_effects.yaml](../../config/game/status_effects.yaml)

---

## Match Eligibility

Before any match is simulated, each team is checked for roster readiness:

| Condition | Result |
|-----------|--------|
| Both teams have ≥ 6 **combat-ready** heroes | Proceed to simulation |
| Exactly one team has < 6 combat-ready heroes | **Automatic forfeit** — eligible team wins **3–0** (kill score); no simulation |
| Both teams have < 6 combat-ready heroes | **Automatic draw** — **0–0** kill score; no simulation |

**Combat-ready hero:** active status, not a Trainer, not permanently dead (Graveyard).

A team needs 6 combat-ready heroes to **enter** a match, independent of formation lineup configuration. Formation still requires all 6 slots filled when simulating.

---

## Match Scoring

- Each **kill** (enemy hero removed from the opposing lineup during the match) = **1 point** for the scoring team
- Maximum score per team = **6** (one per enemy lineup slot)
- Forfeit win = **3–0** (half of maximum 6)
- Double forfeit = **0–0**

**League table points** (Win 3 / Draw 1 / Loss 0) are derived from the match winner/loser/draw, not from kill totals directly.

---

## Combat Flow

1. Formation selection (6 heroes per team, 3 front / 3 back)
2. Roster eligibility check (see above)
3. Queue match for simulation (Redis) — skipped on forfeit/draw
4. PHP worker runs deterministic turn-based simulation *(pending — stub random scores today)*
5. Apply post-match updates (XP, form, fatigue, morale, aging)
6. Store result in `Battle` entity

---

## Item Influence on Combat Stats

Equipped items affect combat calculations in two stages, scaled by **durability**:

### Durability scaling

`Durability_Factor = Durability / 100`

- **100%:** full stat bonuses
- **50%:** stats halved
- **0% (broken):** zero bonuses

### Indirect modifiers (primary attributes)

Added to base attributes **before** derived stats:

```
Effective_STR = Hero_Base_STR + Sum(Item_STR_Bonus × Durability_Factor)
Effective_KON = Hero_Base_KON + Sum(Item_KON_Bonus × Durability_Factor)
```

### Direct modifiers (derived stats)

Added **after** primary contribution:

```
Total_Armor_Value = (Effective_KON × 1.5) + Sum(Item_Armor_Bonus × Durability_Factor)
Total_Spell_Power = (Effective_INT × 3) + Sum(Item_Spell_Accelerator_Bonus × Durability_Factor)
```

---

## Derived Combat Stats & Formulas

Combat stats derive from primary attributes (1–20), equipped items, race, form, fatigue, and morale. No character classes.

### Health Points (HP)

- **Max HP:** `Max_HP = (Level × 30) + (Effective_KON × 12)`
- **Form / Fatigue:** HP scaled by `Form / 100`; fatigue reduces effective KON
- **Race:** Ents +20% to Constitution-based calculations

### Physical Attack (ATK)

- **Unarmed:** `Base_ATK = Effective_STR × 2`
- **Melee weapon:** `Physical_ATK = Weapon_Damage × (1 + Effective_STR / 15)`
- **Ranged weapon:** `Physical_ATK = Weapon_Damage × (1 + Effective_DEX / 15)`
- **Race:** Orcs +20% melee vs non-Orcs; Giants +10% main-hand damage; Ents cannot equip weapons
- **Morale:** high +10–20% damage; low −10–20%

### Spell Power (SP)

```
Spell_Power = Effective_INT × 3 + Equipped_Spell_Accelerator_Bonus
Final_Spell_Effect = Spell_Base_Effect × (1 + Spell_Power / 100)
```

- **Race:** Genies +15% spell effectiveness

### Physical Defense / Armor (DEF)

```
Armor_Value = Equipped_Armor_Defense_Sum + (Effective_KON × 1.5)
Damage_Reduction_Percent = Armor_Value / (Armor_Value + 100)
```

- **Race:** Dwarves +15% armor effectiveness; Ents +20% defensive calculations (no armor slots)

### Magic Resistance (RES)

```
Magic_Resistance = Equipped_Accessory_Resistance_Sum + (Effective_WIL × 2)
Magic_Reduction_Percent = Magic_Resistance / (Magic_Resistance + 100)
```

### Speed / Initiative (INIT)

```
Initiative = Effective_SPD + random_int(-3, 3)
```

- **Race:** Ents −20% speed penalty
- **Status:** Haste +30%, Shock −30%

### Accuracy (ACC)

```
Accuracy_Percent = 80 + Effective_DEX × 1 + Effective_LCK × 0.5
```

- **Race:** Elves +10% accuracy
- **Status:** Blind −40%

### Dodge (EVA)

```
Dodge_Chance_Percent = (Effective_DEX + Effective_SPD) × 0.75 + Effective_LCK × 0.25  (cap 50%)
```

- **Race:** Elves +10% dodge
- **Status:** Shadow Cloak +40% dodge

### Critical Hit Chance (CRT)

```
Crit_Chance_Percent = 5 + Effective_LCK × 1 + Effective_DEX × 0.25  (cap 50%)
```

- **Race:** Genies +15% spell crit chance
- Default crit damage multiplier: **1.5×** (Berserker trait overrides to 2.0×)

---

## Technical Implementation (Derived Stats)

| Component | Path |
|-----------|------|
| Value object | `src/ValueObject/Combat/DerivedCombatStats.php` |
| Calculator | `src/Service/Combat/CombatStatCalculator.php` |
| Profiles | `CombatStatProfile::Equipped`, `HumanNeutral`, `FullIntrinsic` |
| Tests | `tests/Service/Combat/CombatStatCalculatorTest.php` |

`calculate(Hero)` uses the **Equipped** profile (items, trait, form). Rating system uses **HumanNeutral** and **FullIntrinsic** — see [hero-rating-system.md](hero-rating-system.md).

---

## Mastery Combat Bonuses

When equipped gear sub-types reach **100% attunement**, passive bonuses apply per mastery tier above 1:

### Weapon mastery (examples)

| Style | Bonus per tier above 1 |
|-------|----------------------|
| One-handed sword/axe/mace | Physical ATK +5% |
| Two-handed sword/axe/mace | Physical ATK +6% (+ crit or armor for some styles) |
| Dagger | Physical ATK +4%, Crit +2% |
| Bow / crossbow | Physical ATK +5–6%, Accuracy +1–2% |
| Wand / staff / spell accelerator | Spell Power +4–5%, Initiative +1–2% |
| Shield | Armor +5%, Dodge/Block +2% |

### Magic school mastery

Each equipped spell's school: **Spell Power +5% per tier above 1** (stacks across schools).

---

## Trait Modifiers in Combat

### Static (in `DerivedCombatStats`)

| Trait | Effect |
|-------|--------|
| Fragile | Max HP × 0.90 |
| Glasscannon | Spell Power × 1.15, Armor × 0.90 |
| Overconfident | Physical ATK × 1.10, Accuracy −8% |
| Berserker | Crit +15%, Accuracy −8%, crit damage 2.0× |
| Reckless | Crit +15%, Dodge −10% |

### Conditional (engine metadata on `DerivedCombatStats`)

| Trait | Trigger |
|-------|---------|
| Clutch | HP ≤ 30% → Accuracy +15%, Armor × 1.10 |
| Glass Jaw | HP ≤ 50% → incoming physical × 1.10 |
| Perfectionist | Consistent damage (no RNG variance) |
| Loner | Ignores race synergy matrix |
| Volatile / Battle Hardened | Morale decay × 2.0 / × 0.5 on ally death |
| Audience Favorite | +5% arena revenue when fielded (handled in `ArenaRevenueService`, not combat engine) |

---

## Simulation Contract

Locked design for Milestone 6 Step 6.1 implementation. Align PHP value objects and `MatchSimulatorInterface` with this section; do not invent alternate shapes without updating the docs.

### API layers

| Layer | Responsibility |
|-------|----------------|
| `MatchSimulatorInterface::simulate(LeagueFixture): MatchOutcome` | Keep for league resolution. Builds engine request from fixture formations, calls `CombatEngine`, maps result onto `MatchOutcome`. |
| `CombatEngine::simulate(CombatMatchRequest): CombatSimulationResult` | Pure core — no Doctrine fixtures, testable without league. |
| `LeagueMatchResolutionService` | Eligibility / **forfeit before** engine; after sim: persist `Battle`, standings, fan club, morale, chronicles, mastery. |

Forfeit outcomes stay **outside** the engine (existing `resolveForfeitOutcome()`).

Identity: side **A** = home / `Battle.team_a`; side **B** = away / `Battle.team_b`.

### Value objects (planned)

```text
CombatMatchRequest
  sideA, sideB: CombatSide
  matchType: MatchType
  seed: int                         // required
  engineVersion: int = 1

CombatSide
  teamId: int
  formationId: int|null
  approach: FormationApproach       // aggressive | balanced | defensive
  combatants: list<CombatantSnapshot>  // exactly 6, ordered by slot

CombatantSnapshot
  heroId, slot, race, level, form, fatigue, morale
  derived: DerivedCombatStats       // CombatStatCalculator, Equipped profile
  strategy: array                   // {} ⇒ L0 defaults
  spellPriorities: array            // [] ⇒ L0 defaults
  spells: list<{ id, school, … }>   // equipped / formation overrides

CombatSimulationResult
  outcome scores via MatchOutcome   // isForfeit = false
  seed: int
  combatLog: array                  // envelope below
  // 6.1d+: optional death/durability hints — not in 6.1a
```

#### `MatchOutcome` extension

Extend the existing VO (prefer over a parallel wrapper for league wiring):

```text
MatchOutcome(homeScore, awayScore, isForfeit = false, combatLog = [], seed = null)
```

| Producer | `combatLog` |
|----------|-------------|
| Forfeit | `{ "version": 1, "simulator": "forfeit", "events": [] }` |
| Stub (until removed) | `{ "version": 1, "simulator": "stub_random", "events": [] }` |
| Engine | Full envelope (`simulator`: `combat_engine`) |

`LeagueMatchResolutionService::createBattle()` must persist `$outcome->getCombatLog()` (and stop hard-coding stub metadata once the engine ships).

### Seed

- League: deterministic from fixture context, e.g.  
  `seed = hash_to_u32(fixtureId, seasonId?, scheduledAt timestamp, engineVersion)`.
- Practice / sandbox (`POST /api/v1/combat/simulate`): client may pass `seed`, or server picks random and **returns** it in the result.
- Same request + seed + `engineVersion` ⇒ same scores and event stream.

### `combat_log` envelope (v1)

```json
{
  "version": 1,
  "simulator": "combat_engine",
  "seed": 987654321,
  "match_type": "league",
  "teams": {
    "a": { "team_id": 1, "formation_id": 10, "approach": "balanced" },
    "b": { "team_id": 2, "formation_id": 11, "approach": "aggressive" }
  },
  "lineup": {
    "a": {
      "front_1": { "hero_id": 5, "name": "…" },
      "front_2": { "hero_id": 6, "name": "…" },
      "front_3": { "hero_id": 7, "name": "…" },
      "back_1": { "hero_id": 8, "name": "…" },
      "back_2": { "hero_id": 9, "name": "…" },
      "back_3": { "hero_id": 10, "name": "…" }
    },
    "b": { }
  },
  "events": [ ],
  "result": { "score_a": 4, "score_b": 2 }
}
```

| Field | Role |
|-------|------|
| `version` | Schema version; replay client must understand or refuse |
| `simulator` | `combat_engine` \| `forfeit` \| `stub_random` |
| `seed` | PRNG seed used for this run |
| `lineup` | Slot → hero labels for replay UI |
| `events` | Ordered combat events |
| `result` | Final kill scores (also mirrored on `Battle.score_a/b`) |

### Event types and payloads (6.1a minimum)

Common fields: `t` (monotonic index), `type`; commonly also `round`, `side` (`a`\|`b`), `slot`, `hero_id`.

| `type` | Payload (v1) |
|--------|----------------|
| `match_start` | — |
| `round_start` | `round` |
| `turn_start` | `side`, `slot`, `hero_id`, `initiative` |
| `attack` | `target_side`, `target_slot` |
| `spell` | `spell_id`, `target_side`, `target_slot` or `targets[]` |
| `defend` | — |
| `hit` / `miss` / `crit` | Follows the preceding action event in order |
| `damage` | `target_side`, `target_slot`, `amount`, `hp_after`, `source` (`physical` \| `magical` \| `dot`) |
| `heal_applied` | Same shape as `damage` where applicable |
| `status_applied` / `status_tick` / `status_expired` | `effect`, optional `stacks` |
| `ko` | `side`, `slot`, `hero_id` — awards +1 kill to the opposing side |
| `kill_score` | `score_a`, `score_b` — emit **after each** `ko` |
| `match_end` | `score_a`, `score_b`, `rounds` |

Deferred event types (not required for 6.1a): `morale_change`, `revive`, `heal` as a distinct action type if covered by `spell`, hybrid `snapshot`.

Replay reconstructs HP/status by folding `events` — see [screens/12-combat-battle.md](../screens/12-combat-battle.md).

### L0 defaults (config-backed)

Weights / thresholds live in planned `config/game/combat_ai_l0.yaml` (not hardcoded magic numbers in services).

| Approach | Default target order (first living enemy) | Action bias |
|----------|-------------------------------------------|-------------|
| `aggressive` | Enemy back row left→right, then front | Attack; heal only if self/ally HP < 25% |
| `balanced` | Enemy front left→right, then back | Attack; heal if ally HP < 40% |
| `defensive` | Enemy front; else highest threat | Defend/heal if HP < 50%; else attack |

L0 spell pick: at most one ready “best damage / spell power” equipped spell; otherwise basic attack. Full `spell_priorities` interpretation is **L2**.

### Engine vs post-match boundary

| Inside engine (6.1a) | Stays in `LeagueMatchResolutionService` (already) | Later (6.1d+) |
|----------------------|-----------------------------------------------------|---------------|
| Turn loop, damage, KO, kill score, event log, seed | Standings, fan club, team/hero match morale, chronicles, mastery XP, `matches_played` / wins | Combat deaths → aging → permanent death → graveyard; item durability loss; per-hero XP/form/fatigue derived from log |

Engine must **not** write graveyard or item rows directly. At 6.1d it may attach side-effect *hints* on the result for the resolution layer to apply.

### Recommended code order (contract → engine)

1. VO: `CombatMatchRequest`, `CombatSide`, `CombatantSnapshot`; extend `MatchOutcome`.
2. Builder: `Formation` → snapshots via `CombatStatCalculator`.
3. Thin `CombatEngine` (e.g. emit `match_start` / `match_end` only) wired through `MatchSimulatorInterface` — prove `Battle.combat_log` persistence.
4. Turn loop + L0 AI + full event set.
5. Fixture→seed helper + regression test: same seed ⇒ same log and scores.

---

## Engine Architecture Decisions

Decided model for Milestone 6 Step 6.1. Detailed request/result shapes and event payloads: [Simulation Contract](#simulation-contract). Do not invent alternate formats without updating both sections.

### Combat log format — event stream

`Battle.combat_log` is an **append-only event stream** (not per-turn full snapshots). Envelope, event table, and seed rules are defined in the [Simulation Contract](#simulation-contract).

**Rationale:** Smaller payloads, easy unit tests, replay seek via fold-reduce; hybrid snapshots can be added later if UI needs fast scrubbing.

#### Planned extension — hybrid snapshots

If replay seek becomes costly: emit optional `snapshot` events every N turns or after each `ko`, without replacing the event stream as source of truth.

### Determinism and RNG

- Simulation is **seeded** (see [Seed](#seed)); same formations + same seed ⇒ same log and scores (modulo intentional `engineVersion` bumps).
- Trait **Perfectionist** removes damage variance for that hero; it does not disable all combat RNG (accuracy, dodge, crit still apply unless separately specified).
- Replay **never re-simulates** for display — it only plays back `events`. Re-sim with seed is for tests/debug only.

### Out of scope for first engine ship

| Deferred | Until |
|----------|--------|
| Mid-match hero substitution | AI layer L3+ / later milestone |
| Mid-match formation switch | L3+ |
| Player surrender / flee during sim | Not applicable (automation model) |
| Per-turn full state snapshots as primary log | Only if hybrid extension ships |
| Full racial / role synergy number tables | Can land as flat modifiers after L1 |
| Action-sequence script UI | Layer L3 |

---

## Formation AI (Phased)

Combat AI is fully automated. It reads `Formation.approach` and per-slot `strategy` / `spell_priorities`. Empty `{}` / `[]` ⇒ engine defaults from approach + heuristics (same idea as NPC tactics). Schema details: [formation-system.md](formation-system.md#strategy-json-schema-phased). L0 numeric defaults: [Simulation Contract — L0](#l0-defaults-config-backed).

### Layers

| Layer | Behaviour | UI / data today |
|-------|-----------|-----------------|
| **L0** | Defaults from `FormationApproach` (`aggressive` / `balanced` / `defensive`) + positions + equipped gear/spells | Approach radios implemented; slot JSON empty |
| **L1** | Per-hero `target_order` (+ fallback rule, e.g. lowest HP) | Schema frozen; editor later |
| **L2** | `spell_priorities` with conditions (`ally_hp_below`, `always`, …) | Schema frozen; editor later |
| **L3** | Explicit action sequences + advanced conditional tactics (sub, formation switch) | Deferred — game-summary design only |

### Approach weight sketch (L0)

High-level bias (concrete target order and heal thresholds in [L0 defaults](#l0-defaults-config-backed)):

| Approach | Bias |
|----------|------|
| `aggressive` | Prefer focus fire / high damage; less early heal; riskier targets (back line) |
| `balanced` | Mix damage and sustain; default targeting order |
| `defensive` | Prefer protect/heal/defend thresholds; focus remaining threats on front line first |

Config file: planned `config/game/combat_ai_l0.yaml`.

### Decision algorithm (target: L1+, after L0 ship)

Each turn for the active hero:

1. Build candidate actions (basic attack, equipped/prioritised spells that are ready, defend).
2. Score each (action, target) pair using targeting order, HP thresholds, approach weights, and trait/race modifiers.
3. Pick the highest score (ties broken deterministically from seed).

L0 may use a simpler approach→heuristic path without a full scorer; replace with the scorer when L1 lands.

### Implementation phases

| Phase | Deliverable | Replaces / unlocks |
|-------|-------------|-------------------|
| **6.1a** | Contract VO + turn engine + L0 AI + event `combat_log` + seed; plug into `MatchSimulatorInterface` | `StubRandomMatchSimulator` for league |
| **6.1b** | L1 targeting via `strategy.target_order`; freeze JSON schema even if UI still defaults | Real “who hits whom” control |
| **6.1c** | L2 spell conditions; replay viewer MVP (play/pause/speed/skip) | [screen 12](../screens/12-combat-battle.md) |
| **6.1d** | Combat deaths → aging → permanent death → graveyard; durability loss formula | [known-issues](../known-issues.md) #1 remainder |
| **Later** | L3 sequences UI; hybrid snapshots; friendly-match scheduling; synergy tables | Post–core combat |

---

## Summary

Combat runs as a **deterministic, fully automated** simulation (server-side) that reads both teams’ formations and produces an **event-stream** `combat_log` plus final kill scores. The [Simulation Contract](#simulation-contract) defines API layers, VOs, seed, log envelope, and engine vs post-match boundaries. Players only watch a replay; mid-battle decisions are not interactive. AI ships in layers (**L0 approach → L1 targeting → L2 spells → L3 sequences later**). Turn order is determined by speed (SPD); actions resolve per turn with spell and status interactions. Kill-based scoring determines the displayed match result; understaffed teams forfeit without simulation. **Today**, league fixtures use random kill scores via `StubRandomMatchSimulator` while derived stats and post-match processing are production-ready.

---

## API Endpoints (Planned)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v1/combat/simulate` | Practice/sandbox match |
| GET | `/api/v1/battles/{id}` | Battle result |
| GET | `/api/v1/battles/{id}/log` | Combat log / replay |

See [route-map.md](../route-map.md#combat).
