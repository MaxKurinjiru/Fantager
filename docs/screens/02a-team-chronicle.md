# Team Chronicle Screen

Reference: [team-chronicle-system.md](../systems/team-chronicle-system.md), [02-team-dashboard.md](02-team-dashboard.md), [architecture/screen-code-map.md](../architecture/screen-code-map.md)

**When:** Full team event log (dashboard shows only the last five).

**Status:** Live at `GET /app/chronicle`. Read-only. Writes never start from this page.

**Live UI:** `templates/team_chronicle/`. Sidebar: Team & Base → Team Chronicle (`nav.chronicle`).

---

## Implemented

### Displayed information

- Filter bar: category, event type, sort (newest / oldest)
- Chronological entries: event-type label, localized message (`subject_key` + `subject_params`), timestamp
- Empty state when nothing matches

Dashboard: last **5** entries in `recent_chronicle.html.twig` with **Full chronicle** → this screen.

### Backend

| Piece | Location |
|-------|----------|
| Web | `GET /app/chronicle` → `Web\TeamChronicleController::index` |
| Read | `TeamChronicleRepository::findByTeamFiltered()` |
| Present | `TeamChroniclePresenter::presentFilteredForTeam()` |

No chronicle REST API; page is server-rendered.

---

## Planned

None for this screen. Notification locale-at-read is A6.4 (navbar notifications, not chronicle).

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/team_chronicle/` |
| Stimulus | — |
