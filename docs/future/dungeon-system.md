# Dungeon System (Deferred)

> **Status:** Not implemented. The dungeon backend was removed from the codebase; this document preserves the intended design for a future phase (after the combat engine is complete). Reserved enums (`MatchType::Dungeon`, `FinancialRecordType::DungeonReward`, `ChronicleEventType::DungeonCompleted`) remain in code for forward compatibility.

Reference: [game-summary.md](../game-summary.md#215-dungeon-system)

Purpose: Detail dungeon mechanics, rewards, and run processing.

## Proposed Data Model

### `DungeonRun` (table: `dungeon_run`)

| Field | Type | Notes |
|-------|------|-------|
| `id` | INT | Primary key |
| `kingdom_id` | FK → Kingdom | Server scope |
| `team_id` | FK → Team | Executing team |
| `dungeon_key` | VARCHAR(100) | Reference to `config/game/dungeons/*.yaml` |
| `formation_id` | FK → Formation (nullable) | Lineup used for the run |
| `result` | enum (`DungeonResult`) | `win`, `loss`, or `abandoned` |
| `rewards_xp` | INT | XP awarded to participating heroes |
| `rewards_essence` | INT | Essence awarded to team wallet |
| `rewards_items` | JSON | Loot item definitions |
| `completed_at` | DATETIME (nullable) | When the run was resolved |

---

## Dungeon Mechanics

1. **Definitions**: Dungeons are defined in game configuration files (`config/game/dungeons/*.yaml`) containing enemy levels, scaling, and loot tables.
2. **Participation**: A team selects a dungeon and a formation of combat-ready heroes to start the dungeon run.
3. **Resolution**: Dungeon runs simulate battles against scaling enemy encounters. The final result resolves as `win` or `loss`, and rewards are credited to the team and heroes on completion.

---

## Planned APIs (v1)

* `POST /api/v1/dungeons/enter` — Start a dungeon run.
* `GET /api/v1/dungeons/{runId}/result` — Get details of a completed dungeon run, including combat logs and rewards.

---

## Planned Web Routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/dungeons` | Dungeon selection page |

See also [route-map.md](../route-map.md#dungeon-future-feature---planned).
