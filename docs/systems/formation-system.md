# Formation System

Reference: [game-summary.md](../game-summary.md#261-formation-system)

Purpose: Document formation representation, validation rules, synergy calculations, and simulation hooks.

Sections to fill:
- Formation data model
- Positioning rules and constraints
- Synergy and race relationship effects
- Validation and error states
- Simulation/testing endpoints
- Implementation notes


Validation rules:
- Exactly 6 unique hero IDs required; each hero must belong to the team and be available (not in training/match).
- Team must have ≥ 6 combat-ready heroes to participate in a match at all (see [combat-system.md](combat-system.md#match-eligibility)).

Match lineup vs roster:
- **Lineup:** 6 heroes in formation (3 front, 3 back)
- **Roster minimum:** 10 heroes at team start; 6 combat-ready required to avoid automatic forfeit


Summary:
- Formations are 6-slot layouts (3 front, 3 back) with per-hero action priority and spell/targeting settings. Synergy calculations consider race relationships and role balance.

APIs:
- GET/PUT /api/teams/{id}/formations — get and save formations
- POST /api/formation/simulate — run a formation simulation (returns expected outcomes)

