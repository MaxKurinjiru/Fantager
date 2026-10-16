# Calendar Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [calendar-system.md](../systems/calendar-system.md), [18a-friendly-matches.md](18a-friendly-matches.md)

**When:** Weekly kingdom schedule (ticks, league fixtures, own friendly matches).

**Status:** Live at `GET /app/calendar`. Feed is JSON. Per-hero training completions are **not** calendar rows — weekly training is a `system_tick`; results live on Training / hero history.

**Live UI:** `templates/calendar/index.html.twig`, Stimulus `calendar`.

---

## Implemented

### Displayed information

- Week navigation (previous / next) and range label
- Toggles: team-only entries, show system ticks
- Per-day groups: weekday, date, today marker, event cards or empty day
- Entry types from `CalendarService::getCalendarFeed()`:
  - `system_tick` — recurring ticks (`TickScheduleCalculator` + tick logs). `TickType::LeagueMatch` and `TickType::FriendlyMatch` are **skipped** here so they are not duplicated
  - `league_match` — kingdom fixtures in the window (same kickoff slot batched in the UI)
  - `friendly_match` — the viewer’s matches only (`team_only`; requires `teamId`)

League fixtures appear as `league_match` rows only (not a duplicate system tick). Friendly matches appear as `friendly_match` rows only (not a duplicate `TickType::FriendlyMatch` tick). There is **no** `hero_training_history` feed type.

### Backend

`GET /api/v1/kingdom/{id}/calendar`

Query: `start`, `end` (ISO-8601), optional `teamId` (own league visibility + team-scoped tick status + friendly rows; without it, team-only ticks stay `scheduled` and friendlies are omitted), `include_system=true` to keep `visibility: system_only` ticks.

Example item:

```json
{
  "id": "tick_fatigue_recovery_20260608040000",
  "type": "system_tick",
  "title": "Fatigue & Form Recovery",
  "description": "Passive restoration of hero fatigue and condition",
  "scheduledAt": "2026-06-08T04:00:00Z",
  "visibility": "system_only",
  "status": "scheduled",
  "metadata": { "tickType": "fatigue_recovery" }
}
```

Timestamps are ISO UTC. The Stimulus controller formats them with `kingdom.timezone` (passed as `data-calendar-timezone-value`).

Feed assembly: `CalendarService` from `TickScheduleCalculator`, tick logs, league fixtures, and `FriendlyMatchRepository::findForTeamInPeriod()`.

---

## Parked

Do not implement unless the user explicitly asks.

- Set reminder — `POST /api/v1/calendar/reminders`

World-event rows are **Deferred**: [future/world-events-system.md](../future/world-events-system.md).

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/calendar/index.html.twig` |
| Stimulus | `calendar` |
