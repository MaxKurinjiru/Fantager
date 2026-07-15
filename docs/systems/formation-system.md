# Formation System

Reference: [game-summary.md](../game-summary.md#261-formation-system)

Purpose: Document formation representation, validation rules, synergy calculations, and simulation hooks.

## Data Model

- **`Formation`**: team-owned lineup template (`name`, `approach`, `is_default`, `is_temporary`, optional `source_fixture_id`)
- **`FormationSlot`**: 6 positions (`front_1`–`front_3`, `back_1`–`back_3`), hero assignment, `strategy` JSON, `spell_priorities` JSON
- **`LeagueFixture`**: nullable `home_formation_id` / `away_formation_id` — `NULL` means *use team default at match resolution*

Temporary match-specific formations (`is_temporary=true`) are created when a player customizes a lineup for a single fixture. They are excluded from the player's saved formation list and are **automatically deleted by kingdom ticks** once the source fixture is completed (`DailyReset` and `LeagueMatch` ticks call `FixtureFormationService::cleanupStaleTemporaryFormationsForKingdom()`).

## Saved Formation Limit

Each team may store up to **4 saved formations** (`FormationService::MAX_SAVED_FORMATIONS`). Temporary match formations do not count toward this limit.

## Validation Rules

- Exactly 6 unique hero IDs required for match-ready lineups; each hero must belong to the team and be available (not in_match, selling, recovering, or dead).
- Team must have ≥ 6 combat-ready heroes to participate in a match at all (see [combat-system.md](combat-system.md#match-eligibility)).
- Creating or promoting a saved formation fails when the team already has 4 saved formations.

## Match Lineup vs Roster

- **Lineup:** 6 heroes in formation (3 front, 3 back)
- **Roster minimum:** 10 heroes at team start; 6 combat-ready required to avoid automatic forfeit

## Fixture Formation Assignment

| Assignment | DB state | Effective at match time |
|:---|:---|:---|
| Use default | `home/away_formation_id = NULL` | Team's `is_default` formation |
| Saved formation | FK to saved formation | That formation |
| Custom for fixture | FK to temporary formation | Temporary copy (deleted after completion) |

## Strategy JSON Schema (Phased)

Per-slot `strategy` and `spell_priorities` are stored as JSON. Today the Formation UI persists empty values (`strategy: {}`, `spell_priorities: []`) and formation-level `approach` (`aggressive` | `balanced` | `defensive`). Combat AI phases: [combat-system.md — Formation AI](combat-system.md#formation-ai-phased).

### Empty / missing → engine defaults

If `strategy` is `{}` or `spell_priorities` is `[]`, the combat engine applies **L0 defaults** derived from `Formation.approach` and position (front vs back). No client-side required fields for match resolution.

### L1 — targeting (`strategy`)

```json
{
  "target_order": ["back_2", "back_1", "back_3", "front_1", "front_2", "front_3"],
  "fallback": "lowest_hp"
}
```

| Field | Meaning |
|-------|---------|
| `target_order` | Preferred enemy **slots** (same position enum as formation) in focus order; skip dead/absent |
| `fallback` | When no entry in `target_order` is valid — e.g. `lowest_hp`, `highest_threat` (engine-defined) |

### L2 — spell priorities (`spell_priorities`)

Ordered list; first matching ready spell wins that evaluation pass (exact precedence documented with the engine):

```json
[
  {
    "spell_id": 12,
    "when": "ally_hp_below",
    "threshold": 40,
    "target": "lowest_hp_ally"
  },
  {
    "spell_id": 8,
    "when": "always",
    "target": "priority"
  }
]
```

| `when` (planned) | Trigger |
|------------------|---------|
| `always` | Eligible whenever the spell is ready |
| `ally_hp_below` | Any / chosen ally under `threshold` % HP |
| `self_hp_below` | Caster under `threshold` % HP |
| `enemy_status` | Optional later — enemy has listed status |

| `target` (planned) | Resolve to |
|--------------------|------------|
| `priority` | Current targeting pick from `strategy` |
| `lowest_hp_ally` / `self` / `lowest_hp_enemy` | Fixed rules |

Formation-level spell overrides vs hero-equipped fallback follow [game-summary.md](../game-summary.md#combat-strategy-settings) (formation config wins when present).

### L3 — deferred

Explicit action sequences and advanced conditional tactics (substitution, mid-match formation switch) remain design-only until L0–L2 ship. Do not invent L3 field names in production code until this section is extended.

## Summary

Formations are 6-slot layouts (3 front, 3 back) with per-hero action priority and spell/targeting settings. Synergy calculations (planned) consider race relationships and role balance.

**Combat integration:** Combat is fully automated — the engine reads `approach` and (phased) strategy / spell priorities and runs the match without mid-battle player input. Snapshot building and match request shape: [combat-system.md — Simulation Contract](combat-system.md#simulation-contract). The battle UI is a replay of `combat_log` only; see [screens/12-combat-battle.md](../screens/12-combat-battle.md).

## APIs

- `GET/PUT/DELETE /api/v1/formations` — list, save, delete saved formations
- `GET/PUT /api/v1/fixtures/{id}/formation` — read/update fixture assignment (`mode`: `default` \| `saved` \| `custom`)
- `POST /api/v1/fixtures/{id}/formation/promote` — promote temporary match formation to saved (counts toward limit of 4)
- `POST /api/v1/formations/simulate` — **planned**

## Implementation Notes

- Combat engine should resolve lineups via `FixtureFormationService::resolveFormation()` and finalize fixtures via `LeagueFixtureCompletionService::complete()`.
- Temporary formation cleanup is **not** synchronous on completion; kingdom ticks remove stale temps for completed fixtures.
- Deleting a temporary formation clears fixture FKs; battle/dungeon formation FKs use `ON DELETE SET NULL`.
