# Graveyard Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#16-graveyard-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [graveyard-system.md](../systems/graveyard-system.md)

**When:** Review memorials for dismissed heroes/trainers and permanent combat deaths.

**Status:** Live at `GET /app/graveyard`. Writes happen on dismiss and combat death, not from this page. Filter options include reserved `MemorialCause` values `age`, `retired`, `death` (A6.2) that current writers do not emit — live causes are **dismissed** and **combat_death**.

**Live UI:** `templates/graveyard/index.html.twig`.

---

## Implemented

### Summary

- Total memorials, average age at departure (or —)

### Filters (GET, auto-submit)

Role, cause, race, name search. Pagination.

### List

Each entry: name, role badge, trait if any, date, race, final level, age, cause, **View detail** (`?id=`).

### Detail (`?id=`)

Name, race, role, cause, trait, date, age, final level, final stats. Close returns to the filtered list.

Empty state when the filter matches nothing.

### Backend

| Method | Path |
|--------|------|
| GET | `/app/graveyard` |
| GET | `/api/v1/graveyard` |
| GET | `/api/v1/graveyard/{id}` |

Writers: `POST /api/v1/heroes/{id}/dismiss`, `POST /api/v1/training/trainers/{id}/dismiss`, `LeagueMatchResolutionService` → `GraveyardService` (`MemorialCause::CombatDeath`).

---

## Planned

Original sketch extras — do not treat as a contract:

- Share memorial / social
- Sort by battles
- “Most battles fought” legend stat
- Gravestone visualization beyond the list
- Age / retired / death causes as live writers (reserved enums)

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/graveyard/index.html.twig` |
| Stimulus | `form-auto-submit` |
