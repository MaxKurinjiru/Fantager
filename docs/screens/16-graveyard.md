# Graveyard Screen

Reference: [screens-overview.md](../screens-overview.md#16-graveyard-screen), [graveyard-system.md](../systems/graveyard-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

> **Status: Implemented.** Memorial records are written on hero/trainer dismissal; the graveyard page and read API are available.

Displayed Information:
- Graveyard List:
	- Name, race, role (combatant / trainer), final level, age at departure
	- Cause (`MemorialCause`), date of departure
- Statistics Summary:
	- Total memorial records, average age at departure
- Memorial detail panel (`?id=`)

Possible Actions/Buttons:
- View memorial detail
- Filter by role, race, cause
- Search by name

Backend (implemented):
- Dismiss hero — `POST /api/v1/heroes/{id}/dismiss` → `GraveyardService::recordMemorial()`
- Dismiss trainer — `POST /api/v1/training/trainers/{id}/dismiss` → same service
- `GET /app/graveyard` — memorial wall
- `GET /api/v1/graveyard` — list memorials + summary
- `GET /api/v1/graveyard/{id}` — detail

Implementation notes:
- Dismiss actions complete in-place on Hero detail / Training page; players can review memorials on `/app/graveyard`
- Combat death memorials will appear automatically once the combat engine is implemented
