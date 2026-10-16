# Formation System

Reference: [product/overview.md](../product/overview.md#formations)

Status: Live (lineup + L1/L2 slot editor; no synergy)  
Screens: [07-formation-setup.md](../screens/07-formation-setup.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none  
Lock: [Locked: L1/L2 editor](#locked-l1l2-editor)

Purpose: Document formation representation, save/kickoff validation, slot strategy JSON, and fixture assignment. **Race/role synergy is not part of this system.** Team chemistry is [team-system.md](team-system.md#locked-chemistry-flavor) (flavor for matches). In-combat tables and a simulate sandbox are Cut ([combat-system.md](combat-system.md#locked-no-race-synergy--no-simulate)). Do not ship `POST /api/v1/formations/simulate`.

## Data Model

- **`Formation`**: team-owned lineup template (`name`, `approach`, `is_default`, `is_temporary`, optional `source_fixture_id`)
- **`FormationSlot`**: 6 positions (`front_1`–`front_3`, `back_1`–`back_3`), hero assignment, `strategy` JSON, `spell_priorities` JSON
- **`LeagueFixture`**: nullable `home_formation_id` / `away_formation_id` — `NULL` means *use team default at match resolution*

Temporary match-specific formations (`is_temporary=true`) are created when a player customizes a lineup for a single fixture. They are excluded from the player's saved formation list and are **deleted when the fixture is marked completed** via `LeagueFixtureCompletionService::complete()` (after forfeit resolution or after async combat `completeBattle`). `DailyReset` still sweeps orphans with `FixtureFormationService::cleanupStaleTemporaryFormationsForTeam()`.

## Saved Formation Limit

Each team may store up to **4 saved formations** (`FormationService::MAX_SAVED_FORMATIONS`). Temporary match formations do not count toward this limit.

## Validation Rules

- **Save:** `FormationService::rebuildSlots()` persists 0–6 slots. Duplicate `hero_id` values are rejected (`error.formation_duplicate_hero`, API 422). Empty slots stay allowed, including a fully empty draft (UI and API).
- **Match-ready (kickoff):** `MatchLineupService` fields 6 unique combat-ready heroes in this order: (1) assigned fixture/friendly formation if all 6 slots are unique combat-ready heroes; (2) else the team default if that is match-ready; (3) else auto-fill remaining empty / duplicate / unready slots from the combat-ready roster (`id` ascending), persisted as a temporary copy (league: `source_fixture_id` for existing cleanup). Forfeit if the team has fewer than 6 combat-ready heroes — see [combat-system.md](combat-system.md#match-eligibility).
- Team must have ≥ 6 combat-ready heroes to participate in a match at all (see [combat-system.md](combat-system.md#match-eligibility)).
- Creating or promoting a saved formation fails when the team already has 4 saved formations.

## Match Lineup vs Roster

- **Lineup:** 6 heroes in formation (`front_1`–`front_3`, `back_1`–`back_3`)
- **Hex Grid Mapping (17×11 Battlefield):** Canonical positions from `HexGridService::getInitialHexForSlot()`:
  - **Team A (Home / Left):** `front_1` (4,3), `front_2` (4,6), `front_3` (4,9), `back_1` (1,2), `back_2` (1,5), `back_3` (1,8).
  - **Team B (Away / Right):** `front_1` (12,3), `front_2` (12,6), `front_3` (12,9), `back_1` (15,2), `back_2` (15,5), `back_3` (15,8).
  - Lane centres are at least 3 hexes apart so Ent/Giant **7-hex flowers** do not overlap at kickoff. Front row is staggered +1 r vs back so a lane's backliner does not share LoS with its frontliner. See [combat-system.md](combat-system.md#unit-footprint-size).
- **Movement strategy:** Formation-wide `strategy.movement_goal` (`advance_to_melee` | `seek_cover_ranged` | `protect_backline` | `flank_rear`) stored on each slot. `seek_cover_ranged` holds the weapon's maximum range.
- **Roster minimum:** 10 heroes at team start; 6 combat-ready required to avoid automatic forfeit

## Fixture Formation Assignment

| Assignment | DB state | Effective at match time |
|:---|:---|:---|
| Use default | `home/away_formation_id = NULL` | Team's `is_default` formation |
| Saved formation | FK to saved formation | That formation |
| Custom for fixture | FK to temporary formation | Temporary copy (deleted after completion) |

## Strategy JSON Schema (Phased)

Per-slot `strategy` and `spell_priorities` are stored as JSON. The Formation UI saves formation-level `approach` (`aggressive` | `balanced` | `defensive`) and copies one `strategy.movement_goal` onto every slot, merged with that slot’s L1 `target_order` / `fallback` and L2 `spell_priorities`. Empty lists keep engine L0 defaults. Combat AI phases: [combat-system.md — Formation AI](combat-system.md#formation-ai-phased). See [Locked: L1/L2 editor](#locked-l1l2-editor).

### Empty / missing → engine defaults

If `target_order` is missing, the engine uses the approach (`aggressive`: back row then front; `balanced`: front row then back; `defensive`: living front row, then the survivor with the highest physical attack). If `spell_priorities` is `[]`, the engine picks one ready equipped spell (heal under the approach threshold, otherwise the highest-tier offensive spell). NPC tactics may still fill `spell_priorities` before kickoff, and that list wins over the L0 pick. No client-side required fields for match resolution.

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

| `when` | Trigger | Engine |
|---------|---------|--------|
| `always` | Eligible whenever the spell is ready | Read |
| `ally_hp_below` | Any living ally under `threshold` % HP | Read |
| `self_hp_below` | Caster under `threshold` % HP | Read |
| `enemy_status` | Enemy has a listed status | **Cut from player UI (A7.6).** Engine **skips** the entry if present; save sanitizer drops it |

| `target` | Resolve to | Engine |
|----------|------------|--------|
| `priority` | Current targeting pick from `strategy` | Read (default when `target` is omitted) |
| `lowest_hp_ally` / `self` | That ally, or the caster | Read |
| `lowest_hp_enemy` | Lowest-HP living enemy | **Cut from player UI (A7.6).** Save sanitizer remaps to `priority` |

Non-empty per-slot `spell_priorities` is evaluated before the L0 pick (equipped spells only). Empty list = L0. Engine: [combat-system.md](combat-system.md#formation-ai-phased). Player editor: [Locked: L1/L2 editor](#locked-l1l2-editor).

### L3 — deferred

Explicit action sequences and advanced conditional tactics (substitution, mid-match formation switch) stay design-only. Do not invent L3 field names in production code until this section is extended.

## Locked: L1/L2 editor

**#12 Resolved (A7.6 Implement, 16 Oct 2026).** Players edit per-slot L1 targeting and thin L2 spell priorities on Formation Setup. Do not add L3 sequences or unmet `when`/`target` values to the UI.

| Rule | Meaning |
|------|---------|
| L1 targeting | Click a pitch slot. Ordered unique enemy slots (`front_1`–`back_3`) plus `fallback` `lowest_hp` / `highest_threat` / `approach`. Empty `target_order` = L0 approach. Fallback is stored only with a non-empty order. |
| Thin L2 | Ordered **equipped** spells for the hero in that slot. Live `when`: `always`, `ally_hp_below`, `self_hp_below` (threshold 1–99). Live `target`: `priority`, `lowest_hp_ally`, `self`. Empty list = L0 spell pick. |
| Movement stays formation-wide | One `movement_goal` is copied onto every slot on save and merged with that slot’s L1 fields. |
| Cut | L3 action sequences / Conditional Tactics UI. `enemy_status`. `lowest_hp_enemy`. Dual-wield targeting lists. Flexible AI. On Low Morale. |
| Save sanitizer | `FormationTacticsNormalizer` keeps only the live schema. NPC-shaped objects and unmet `when` values are dropped. Invalid `target` (including `lowest_hp_enemy`) remaps to `priority`. Duplicate `spell_id` / slot values are collapsed. |
| L2 follows the hero | Swapping or moving a hero moves `spell_priorities` with them. L1 stays on the slot. |

## Summary

Formations are 6-slot layouts (3 front, 3 back) with formation-wide `approach` and per-slot `strategy` / `spell_priorities`. There is **no synergy calculation** on save or at kickoff. Team chemistry (flavor for matches — [#7](../product/known-issues.md) Resolved) is computed from the combatant roster, not from filled slots.

**Combat integration:** Combat is fully automated — the engine reads `approach` and (phased) strategy / spell priorities and runs the match without mid-battle player input. Snapshot building and match request shape: [combat-system.md — Simulation Contract](combat-system.md#simulation-contract). The battle UI is a replay of `combat_log` only; see [screens/12-combat-battle.md](../screens/12-combat-battle.md).

## APIs

- `GET/PUT/DELETE /api/v1/formations` — list, save, delete saved formations
- `GET/PUT /api/v1/fixtures/{id}/formation` — read/update fixture assignment (`mode`: `default` \| `saved` \| `custom`)
- `POST /api/v1/fixtures/{id}/formation/promote` — promote temporary match formation to saved (counts toward limit of 4)
- `POST /api/v1/formations/simulate` — **Cut** (no controller); do not ship a sandbox

**Tests:** `tests/Service/Formation/FormationServiceTest.php`, `FormationTacticsNormalizerTest.php`, `MatchLineupServiceTest.php`, `FixtureFormationServiceTest.php`.

## Implementation Notes

- Player UI and fixture assignment still read via `FixtureFormationService::resolveFormation()` (assigned or default, unrepaired). **Kickoff / simulation** uses `MatchLineupService::resolveForKickoff()` so the engine always receives 6 unique combat-ready heroes. Finalize fixtures via `LeagueFixtureCompletionService::complete()`.
- Temporary formation cleanup runs in `LeagueFixtureCompletionService::complete()` **after** the fixture is `Completed` (and after post-match rewards have used Battle formation FKs). Do **not** clean up on `LeagueMatch` kickoff while combat is still simulating. `DailyReset` is an orphan safety net.
- Deleting a temporary formation clears fixture FKs; battle formation FKs use `ON DELETE SET NULL`.
