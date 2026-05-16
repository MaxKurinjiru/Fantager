# Combat System

Reference: [game-summary.md](../game-summary.md#210-combat-system)

Purpose: Document combat simulation, turn order, status effects, and result processing.

Sections to fill:
- Combat engine architecture (worker/service)
- Turn resolution and speed order
- Damage, healing, and status effect rules
- Logging and replay format
- Performance and scaling considerations
- Implementation notes

API endpoints
- POST /api/combat/simulate — returns deterministic simulation result for preview
- GET /api/combat/{matchId}/log — returns combat log/replay


Summary:
- Combat runs in a deterministic simulation engine (server-side worker) producing event logs and final results. Turn order determined by speed; actions resolved per-turn with spell and status interactions.

APIs:
- POST /api/combat/simulate — run simulation for practice
- GET /api/combat/{matchId}/log — retrieve combat log/replay

