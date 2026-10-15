# Calendar Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#14-calendar-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [calendar-system.md](../systems/calendar-system.md)

**When:** Weekly kingdom schedule (ticks, league matches, team training completions).

**Status:** Live at `GET /app/calendar`. Feed is JSON. Reminders are **Planned**. World events are deferred (`future/world-events-system.md`).

**Live UI:** `templates/calendar/index.html.twig`, Stimulus `calendar`.

---

## Implemented

### Displayed information

- Week navigation (previous / next) and range label
- Toggles: team-only entries, show system ticks
- Per-day groups: weekday, date, today marker, event cards or empty day
- Entry types: `system_tick`, `league_match`, `hero_training_history`

League fixtures appear as `league_match` rows only (not a duplicate system tick).

### Backend

`GET /api/v1/kingdom/{id}/calendar`

Query: `start`, `end` (ISO-8601), optional `teamId` (own league matches + team-scoped tick status; without it, team-only ticks stay `scheduled`).

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

Feed assembly: `CalendarService` from `TickScheduleCalculator`, tick logs, fixtures, training history, heroes.

---

## Planned

- Set reminder — `POST /api/v1/calendar/reminders` (not implemented)
- Dynamic world-event rows

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/calendar/index.html.twig` |
| Stimulus | `calendar` |
