# Headquarters System

Reference: [game-summary.md](../game-summary.md#27-headquarters-system)

Purpose: Document HQ facilities, upgrades, passive bonuses, and related APIs.

Sections to fill:
- Facility models and upgrade paths
- Passive bonuses calculation
- Race optimization mechanics
- Upgrade transaction flow and validation
- UI/UX data needs
- Implementation notes



Summary:
- HQ contains facilities (Training, Medical, Library, Forge, Treasury, Barracks, Summoning Chamber, Arena). Each facility grants passive bonuses that scale with level and affect training speed, crafting success, revenue, and capacity.

APIs:
- GET /api/hq/{teamId} — returns facility levels and passive bonuses
- POST /api/hq/{teamId}/upgrade — upgrade facility with validation and cost deduction

