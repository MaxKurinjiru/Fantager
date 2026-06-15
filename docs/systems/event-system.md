# Event System

Reference: [game-summary.md](../game-summary.md#22-event-system)

Purpose: Document scheduled events, server ticks, event lifecycle, and implementation details.

## Event Types & Triggers

Game events are categorized into three main categories:
1. **Scheduled Server Ticks**: Automated periodic tasks that affect the game economy and player teams (Daily Reset, Fatigue Recovery, Weekly Training, League Match arena revenue).
2. **Dynamic World Events**: Limited-time gameplay events (e.g. Dungeon cycles, Seasonal bonus weeks) modeled via the `Event` entity.
3. **Queue Completions**: Asynchronous tasks specific to teams (Facility Upgrades, Hero Training queue, Item Crafting jobs).

---

## Server Tick Scheduling & Processing

All scheduled server ticks are governed by the `app:ticks:run` runner. 
- **Timezone Alignment**: Ticks run at designated local times configured on each Kingdom (e.g. `Europe/Prague`). The scheduler normalizes target local times (e.g. `04:00` for fatigue recovery) into UTC when determining execution eligibility.
- **Sequential Execution**: During execution, the tick engine queries outstanding ticks and processes them chronologically.

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

Upon tick completion (e.g. Match resolution, crafting job done, or world event transition), the message handler dispatches notifications to players:
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


Summary:
- Dynamic world events include weekly server ticks, seasonal events, limited missions, and dungeon cycles. Events can grant XP, items, and currency.
- Each event has lifecycle states: scheduled -> active -> resolving -> complete.

Server tick responsibilities:
- Process fatigue/form recovery, league match resolution, dungeon/job processing, training/crafting queue completion, marketplace auctions. See [Calendar System](calendar-system.md) for the detailed weekly schedule and season timeline.
- Runs on kingdom-local timezone and respects Game Speed multiplier.

APIs & data:
- GET /api/events — list active and upcoming events
- POST /api/events/{id}/participate — join event

Edge cases:
- Partial failures during multi-step rewards distribution; use transactional processing and retries.

