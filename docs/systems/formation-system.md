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

## Summary

Formations are 6-slot layouts (3 front, 3 back) with per-hero action priority and spell/targeting settings. Synergy calculations (planned) consider race relationships and role balance.

## APIs

- `GET/PUT/DELETE /api/v1/formations` — list, save, delete saved formations
- `GET/PUT /api/v1/fixtures/{id}/formation` — read/update fixture assignment (`mode`: `default` \| `saved` \| `custom`)
- `POST /api/v1/fixtures/{id}/formation/promote` — promote temporary match formation to saved (counts toward limit of 4)
- `POST /api/v1/formations/simulate` — **planned**

## Implementation Notes

- Combat engine should resolve lineups via `FixtureFormationService::resolveFormation()` and finalize fixtures via `LeagueFixtureCompletionService::complete()`.
- Temporary formation cleanup is **not** synchronous on completion; kingdom ticks remove stale temps for completed fixtures.
- Deleting a temporary formation clears fixture FKs; battle/dungeon formation FKs use `ON DELETE SET NULL`.
