# Calendar/Events Screen

Reference: [screens-overview.md](../screens-overview.md#14-calendarevents-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Weekly Calendar Grid:
	- Scheduled server ticks and kingdom-level events
	- Player-scheduled matches and arena times
- Upcoming Events Panel:
	- Event list, rewards preview, participation requirements
- Active Events Panel:
	- Ongoing events with progress bars and time remaining
- Event History:
	- Completed events and earned rewards

Possible Actions/Buttons:
- View Event Details
- Participate in Event
- Set Reminder / Subscribe
- Filter Events (by type, rewards, participation)

Backend Requirements:
- Calendar events feed endpoint
- Server tick schedule and timezone normalization
- Event participation registration endpoint
- Notification/reminder system (push/email/in-app)
- Event history and audit logs

## Calendar API & Event Feed

The calendar screen retrieves all entries via a unified JSON endpoint:
- **Route**: `GET /api/v1/kingdom/{id}/calendar`
- **Query Parameters**:
  - `start`: ISO-8601 start date (e.g. `2026-06-08T00:00:00Z`)
  - `end`: ISO-8601 end date (e.g. `2026-06-15T00:00:00Z`)
  - `teamId`: Option team ID to scope player-specific actions (e.g. friendly matches, item crafting, upgrades).
- **Response Format**:
  ```json
  [
    {
      "id": "tick_123",
      "type": "league_match",
      "title": "League Match vs #1 Team Alpha",
      "description": "Round 3 (Home match)",
      "scheduledAt": "2026-06-09T18:00:00Z",
      "visibility": "public",
      "status": "scheduled",
      "metadata": {
        "homeTeamId": 12,
        "awayTeamId": 15,
        "round": 3
      }
    },
    {
      "id": "training_tick_456",
      "type": "weekly_training",
      "title": "Weekly Training Tick",
      "description": "Hero training gains applied",
      "scheduledAt": "2026-06-12T10:00:00Z",
      "visibility": "public",
      "status": "scheduled",
      "metadata": {}
    }
  ]
  ```

---

## Tick Schedule Display

- **Date Conversions**: Timestamps are delivered in ISO UTC format. The frontend converts these dates to the user's browser local timezone or the kingdom's official timezone.
- **Filtering**: Players can toggle visibility of events on their weekly calendar feed (e.g. show/hide administrative ticks like "Fatigue Recovery" or "Daily Maintenance", and only show team-relevant queues and league matches).

---

## Reminder / Notification Hooks

- **Client Reminders**: Players can set local notifications or browser push notifications. The API provides a hook:
  `POST /api/v1/calendar/reminders` with payload `{ eventId: string, leadMinutes: int }`.
- **System Server-Sent Events**: Ongoing events push state updates (e.g., transition from `scheduled` to `in_progress` to `complete`) to the client using Mercure.

---

## Implementation Notes

- **API Endpoint**: Handled by `App\Controller\Api\CalendarController::getFeed()`.
- **Feed Logic**: Constructed by `App\Service\Calendar\CalendarService` which delegates collection queries to repositories (`LeagueFixtureRepository`, `EventRepository`, and queue tables).

