# Dungeon System

> [!NOTE]
> **Future Feature**: The Dungeon System is planned for a future release (Phase 7 of the roadmap). It is not implemented, active, or expected to function in the current version of the project. The entities and endpoints listed below are for architectural reference only.

Reference: [game-summary.md](../game-summary.md#215-dungeon-system)

Purpose: Detail dungeon mechanics, rewards, and run processing.

## Dungeon Run Entity

Dungeon runs are represented and logged in the database using the `DungeonRun` entity.

### DungeonRun Entity
Tracks the state, configuration, and results of a dungeon encounter.
* **kingdom** (`Kingdom` relation): The kingdom/server where the dungeon run takes place.
* **team** (`Team` relation): The team executing the dungeon run.
* **dungeonKey** (`string`): Reference key to the configuration of the specific dungeon (e.g., loaded from `config/game/dungeons/*.yaml`).
* **formation** (`Formation` relation, nullable): The lineup configuration/formation used for the dungeon run.
* **result** (`DungeonResult` enum): `win`, `loss`, or `abandoned`.
* **rewardsXp** (`int`): Experience points awarded to participating heroes.
* **rewardsEssence** (`int`): Rarity-specific essence awarded to the team's wallet.
* **rewardsItems** (`json` array): List of item definitions awarded as loot.
* **completedAt** (`DateTimeImmutable`, nullable): Timestamp when the run was completed or resolved.

---

## Dungeon Mechanics

1. **Definitions**: Dungeons are defined in game configuration files (`config/game/dungeons/*.yaml`) containing enemy levels, scaling, and loot tables.
2. **Participation**: A team selects a dungeon and a formation of combat-ready heroes to start the dungeon run.
3. **Resolution**: Dungeon runs simulate battles against scaling enemy encounters. The final result resolves as `win` or `loss`, and rewards are credited to the team and heroes on completion.

---

## APIs (v1)

* `POST /api/v1/dungeons/enter` — Start a dungeon run.
* `GET /api/v1/dungeons/{runId}/result` — Get details of a completed dungeon run, including combat logs and rewards.

