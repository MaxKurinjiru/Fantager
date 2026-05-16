# Graveyard System

Reference: [game-summary.md](../game-summary.md#213-graveyard-system)

Purpose: Document permanent death, logging, and memorialization mechanics.

Sections to fill:
- Graveyard model and retention
- Permanent death flow and triggers
- Statistics and leaderboards integration
- Exporting memorial data
- Implementation notes



Summary:
- When a hero dies permanently, record immutable data (name, race, final level, age, cause, stats) in Graveyard for display and statistics. Ensure this is final and remove hero from active rosters.

APIs:
- GET /api/graveyard — list of fallen heroes

