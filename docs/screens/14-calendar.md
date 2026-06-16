# Calendar Screen

Reference: [screens-overview.md](../screens-overview.md#14-calendar-screen)

Purpose: Per-screen API, UI data requirements, and implementation notes for the kingdom calendar (server ticks, league fixtures, team training).

## Displayed Information

- **Weekly calendar feed:**
  - Scheduled server ticks (fatigue recovery, training, league processing, maintenance, etc.)
  - League fixtures (home/away matches)
  - Team-specific training queue completions (when scoped to a team)
- **Filters:**
  - Show/hide system-only ticks
  - Team-only entries

## Possible Actions

- Navigate weeks (previous / next)
- Toggle filters (system ticks, team-only view)
- Set reminder — planned (`POST /api/v1/calendar/reminders`)

## Backend Requirements

- Calendar events feed endpoint
- Server tick schedule and timezone normalization
- Notification/reminder system (future)

## Calendar API & Feed

The calendar screen retrieves all entries via a unified JSON endpoint:

- **Route**: `GET /api/v1/kingdom/{id}/calendar`
- **Query Parameters**:
  - `start`: ISO-8601 start date (e.g. `2026-06-08T00:00:00Z`)
  - `end`: ISO-8601 end date (e.g. `2026-06-15T00:00:00Z`)
  - `teamId`: Optional team ID to scope player-specific entries (training queue, own league matches)
- **Response Format**:
  ```json
  [
    {
      "id": "tick_fatigue_recovery_20260608040000",
      "type": "system_tick",
      "title": "Fatigue & Form Recovery",
      "description": "Passive restoration of hero fatigue and condition",
      "scheduledAt": "2026-06-08T04:00:00Z",
      "visibility": "system_only",
      "status": "scheduled",
      "metadata": {
        "tickType": "fatigue_recovery"
      }
    },
    {
      "id": "league_match_42",
      "type": "league_match",
      "title": "Team Alpha vs Team Beta",
      "description": "League Fixture - Group A",
      "scheduledAt": "2026-06-09T18:00:00Z",
      "visibility": "public",
      "status": "scheduled",
      "metadata": {
        "fixtureId": 42,
        "homeTeam": { "id": 12, "name": "Team Alpha" },
        "awayTeam": { "id": 15, "name": "Team Beta" },
        "groupName": "A"
      }
    }
  ]
  ```

Feed entry types today: `system_tick`, `league_match`, `hero_training_history`. Dynamic world events are deferred — see [future/world-events-system.md](../future/world-events-system.md).

---

## Tick Schedule Display

- **Date Conversions**: Timestamps are delivered in ISO UTC format. The frontend converts these dates to the user's browser local timezone or the kingdom's official timezone.
- **Filtering**: Players can toggle visibility of system ticks and team-only entries on the weekly calendar feed.

---

## Implementation Notes

- **Web page**: `App\Controller\Web\CalendarController` at `/app/calendar` (Stimulus `calendar_controller.js`).
- **API Endpoint**: `App\Controller\Api\V1\CalendarController::getFeed()` at `/api/v1/kingdom/{id}/calendar`.
- **Feed Logic**: Constructed by `App\Service\Calendar\CalendarService` from `TickScheduleCalculator`, `KingdomTickLogRepository`, `LeagueFixtureRepository`, `HeroTrainingHistoryRepository`, and `HeroRepository`.
