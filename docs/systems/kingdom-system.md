# Kingdom System & Server Split

Reference: [game-summary.md](../game-summary.md#21-kingdom-system--server-split)

Purpose: Document design, data model, APIs, and deployment considerations for Kingdom/Server split.

## Key Design Decision: Kingdom as Shared Game World

**Kingdom = one game world (server instance) shared by many players.**

Each player (User + Team) belongs to exactly one Kingdom, chosen once at account creation (after email verification). A Kingdom is **not** owned by a single player — it is a shared environment with its own economy, events, and league.

- Relationship: `Kingdom (1) → Users (N)`, `Kingdom (1) → Teams (N)`, `User (1) → Team (1)`
- The player chooses a Kingdom as part of the **registration form** (permanent, cannot be changed later).
- After email verification, the player is assigned a random available NPC team within their chosen Kingdom.
- `kingdom_id` on User and Team scopes all data queries, background jobs, and API calls to the correct game world.

## Kingdom Initialization

When a new Kingdom is created (by an admin or automated setup), the following steps happen automatically in order:

1. **Kingdom is persisted** with all settings (language, timezone, game_speed, `league_tiers_config`, etc.).
2. **First LeagueSeason is created** (`season_number = 1`, status `upcoming` or `active` depending on the start date). `LeagueTier` and `LeagueGroup` rows are created from `league_tiers_config` — the bracket skeleton exists but is empty.
3. **NPC teams are created and immediately seeded** into the league groups. Each team (`is_npc = true`, `user_id = NULL`) is created with generated name and emblem and placed directly into a `LeagueGroup`, and a `LeagueStanding` row is created for it. All groups are filled in one pass.
4. **Heroes are generated and assigned** to each NPC team — **10 heroes per team**, fully staffed and functional before the Kingdom opens to players.

When a real player verifies their email, one randomly selected NPC team (with `user_id IS NULL`) in that Kingdom is assigned to the player immediately and automatically — no extra action required from the player. The team's `user_id` is set to the user, and `is_npc` is immediately set to `false` to reflect that it is now a player-managed team.

Sections to fill:
- Overview & purpose
- Data model (tables, key fields)
- API endpoints (routes, payloads)
- Server provisioning and isolation
- Game-speed and tick-rate handling
- Migration and cross-kingdom considerations (if any)
- Tests and integration points
- Implementation notes (folders, candidate services)

Summary (to extract from game-summary):
- Kingdom-specific global settings: language, timezone, gameSpeed, startingResources, marketplaceTaxRate, seasonLength, **leagueTiersConfig** (tiers, groups per tier, teams per group — player capacity is derived from this), theme, levelCap, xpModifier, craftingBoost.
- Server tick responsibilities: fatigue recovery, league match processing, dungeon processing, training/crafting queue ticks, marketplace auction processing, hero aging.

APIs (examples):
- GET /api/kingdoms — list kingdoms (id, name, status, playerCount, **capacity** (computed from leagueTiersConfig), settings)
- POST /api/players/{playerId}/kingdom — set kingdom at account creation (validation: capacity)

Implementation notes:
- Partition data by `kingdom_id` in DB queries and indexes.
- Ensure jobs and cron workers run per-kingdom to respect settings and Game Speed multipliers.

