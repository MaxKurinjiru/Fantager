# Graveyard Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [graveyard-system.md](../systems/graveyard-system.md)

**When:** Review memorials for dismissed heroes/trainers and permanent combat deaths.

**Status:** Live at `GET /app/graveyard`. Writes happen on dismiss and combat death, not from this page. Cause filters are live values **dismissed** and **combat_death** (`MemorialCause::liveCases()`). Reserved `age`, `retired`, `death` stay on the enum but are not offered as filter options.

**Live UI:** `templates/graveyard/index.html.twig`.

---

## Implemented

### Summary

- Total memorials, average age at departure (or —), count per live cause (dismissed, combat death)

### Filters (GET, auto-submit)

Role, cause, race, name search. Pagination keeps the current page on detail open and close.

### List

Each entry: name, role badge, trait if any, date, race, final level, age, cause, matches played, wins, times fallen, **View detail** (`?id=`). When the hero row still exists: **View hero** / **View trainer** and **View history** (`/app/heroes/{id}`, history tab).

### Detail (`?id=`)

Name, race, role, cause, trait, date, age, final level, matches, wins, times fallen, final stats. Same profile links. Close returns to the filtered list on the same page.

Empty state when the team has no memorials. A different empty state when filters match nothing.

### Backend

| Method | Path |
|--------|------|
| GET | `/app/graveyard` |
| GET | `/api/v1/graveyard` |
| GET | `/api/v1/graveyard/{id}` |

Writers: `POST /api/v1/heroes/{id}/dismiss`, `POST /api/v1/training/trainers/{id}/dismiss`, league and friendly resolution → `GraveyardService` (`MemorialCause::CombatDeath`). Dismissal and combat death set status `dead` and keep the hero row.

---

## Sketch leftover

Not a contract:

- Share memorial / social
- Sort by battles
- “Most battles fought” legend stat
- Gravestone visualization beyond the list

`MemorialCause::{Age, Retired, Death}` stay **Reserved** — no live writers.

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/graveyard/index.html.twig` |
| Stimulus | `form-auto-submit` |
