# Kingdom System & Server Split

Reference: [game-summary.md](../game-summary.md#21-kingdom-system--server-split)

Purpose: Document design, data model, APIs, and deployment considerations for Kingdom/Server split.

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
- Kingdom-specific global settings: language, timezone, gameSpeed, startingResources, marketplaceTaxRate, seasonLength, maxPlayers, theme, levelCap, xpModifier, craftingBoost.
- Server tick responsibilities: fatigue recovery, league match processing, dungeon processing, training/crafting queue ticks, marketplace auction processing, hero aging.

APIs (examples):
- GET /api/kingdoms — list kingdoms (id, name, status, playerCount, maxCapacity, settings)
- POST /api/players/{playerId}/kingdom — set kingdom at account creation (validation: capacity)

Implementation notes:
- Partition data by `kingdom_id` in DB queries and indexes.
- Ensure jobs and cron workers run per-kingdom to respect settings and Game Speed multipliers.

