# Event System

Reference: [game-summary.md](../game-summary.md#22-event-system)

Purpose: Document scheduled events, server ticks, event lifecycle, and implementation details.

## Event Types & Triggers

Game events fall into two implemented categories:

1. **Scheduled Server Ticks**: Automated periodic tasks governed by `app:ticks:run` and `ProcessKingdomTicksHandler`. These affect economy, progression, maintenance, and league scheduling. See [Calendar System](calendar-system.md) for the full weekly schedule.
2. **Dynamic World Events**: Limited-time gameplay events (e.g. seasonal bonus weeks) modeled via the `Event` entity.

Team-scoped work processed today runs inside scheduled ticks:
- HQ facility upgrades complete during `DailyReset` (00:00).
- Trainer-based hero training resolves during `WeeklyTraining` (Friday 10:00).
- Expired marketplace listings resolve during `DailyReset` (00:00).

The calendar feed may also display team-specific `training_queue` entries for UI purposes; those reflect the same Friday training schedule rather than a standalone tick runner.

---

## Server Tick Scheduling & Processing

All scheduled server ticks are governed by the `app:ticks:run` runner.
- **Timezone Alignment**: Ticks run at designated local times configured on each Kingdom (e.g. `Europe/Prague`). The scheduler normalizes target local times (e.g. `04:00` for fatigue recovery) into UTC when determining execution eligibility.
- **Sequential Execution**: During execution, the tick engine queries outstanding ticks and processes them chronologically, with logical priority as a tie-breaker when timestamps collide.
- **Single Entry Point**: Legacy standalone commands (`app:training:tick`, `app:process-marketplace-listings`) were removed; all recurring processing goes through the tick log system.

---

## Data Model & Event State

The system tracks event lifecycles via the following entities:
1. **`App\Entity\Event\Event`**:
   - Represents dynamic world events.
   - States: `Scheduled` -> `Active` -> `Resolving` -> `Completed`.
2. **`App\Entity\Kingdom\KingdomTickLog`**:
   - Tracks execution of recurring system ticks.
   - States: `processing` -> `completed` / `failed`.

---

## Notifications

Upon tick completion (e.g. marketplace listing expiry, training completion, league match revenue payout, or world event transition), the system may dispatch notifications to players:
- **In-App Notifications**: Persists `Notification` records in the database, which players fetch on dashboard updates or login.

---

## Edge Cases & Failure Handling

- **Partial Failure in Tick Sequence**: If a specific tick fails (e.g. a match simulation crashes), the tick log is marked `failed` with the error stack trace. The `ProcessKingdomTicksHandler` immediately halts execution for that Kingdom, preserving the integrity of downstream transactions.
- **Race Conditions**: Handled by the unique database constraint on `[kingdom_id, tick_type, scheduled_at]` inside the log table. Multiple parallel runner commands will not double-execute ticks.
- **Downtime Catch-Up**: Upon recovery from server downtime, the command automatically schedules and executes all missed occurrences in strict chronological order.

---

## Implementation Notes

- **Folder Structure**:
  - `src/Entity/Kingdom/KingdomTickLog.php` — Log entity.
  - `src/Message/ProcessKingdomTicksMessage.php` — Command message.
  - `src/Message/ProcessKingdomTicksHandler.php` — Sequential executor.
  - `src/Command/ProcessTicksCommand.php` — Cron runner console command.
  - `src/Service/Calendar/CalendarService.php` — Calendar aggregating feed service.

### Current server tick responsibilities

- Fatigue/form recovery, weekly training, league match arena revenue, marketplace listing expiry, HQ maintenance and upgrades, player inactivity cleanup, race optimization, season transition, and weekly summon reset.
- Runs on kingdom-local timezone and respects the Game Speed multiplier.

### APIs & data

- `GET /api/v1/calendar` — kingdom calendar feed (ticks, fixtures, world events, team training entries)
- `GET /api/v1/events` — list active and upcoming world events *(when exposed)*
- `POST /api/v1/events/{id}/participate` — join event *(planned)*

### Edge cases

- Partial failures during multi-step reward distribution should use transactional processing; failed ticks block further kingdom processing until resolved.
