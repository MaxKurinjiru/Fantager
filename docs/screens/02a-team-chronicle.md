# Team Chronicle Screen

Reference: [team-chronicle-system.md](../systems/team-chronicle-system.md), [02-team-dashboard.md](02-team-dashboard.md)

**When:** Player views the full history of their team.

## Route

| Method | Path | Controller |
|--------|------|------------|
| GET | `/app/chronicle` | `App\Controller\Web\TeamChronicleController::index` |

Sidebar: Team & Base → **Team Chronicle** (`nav.chronicle`).

## Displayed Information

- Filter bar: **category**, **event type**, **sort** (newest / oldest first)
- Chronological list of chronicle entries:
  - Event type label
  - Localized message (from `subject_key` + `subject_params`)
  - Timestamp

Empty state when no entries match filters.

## Dashboard Entry Point

The Team Dashboard shows the **5 most recent** entries in `recent_chronicle.html.twig` with a **Full chronicle** link to this screen.

## Backend

- Reads via `TeamChronicleRepository::findByTeamFiltered()`
- Presentation via `TeamChroniclePresenter::presentFilteredForTeam()`
- Writes are never triggered from this screen (read-only)

## Related Docs

- Data model and write hooks: [team-chronicle-system.md](../systems/team-chronicle-system.md)
- Ownership events: [team-system.md](../systems/team-system.md#team-chronicle), [auth-system.md](../systems/auth-system.md)
