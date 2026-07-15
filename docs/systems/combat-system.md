# Combat System

Reference: [game-summary.md](../game-summary.md#210-combat-system)

Purpose: Document combat simulation, match eligibility, scoring, derived combat stats, turn order, status effects, and result processing.

---

## Automation Model

Combat is **fully automated**. Players configure behaviour **before** the match via the [Formation System](formation-system.md) (`approach` today; per-slot targeting / spell priorities in later AI layers). The engine simulates the entire bout server-side; the UI is a **replay viewer** over `combat_log` — see [screens/12-combat-battle.md](../screens/12-combat-battle.md).

There is **no** mid-battle player input (no turn submission, target picking, Auto-Battle toggle, or surrender during simulation). Architecture, log format, and AI phases: [Engine Architecture Decisions](#engine-architecture-decisions) and [Formation AI](#formation-ai-phased).

---

## Implementation Status

| Component | Status | Notes |
|-----------|--------|-------|
| Match eligibility & forfeit rules | ✅ Implemented | `LeagueMatchResolutionService::resolveForfeitOutcome()` |
| Kill-based scoring & standings | ✅ Implemented | `StubRandomMatchSimulator` until full engine ships |
| `Battle` entity persistence | ✅ Implemented | Scores, formations, result enum |
| Post-match side effects | ✅ Implemented | Standings, fan club, morale, hero/team chronicle, mastery XP |
| `CombatStatCalculator` + `DerivedCombatStats` | ✅ Implemented | Profile-aware (`Equipped`, `HumanNeutral`, `FullIntrinsic`) |
| Deterministic turn engine | ⏳ Pending | Milestone 6 Step 6.1 — see [Engine Architecture Decisions](#engine-architecture-decisions) |
| `combat_log` JSON + replay UI | ⏳ Pending | Event-stream format decided; UI pending |
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

## Engine Architecture Decisions

Decided model for Milestone 6 Step 6.1. Do not invent alternate formats without updating this section.

### Combat log format — event stream

`Battle.combat_log` is an **append-only event stream** (not per-turn full snapshots).

**Rationale:** Smaller payloads, easy unit tests, replay seek via replay+reduce; hybrid snapshots can be added later if UI needs fast scrubbing.

#### Envelope (planned)

```json
{
  "version": 1,
  "seed": 18446744073709551615,
  "match_type": "league",
  "teams": {
    "a": { "team_id": 1, "formation_id": 10, "approach": "balanced" },
    "b": { "team_id": 2, "formation_id": 11, "approach": "aggressive" }
  },
  "lineup": { },
  "events": [ ]
}
```

| Field | Role |
|-------|------|
| `version` | Schema version; replay client must understand or refuse |
| `seed` | Deterministic PRNG seed for the simulation |
| `lineup` | Slot → hero identity / starting snapshot (ids, positions) for replay labels |
| `events` | Ordered combat events |

#### Event types (v1 set)

| `type` | Purpose |
|--------|---------|
| `match_start` / `match_end` | Boundaries; `match_end` carries final kill scores |
| `round_start` | Round index |
| `turn_start` | Whose turn (`actor` slot / hero id), initiative |
| `attack` / `spell` / `defend` / `heal` | Chosen action + target(s) |
| `hit` / `miss` / `crit` | Attack resolution |
| `damage` / `heal_applied` | Numeric change + remaining HP |
| `status_applied` / `status_tick` / `status_expired` | Status effects |
| `ko` / `revive` | Lineup removal / mid-match resurrection |
| `morale_change` | Per-hero or team morale delta |
| `kill_score` | Optional incremental score update |

Payload fields are type-specific; keep events small. The replay viewer reconstructs HP/status by folding events (see [screens/12-combat-battle.md](../screens/12-combat-battle.md)).

#### Planned extension — hybrid snapshots

If replay seek becomes costly: emit optional `snapshot` events every N turns or after each `ko`, without replacing the event stream as source of truth.

### Determinism and RNG

- Simulation is **seeded**: same formations + same seed ⇒ same log and scores (modulo intentional engine version bumps).
- Store `seed` in the log envelope and/or battle metadata.
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

Combat AI is fully automated. It reads `Formation.approach` and per-slot `strategy` / `spell_priorities`. Empty `{}` / `[]` ⇒ engine defaults from approach + heuristics (same idea as NPC tactics). Schema details: [formation-system.md](formation-system.md#strategy-json-schema-phased).

### Layers

| Layer | Behaviour | UI / data today |
|-------|-----------|-----------------|
| **L0** | Defaults from `FormationApproach` (`aggressive` / `balanced` / `defensive`) + positions + equipped gear/spells | Approach radios implemented; slot JSON empty |
| **L1** | Per-hero `target_order` (+ fallback rule, e.g. lowest HP) | Schema frozen; editor later |
| **L2** | `spell_priorities` with conditions (`ally_hp_below`, `always`, …) | Schema frozen; editor later |
| **L3** | Explicit action sequences + advanced conditional tactics (sub, formation switch) | Deferred — game-summary design only |

### Approach weight sketch (L0)

| Approach | Bias |
|----------|------|
| `aggressive` | Prefer focus fire / high damage; less early heal; riskier targets (back line) |
| `balanced` | Mix damage and sustain; default targeting order |
| `defensive` | Prefer protect/heal/defend thresholds; focus remaining threats on front line first |

Exact numeric weights live in engine code/config once implemented; document them here when checked in.

### Decision algorithm (target: L1+, after L0 ship)

Each turn for the active hero:

1. Build candidate actions (basic attack, equipped/prioritised spells that are ready, defend).
2. Score each (action, target) pair using targeting order, HP thresholds, approach weights, and trait/race modifiers.
3. Pick the highest score (ties broken deterministically from seed).

L0 may use a simpler approach→heuristic path without a full scorer; replace with the scorer when L1 lands.

### Implementation phases

| Phase | Deliverable | Replaces / unlocks |
|-------|-------------|-------------------|
| **6.1a** | Turn engine + L0 AI + event `combat_log` + seed; plug into `MatchSimulatorInterface` | `StubRandomMatchSimulator` for league |
| **6.1b** | L1 targeting via `strategy.target_order`; freeze JSON schema even if UI still defaults | Real “who hits whom” control |
| **6.1c** | L2 spell conditions; replay viewer MVP (play/pause/speed/skip) | [screen 12](../screens/12-combat-battle.md) |
| **6.1d** | Combat deaths → aging → permanent death → graveyard; durability loss formula | [known-issues](../known-issues.md) #1 remainder |
| **Later** | L3 sequences UI; hybrid snapshots; friendly-match scheduling; synergy tables | Post–core combat |

---

## Summary

Combat runs as a **deterministic, fully automated** simulation (server-side) that reads both teams’ formations and produces an **event-stream** `combat_log` plus final kill scores. Players only watch a replay; mid-battle decisions are not interactive. AI ships in layers (**L0 approach → L1 targeting → L2 spells → L3 sequences later**). Turn order is determined by speed (SPD); actions resolve per turn with spell and status interactions. Kill-based scoring determines the displayed match result; understaffed teams forfeit without simulation. **Today**, league fixtures use random kill scores via `StubRandomMatchSimulator` while derived stats and post-match processing are production-ready.

---

## API Endpoints (Planned)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v1/combat/simulate` | Practice/sandbox match |
| GET | `/api/v1/battles/{id}` | Battle result |
| GET | `/api/v1/battles/{id}/log` | Combat log / replay |

See [route-map.md](../route-map.md#combat).
