# Combat System

Reference: [product/overview.md](../product/overview.md#combat)

Status: Live (automated engine + post-match replay)  
Screens: [12-combat-battle.md](../screens/12-combat-battle.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Formulas: [combat-formulas.md](combat-formulas.md)  
Open design: none  
Lock: [Locked: no race synergy / no simulate](#locked-no-race-synergy--no-simulate)

Purpose: Live combat contract — eligibility, scoring, hex layout, simulation / `combat_log`, formation AI, post-match boundary. Derived stats live in [combat-formulas.md](combat-formulas.md). Later product decisions (home-advantage): [known-issues.md](../product/known-issues.md) § Later product decisions. Simulate is **Cut** — [Locked: no race synergy / no simulate](#locked-no-race-synergy--no-simulate).

---

## Automation Model

Combat is **fully automated**. Players configure behaviour **before** the match via the [Formation System](formation-system.md) (`approach`, one formation-wide `movement_goal`, and per-slot L1 `target_order` / L2 `spell_priorities`). The engine simulates the entire bout server-side; the UI is a **replay viewer** over `combat_log` — see [screens/12-combat-battle.md](../screens/12-combat-battle.md).

There is **no** mid-battle player input (no turn submission, target picking, Auto-Battle toggle, or surrender during simulation). Architecture, log format, wave orchestration, and AI phases: [Simulation Contract](#simulation-contract), [Engine Architecture Decisions](#engine-architecture-decisions), and [Formation AI](#formation-ai-phased).

---

## Live surface

Kickoff uses `MatchLineupService` (assigned → default → roster fill) and `LeagueMatchResolutionService::resolveForfeitOutcome()`. Live battles persist `MatchType::League` or `MatchType::Friendly`. `MatchType::Arena` is **reserved / unused** — it is not an arena-facility match and must not be written. Arena as a HQ building is ticket gold only (`ArenaService`).

Status effect config: [config/game/status_effects.yaml](../../config/game/status_effects.yaml) — loaded by `StatusEffectConfig` and used by `CombatEngine` for **durations**, **tick damage/heal percents**, and **Haste/Shock speed percents on the initiative sort** (hardcoded fallbacks remain if a key is missing). Other combat modifiers from the YAML (accuracy, skip turn, etc.) are applied via engine logic where implemented. Formulas: [combat-formulas.md](combat-formulas.md). Haste/Shock: [combat-formulas.md — Speed / Initiative](combat-formulas.md#speed--initiative-init).

Locked live rules: round-200 kill-score end, preparation length, fumble-without-retarget. Resurrection (`revive`) and L3 sequences stay out. In-combat `morale_change` / Volatile / Battle Hardened is **Cut**. Race synergy and `POST /api/v1/combat/simulate` are **Cut**. Replay HUD: [12-combat-battle.md](../screens/12-combat-battle.md#locked-replay-hud).

## Locked: no race synergy / no simulate

**#9 Resolved (A7.5 Cut, 16 Oct 2026).** Do not add in-combat racial or role synergy tables. Do not ship `POST /api/v1/combat/simulate`.

| Rule | Meaning |
|------|---------|
| No synergy tables | Combat does not apply race-pair or role-pair match modifiers. `race_relations.yaml` stays summoning / chemistry / training / arena-adaptation only. |
| `ignoresRaceSynergy` unused | Serialized on `DerivedCombatStats` (Loner = true). `CombatEngine` does not read it. Loner player copy is flavor. |
| No sandbox route | `POST /api/v1/combat/simulate` is **Cut** (no controller). Do not ship a sandbox. Friendly matches are the live adjacent path. `CombatEngine::simulate()` stays for tests. |
| Reopen | Explicit product ask required before in-combat synergy or a sandbox (consequences, opponent, one-shot vs cohort). |

---

## Match Eligibility

Before any match is simulated, each team is checked for whether it can **field** a kickoff lineup (`MatchLineupService`):

| Condition | Result |
|-----------|--------|
| Both teams can field 6 unique **combat-ready** heroes | Proceed to simulation |
| Exactly one team cannot | **Automatic forfeit** — eligible team wins **3–0** (kill score); no simulation |
| Both teams cannot | **Automatic draw** — **0–0** kill score; no simulation |

**Combat-ready hero:** active status, not a Trainer, not permanently dead (Graveyard).

A team forfeits when it has **fewer than 6 combat-ready heroes on the roster**. An incomplete saved formation does not forfeit by itself: kickoff uses the assigned lineup if it already has 6 unique combat-ready heroes; otherwise the default if that is match-ready; otherwise remaining slots are filled from the combat-ready roster. Duplicate `hero_id` is rejected at **save** ([formation-system.md](formation-system.md#validation-rules)).

---

## Match Scoring

- Each **kill** (enemy hero removed from the opposing lineup during the match) = **1 point** for the scoring team
- Maximum score per team = **6** (one per enemy lineup slot)
- Forfeit win = **3–0** (half of maximum 6)
- Double forfeit = **0–0**
- **Round cap (locked):** at round 200 the match ends with the kill scores accumulated so far. The higher score wins. Equal scores, including 0–0, are a draw. Remaining HP is not a tiebreak.

**League table points** (Win 3 / Draw 1 / Loss 0) are derived from the match winner/loser/draw, not from kill totals directly.

---

## Combat Flow

1. Formation selection (6 heroes per team, 3 front / 3 back)
2. Roster eligibility check (see above)
3. League match tick: forfeit understaffed fixtures; enqueue **wave cohort** for the rest *(Messenger — see [orchestration](#wave-based-messenger-orchestration))*
4. Workers resolve **round by round in lockstep across the cohort** (max 200 rounds); append to `combat_log`
5. On each battle completion: apply post-match updates (team morale, mastery XP, hero XP, form, fatigue, `matches_played` / wins, aging / permanent death → graveyard, item durability loss)
6. Persist final result on `Battle`; product UI is post-match replay only

*(Messenger waves simulate rounds in lockstep; post-match side effects run on final round completion.)*

---

## Hexagonal Grid Combat Engine (17×11 Landscape)

Combat takes place on a **17×11 2D Hexagonal Grid** (17 columns wide, q = 0..16; 11 rows tall, r = 0..10) using **Flat-Topped Axial Coordinates `(q, r)`**. `CombatEngine::ENGINE_VERSION` is **7** (Haste/Shock YAML speed percents apply to the per-round initiative sort, not AP).

Canonical starting positions are defined in `HexGridService::getInitialHexForSlot()` and mirrored by the battle replay UI. Lane centres are **at least 3 hexes apart** so 7-hex flowers do not overlap. The front row is staggered +1 r vs the back row so a backliner does not share a LoS corridor with its own frontliner.

### Starting Positions
- **Team A (Home / Left):**
  - `front_1` → `(4, 3)`, `front_2` → `(4, 6)`, `front_3` → `(4, 9)`
  - `back_1` → `(1, 2)`, `back_2` → `(1, 5)`, `back_3` → `(1, 8)`
- **Team B (Away / Right):**
  - `front_1` → `(12, 3)`, `front_2` → `(12, 6)`, `front_3` → `(12, 9)`
  - `back_1` → `(15, 2)`, `back_2` → `(15, 5)`, `back_3` → `(15, 8)`

### Unit Footprint (size)
Most races occupy **1 hex** (the centre). **Ent** and **Giant** occupy a **7-hex flower**: the centre plus its 6 neighbours (`Race::hexFootprintRadius() = 1`).

- **Occupancy / pathfinding / LoS:** the full footprint blocks other units and ranged lines.
- **Engagement distance:** edge-to-edge between footprints: `max(0, centreDistance - radiusA - radiusB)`. Melee reach 1 means bodies are adjacent (a 1-hex unit vs a flower is in melee at centre distance 2).
- **Movement:** the centre moves 1 hex per step (still **2 AP**); the whole flower must stay on the map and must not overlap another living footprint.
- All six slots on a side can be Ents/Giants at once — the 17×11 layout is packed for that.

### Action Points (AP) & Movement
- **Action Points per Turn:** `AP = 4 + floor(baseInitiative / 5)`, where `baseInitiative = round(Effective_SPD × 2)` (Ents apply ×0.80 before rounding). Equivalent to `4 + floor(round(SPD × 2) / 5)`, not `4 + floor(SPD / 5)`. Haste/Shock do **not** change AP.
- **Movement Cost:** Moving 1 hex costs **2 AP**.
- **Budget:** On a planning turn the unit spends the whole budget, one hex at a time, and stops early when the movement goal is already met. A unit with `preparationRemaining > 0` does not move.
- **Armor Fatigue:** `heavy_armor` adds **+2 Fatigue** for each hex moved.
- **Pathfinding:** Hexagonal A* routes unit centres around occupied hexes (living combatant footprints). Closing to melee stops at weapon reach rather than walking onto the target body.
- **`seek_cover_ranged`:** Holds engagement at the weapon's max range. Closer than that, the unit steps back. Farther than that, it closes and pathfinding stops at that range, so it does not walk into melee. One formation-wide `movement_goal` is still copied onto every slot.

### Weapon Reach & Line of Sight (LoS)
- **Melee Weapons:** 1 hex engagement reach (footprints adjacent).
- **Polearms & Spears (`spear`, `polearm`):** 2 hex reach (reserved subtypes; current item set is melee/ranged/magic).
- **Ranged Weapons (bows, crossbows) & Spells (staff/wand):** 2 to 5 hex engagement reach + **Line of Sight**.
  - **Line of Sight (LoS):** Cube-rounded hex line interpolation from centre to centre. Intermediate standing units (full footprints) block LoS; the shooter and target bodies do not.

### Directional Shield Defense & Flanking
- **Frontal Shield Block:** Shield status reduction applies to non-flanking attacks.
- **Rear Flanking Damage:** Attacks from the target's rear arc deal **+20% Flanking Damage** and bypass shield cover.

### Charging Synergy (`preparationRemaining`)
- Units charging spells (`duration = 1 + tier`) or drawing bows/crossbows (`duration = 2` / `3`) are **stationary** (planning/movement skipped while preparing).
- Moving to a new hex happens only in the planning phase when no queued action is active.
- Melee attacks require being in reach after movement; otherwise the unit continues closing.

---

## Derived stats

HP, ATK, SP, DEF, RES, INIT, accuracy, dodge, crit, durability scaling, mastery, and trait modifiers: **[combat-formulas.md](combat-formulas.md)**. Do not duplicate formulas here.

`CombatStatCalculator` + `DerivedCombatStats` use the Equipped profile (form on HP, fatigue on KON, morale damage multiplier, Ent KON for HP/armor). Orc +20% melee vs non-Orcs applies when the hit resolves.

---

## Simulation Contract

Align PHP value objects and Messenger orchestration with this section; do not invent alternate shapes without updating the docs.

### API layers

| Layer | Responsibility |
|-------|----------------|
| League match tick / cohort starter | Eligibility + forfeit; create `Battle` + persisted run state for each non-forfeit fixture at `scheduledAt`; start wave orchestration (see [Wave-based Messenger orchestration](#wave-based-messenger-orchestration)). |
| `CombatEngine` (per-round) | Pure **per-round** (and per-turn within a round) resolution via `ProcessCombatRound` — no Doctrine fixtures, no standings writes. Input: run state + round number; output: updated state + appended events. |
| `CombatEngine::simulate()` / `LeagueMatchSimulator` | One-shot helpers retained for **tests**; **live league path uses wave Messenger**, not a single `simulate()` call. Sandbox API is **Cut** ([Locked: no race synergy / no simulate](#locked-no-race-synergy--no-simulate)). |
| Completion handler (`LeagueMatchResolutionService`) | When a battle reaches `match_end`: persist final scores/log, apply post-match side effects (standings, fan club, morale, chronicles, mastery, aging/graveyard, durability). |

Forfeit outcomes stay **outside** the engine (existing `resolveForfeitOutcome()`).

Identity: side **A** = home / `Battle.team_a`; side **B** = away / `Battle.team_b`.

**NPC vs player:** Combat orchestration and the engine do **not** distinguish NPC teams from player teams. NPC “management” is handled only by [npc-simulation-system.md](npc-simulation-system.md) before kickoff (formations, roster, etc.) so that lineups look player-like.

### Value objects

```text
CombatMatchRequest
  sideA, sideB: CombatSide
  matchType: MatchType
  seed: int                         // required
  engineVersion: int = CombatEngine::ENGINE_VERSION  // 7

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

CombatSimulationResult              // transitional one-shot; wave path evolves toward round deltas
  outcome scores via MatchOutcome
  seed: int
  combatLog: array
```

#### `MatchOutcome` extension

```text
MatchOutcome(homeScore, awayScore, isForfeit = false, combatLog = [], seed = null)
```

| Producer | `combatLog` |
|----------|-------------|
| Forfeit | `{ "version": 1, "simulator": "forfeit", "events": [] }` |
| Engine | Full envelope (`simulator`: `combat_engine`) |

`LeagueMatchResolutionService::createBattle()` must persist `$outcome->getCombatLog()` when using the transitional one-shot path.

### Seed

- League: deterministic from fixture context, e.g.  
  `seed = hash_to_u32(fixtureId, seasonId?, scheduledAt timestamp, engineVersion)`.
- Practice / sandbox (`POST /api/v1/combat/simulate`): **not live**. If a later sandbox ships, the client may pass `seed`, or the server picks random and **returns** it. Tests seed `CombatEngine::simulate()` directly.
- Same request + seed + `engineVersion` ⇒ same scores and event stream.
- Under wave processing, persist **mutable RNG state** derived from the seed between rounds so determinism spans Messenger messages.

### Wave-based Messenger orchestration

**Goal:** At a calendar kickoff, all eligible fixtures in that kingdom/slot start together. Combat advances in **global waves of rounds**: round 1 for every active battle, then round 2 for every still-incomplete battle, and so on — not “each match runs all its rounds to completion independently before the next match starts.”

#### Cohort

A **cohort** = all non-forfeit battles created for the same kingdom + `scheduledAt` (one league match tick group).

| Concept | Detail |
|---------|--------|
| Cohort start | League match tick creates battles + run state, appends `match_start`, sets battles to `simulating`, dispatches `CombatWave(scheduledAt, round=1)` |
| Wave N | Dispatch `ProcessCombatRound(battleId, N)` for every battle in the cohort with status `simulating` whose `current_round == N - 1` |
| Wave barrier | Wave **N + 1** may start only when every cohort battle that is still `simulating` has finished round N. **`stalled` and `completed` battles are ignored** by the barrier |
| No wave timeout | Wall-clock timeouts are **not** used — they would break the intended lockstep. Progress relies on successful round handlers and on marking failures as `stalled` |
| Max rounds | **`MAX_ROUNDS = 200`**. If neither side has a decisive kill-score outcome by then, force `match_end` (draw on kill scores, or current scores as-is) after round 200 |
| Live UI | **Not required** for now. Partial logs may exist on disk for ops/debug; product UI is post-match replay only |

#### Battle / run statuses

| Status | Meaning |
|--------|---------|
| `simulating` | Participates in waves; waiting for or processing the next round |
| `stalled` | Failed a round handler (exception / poison). **Excluded from wave barrier** so other matches continue. Ops resume via `ResumeCombatService` |
| `completed` | `match_end` applied; post-match side effects done |

Persisted **run state** lives on `Battle.run_state` (JSON) plus columns `current_round`, `status`, and `scheduled_at`. The JSON must include at least: RNG state, combatant HP/status snapshots, accumulated events (also mirrored to `combat_log` on completion), scores, and engine status. Cohort key is `scheduledAt` + kingdom on the entity.

#### Message flow

```text
LeagueMatchTick(kingdom, scheduledAt)
  → forfeit understaffed fixtures (sync)
  → create Battle + run state for each eligible fixture
  → Dispatch CombatWave(kingdom, scheduledAt, round=1)

CombatWave(kingdom, scheduledAt, round=N)
  → enqueue ProcessCombatRound(battleId, N) for each simulating battle at current_round N-1
  → when barrier satisfied (all simulating battles finished round N):
        if any simulating remain and N < 200 → Dispatch CombatWave(..., N+1)
        else → nothing (completed/stalled only)

ProcessCombatRound(battleId, N)   // idempotent on (battleId, N)
  → if already applied or status ≠ simulating → no-op ack
  → resolve round N (turns inside the round); append events; current_round = N
  → if match over (one side all KO, or N == 200) → CompleteBattle
  → on failure → set status=stalled, store error; do not retry forever without ops

CompleteBattle(battleId)
  → finalize score + combat_log; fixture completed; post-match side effects
```

#### Isolation and resume (no timeout)

- **Failure isolation:** a battle that throws in `ProcessCombatRound` becomes `stalled` and drops out of the barrier. Other matches keep advancing waves.
- **Hung / lost messages without an error:** there is no wave timeout by design. Operational recovery is: inspect stuck `simulating` battles, mark `stalled` or re-dispatch the missing round, then call `ResumeCombatService::resume(battleId)`.
- **Resume after fix:** `ResumeCombatService::resume(battleId)` runs remaining rounds **solo** (sync loop) until `completed`, without re-joining the original cohort wave. On completion it calls `LeagueMatchResolutionService::completeBattle` when a `LeagueFixture` exists, otherwise `FriendlyMatchResolutionService::completeBattle` for a `FriendlyMatch`. This avoids barrier edge cases when the cohort has already moved on.

#### Within a round

Turn order, actions, damage, and status ticks follow the combat formulas and AI layers. A “round” is one full pass of the turn engine as defined when the turn loop lands (SPD order); the wave coordinator only counts **round index 1…200**.

To expand combat duration and add tactical depth, each active combatant's turn is divided into two phases:
1. **Planning Phase**: If a hero has no active action queued, they select a target and action (basic attack or spell) using AI rules. The action is queued, and the `preparationRemaining` count is initialized based on the action type.
2. **Execution Phase**: If a hero has a queued action, `preparationRemaining` is decremented. If it reaches `0`, the action executes. If the target has been KO'd in the meantime, the action fumbles (fails) with no effect.

##### Preparation & Casting Durations

**Locked.** These waits are intentional: they lengthen matches and make pre-match tactics matter. Do not shorten them, and do not retarget when the queued target dies (that case is `action_fumble`).

- **Physical Melee / Unarmed**: 1 round (plan in round N, execute in N+1).
- **Ranged Bow**: 2 rounds (plan in N, draw/aim in N+1, execute in N+2).
- **Ranged Crossbow**: 3 rounds (plan in N, draw/load in N+1 & N+2, execute in N+3).
- **Spells**: Dynamic casting duration based on the spell's tier: `1 + spell.tier` rounds (e.g. Tier 1 = 2 rounds, Tier 5 = 6 rounds). Catalog T1–T5: [spell-system.md](spell-system.md#locked-catalog-t1t5).
- A unit with `preparationRemaining > 0` does not move or pick a new action.

##### Interruption Mechanics
- **Stun / Freeze**: If a hero is stunned or frozen, their current queued action is immediately cancelled.
- **Silence**: If a hero is silenced and their queued action is a spell, it is immediately cancelled.

### `combat_log` envelope (v1)

```json
{
  "version": 1,
  "simulator": "combat_engine",
  "seed": 987654321,
  "match_type": "league",
  "grid": { "width": 17, "height": 11 },
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
| `simulator` | `combat_engine` \| `forfeit` |
| `seed` | PRNG seed used for this run |
| `engine_version` | `CombatEngine::ENGINE_VERSION` stamped on the match (7 = Haste/Shock on initiative sort) |
| `grid` | `{ width, height }` battlefield size for replay |
| `lineup` | Slot → hero labels for replay UI |
| `events` | Ordered combat events (appended across waves) |
| `result` | Final kill scores (also mirrored on `Battle.score_a/b`) |

### Event types and payloads

Common fields: `t` (monotonic index), `type`; commonly also `round`, `side` (`a`\|`b`), `slot`, `hero_id`.

| `type` | Payload (v1) |
|--------|----------------|
| `match_start` | — |
| `round_start` | `round` |
| `turn_start` | `side`, `slot`, `hero_id`, `initiative` |
| `plan_action` | `side`, `slot`, `hero_id`, `action_type`, `target_side`, `target_slot`, `duration`, optional `spell_name`, optional `weapon_type` |
| `preparing_action_tick` | `side`, `slot`, `hero_id`, `action_type`, `rounds_remaining` |
| `action_interrupted` | `side`, `slot`, `hero_id`, `reason` |
| `action_fumble` | `side`, `slot`, `hero_id`, `target_side`, `target_slot` |
| `attack` | `target_side`, `target_slot` |
| `attack_out_of_range` | `side`, `slot`, `target_side`, `target_slot` — planned attack aborted (reach / min range); no replay highlight required |
| `attack_blocked_los` | `side`, `slot`, `target_side`, `target_slot` — ranged attack blocked by LoS; no replay highlight required |
| `spell` | `spell_id`, `spell_name`, `caster_side`, `caster_slot`, `target_side`, `target_slot` (also common fields `side`, `slot`, `hero_id`) |
| `defend` | — |
| `hit` / `miss` / `crit` | Follows the preceding action event in order |
| `damage` | `target_side`, `target_slot`, `amount`, `hp_after`, `source` (`physical` \| `magical` \| `dot`) |
| `heal_applied` | Same shape as `damage` where applicable |
| `status_applied` / `status_tick` / `status_expired` | `effect`, optional `stacks` |
| `ko` | `side`, `slot`, `hero_id` — awards +1 kill to the opposing side |
| `kill_score` | `score_a`, `score_b` — emit **after each** `ko`, with scores already incremented |
| `match_end` | `score_a`, `score_b`, `rounds` |

Deferred event types: `revive`, hybrid `snapshot`. `morale_change` is **Cut** — do not emit it. Live streaming of partial logs to clients is deferred (replay-only product UI).

Replay reconstructs HP/status by folding `events` — see [screens/12-combat-battle.md](../screens/12-combat-battle.md).

### L0 defaults (config-backed)

`config/game/combat_ai_l0.yaml` supplies the heal thresholds (`CombatAiL0Config`).

| Approach | Default target order (first living enemy) | Action bias |
|----------|-------------------------------------------|-------------|
| `aggressive` | Enemy back row left→right, then front | Attack; heal only if self/ally HP < 25% |
| `balanced` | Enemy front left→right, then back | Attack; heal if ally HP < 40% |
| `defensive` | Enemy front; else highest threat | Heal if ally HP < 50%; else attack *(defend stance deferred — not queued)* |

**Engine today:** `aggressive` uses back-then-front. `balanced` uses front-then-back. `defensive` uses the living front line and, once that line is gone, the survivor with the highest `physicalAttack`. A non-empty `spell_priorities` list is interpreted first (first matching `always` / `self_hp_below` / `ally_hp_below` wins; `enemy_status` and other unknown `when` values never match and are skipped). An empty list picks at most one ready spell: a defensive spell when any living ally is under the approach heal threshold, otherwise the highest-tier offensive spell. Utility spells are not auto-cast. `target_order` and `fallback` (`lowest_hp`, `highest_threat`, default approach order) are already read and outrank the approach.

### Engine vs post-match boundary

| Inside engine / round handlers | On battle completion (`LeagueMatchResolutionService`) |
|--------------------------------|--------------------------------------------------------|
| Round/turn resolution, damage, KO, kill score, event append, RNG state | Standings, fan club, team morale, chronicles, mastery XP, `matches_played` / wins, durability, aging → permanent death → graveyard. Hero XP, form, and fatigue: see [Post-match hero rewards](#post-match-hero-rewards) |

Engine must **not** write graveyard, hero XP, form, fatigue, or item rows directly — the resolution layer applies those side effects after `match_end`.

### Post-match hero rewards

Applied in `LeagueMatchResolutionService::completeBattle()` for a simulated match. A forfeit does not change hero XP, form, or fatigue. Mastery XP is unchanged. XP is a lifetime total on `Hero.xp` (never spent). After the award, `HeroProgressionService` raises stored `Hero.level` from the current curve and `Kingdom.level_cap` ([known-issues.md](../product/known-issues.md) #6). Do not derive combat `level` from XP — snapshots read `Hero.level`.

| Event | Effect |
|-------|--------|
| Participated | fatigue +15 (cap 100), form −8 (floor 0), +20 XP |
| Won the match | +15 XP |
| KO the hero caused (physical or spell; a status-tick KO has no killer) | +10 XP each |
| Was KO'd | extra fatigue +10, extra form −7 |

XP is `round(base × library multiplier × kingdom.xp_modifier)`. The library multiplier is `1 + xp_gain_pct / 100` from the team's Library facility when headquarters bonuses are active. No library, or a financial crisis that suspends HQ bonuses, leaves that multiplier at 1. `Kingdom.xp_modifier` defaults to `1.00`. After the write, stored `Hero.level` may increase — [hero-system.md](hero-system.md#locked-hero-level-invariant).

The elder mortality roll uses the LCG in `CombatEngine::drawInt()`, starting from `combat_log.result.rng_state` (the generator state after the last combat roll). Every id in `killed_hero_ids` consumes one roll, in that order, before the next id. A missing `rng_state` falls back to the combat-log seed, then to 1. The same log state produces the same graveyard outcome.

---

## Engine Architecture Decisions

Request/result shapes, event payloads, and **wave orchestration**: [Simulation Contract](#simulation-contract). Do not invent alternate formats without updating that section.

`Battle.combat_log` is an **append-only event stream** (not per-turn full snapshots). Optional hybrid `snapshot` events are not live — do not add them without a product ask.

### Determinism and RNG

- Simulation is **seeded** (see [Seed](#seed)); same formations + same seed ⇒ same log and scores (modulo intentional `engineVersion` bumps).
- Persist RNG state across Messenger round messages.
- Trait **Perfectionist** removes damage variance for that hero; it does not disable all combat RNG (accuracy, dodge, crit still apply unless separately specified).
- Replay **never re-simulates** for display — it only plays back `events`. Re-sim with seed is for tests/debug only.

### Out of scope

| Deferred | Until |
|----------|--------|
| Mid-match hero substitution | AI layer L3+ / later milestone |
| Mid-match formation switch | L3+ |
| Player surrender / flee during sim | Not applicable (automation model) |
| Per-turn full state snapshots as primary log | Only if hybrid extension ships |
| Full racial / role synergy number tables | **Cut**. Later product decision required |
| Action-sequence script UI | Layer L3 |
| Live / in-progress match UI | Explicitly deferred — replay after completion only |
| Wave wall-clock timeout | Rejected — lockstep without timeout; isolation via `stalled` |

---

## Formation AI (Phased)

Combat AI is fully automated. It reads `Formation.approach` and per-slot `strategy` / `spell_priorities`. Empty `target_order` uses the approach slot order. Empty `spell_priorities` uses the L0 spell pick (heal under the approach threshold, else highest-tier offense). Schema details: [formation-system.md](formation-system.md#strategy-json-schema-phased). L0 numeric defaults: [Simulation Contract — L0](#l0-defaults-config-backed).

### Layers

| Layer | Behaviour | UI / data today |
|-------|-----------|-----------------|
| **L0** | Approach slot order, plus one ready spell when `spell_priorities` is empty (heal under the yaml threshold, else highest-tier offense). Defensive holds the front line, then highest physical attack | Approach radios and one formation-wide `movement_goal` (copied onto every slot) |
| **L1** | Per-hero `target_order` (+ `fallback`: `lowest_hp`, `highest_threat`, or approach order) | Player slot editor. Empty order = approach |
| **L2** | `spell_priorities` with `always`, `self_hp_below`, `ally_hp_below` | Thin player editor for equipped spells. Empty list = L0 pick. NPC may still fill lists. `enemy_status` / `lowest_hp_enemy` Cut from UI |
| **L3** | Explicit action sequences + advanced conditional tactics (sub, formation switch) | Deferred — not live |

### Approach weight sketch (L0)

High-level bias (concrete target order and heal thresholds in [L0 defaults](#l0-defaults-config-backed)):

| Approach | Bias |
|----------|------|
| `aggressive` | Prefer focus fire / high damage; less early heal; riskier targets (back line) |
| `balanced` | Mix damage and sustain; default targeting order |
| `defensive` | Prefer protect/heal/defend thresholds; focus remaining threats on front line first |

Config file: `config/game/combat_ai_l0.yaml`, loaded by `CombatAiL0Config`.

L0–L2, wave orchestration, and graveyard writes are live. L3 sequences, hybrid snapshots, Resurrection, and live match UI stay out of scope (see Out of scope above).

---

## Summary

Combat is a **deterministic, fully automated** simulation. At each calendar kickoff, eligible fixtures form a **cohort** and advance in **Messenger waves of rounds** (round N for all active battles, then N+1), capped at **200 rounds**, without wave timeouts. Failed battles become **`stalled`** so others continue; resume is solo catch-up. The engine produces an append-only **event-stream** `combat_log`; players use **post-match replay** only (no live UI for now). NPC and player teams use the same combat path. The [Simulation Contract](#simulation-contract) defines VOs, seed/RNG state, wave orchestration, and post-match boundaries. AI layers: **L0–L2 live; L3 later**. Kill-based scoring decides the result, including a round-200 finish; understaffed teams forfeit without simulation. Preparation durations and fumble-without-retarget are locked. Permanent combat deaths feed the [Graveyard System](graveyard-system.md).

---

## API Endpoints

| Method | Path | Purpose | Status |
|--------|------|---------|--------|
| GET | `/api/v1/battles/{id}` | Battle result (after completion) | Live |
| GET | `/api/v1/battles/{id}/log` | Combat log / replay | Live |
| POST | `/api/v1/combat/simulate` | Practice/sandbox match | Cut — [Locked: no race synergy / no simulate](#locked-no-race-synergy--no-simulate) |

See [architecture/routes.md](../architecture/routes.md#combat).
