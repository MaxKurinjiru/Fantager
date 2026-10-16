# Friendly Matches Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [friendly-match-system.md](../systems/friendly-match-system.md), [18-arena-management.md](18-arena-management.md)

**When:** Challenge another kingdom team to a PvP friendly (Wed/Sun 18:00 windows in the system doc).

**Status:** Live at `GET /app/friendly-matches`. Full combat consequences. Arena HQ panel links here. Do not add extra windows or casual “no consequence” friendlies without a design decision.

**Live UI:** `templates/friendly_matches/index.html.twig`, Stimulus `friendly_matches`.

---

## Implemented

### Challenge form

- Opponent `<select>` (kingdom teams)
- Slot radios from `GET /api/v1/arena/friendly/slots` after opponent pick
- Submit → `POST /api/v1/arena/schedule-match`

### Tabs (`?tab=`)

Upcoming / pending invites (with counts) / history. Empty state per tab.

### Row

Home vs away, kickoff, status, you-home / you-away.

| Status | Actions |
|--------|---------|
| `pending` and you are away | Accept, decline |
| `pending` or `scheduled` and you play | Cancel |
| `scheduled` and you play | **Formations** → `/app/formation` (saved lineups; not a `fixture_id` match-prep URL) |
| `completed` with a battle | View battle report |

Friendly-match formation API (`/api/v1/friendly-matches/{id}/formation`) exists for programmatic lineup; the list CTA opens the general formation screen.

### Backend

| Layer | Path |
|-------|------|
| Web | `Web\FriendlyMatchController` — `GET /app/friendly-matches` |
| API | `Api\V1\ArenaController` (`/api/v1/arena/friendly*`), `FriendlyMatchFormationController` |
| Service | `src/Service/FriendlyMatch/` |

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/friendly_matches/index.html.twig` |
| Stimulus | `friendly_matches` |
