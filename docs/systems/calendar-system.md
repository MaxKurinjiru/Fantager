# Calendar & Server Ticks System

Reference: [game-summary.md](../game-summary.md#22-weekly-calendar-server-ticks), [event-system.md](event-system.md), [league-system.md](league-system.md)

Purpose: Document the weekly schedule, automated event sequences, daily/weekly server ticks, and the timeline of a league season.

---

## Weekly Server Tick Schedule

The game world operates on automated server ticks executed at scheduled times. These ticks process progression, economy, maintenance, and competitive matches. All ticks are executed in the Kingdom's local timezone.

| Day | Time | Event / Tick | Action Details |
|:---|:---|:---|:---|
| **Daily** | 00:00 | **Daily Reset & Maintenance** | Process daily quests expiration, reset daily limits, update hero aging, process expired marketplace listings. |
| **Daily** | 04:00 | **Fatigue & Form Recovery** | Recovery tick for hero fatigue and form (passive restoration). |
| **Tuesday** | 18:00 | **League Match (Mid-Week)** | Process scheduled mid-week league fixtures. Resolve combat, distribute match XP/Gold, apply post-match fatigue/form/morale/aging. |
| **Friday** | 10:00 | **Weekly Training** | Process queued training jobs. Calculate stat gains (using non-linear formulas and raw x10 scaling), apply to heroes, and restore status. |
| **Friday** | 18:00 | **League Match (End-Week)** | Process scheduled end-week league fixtures. Resolve combat, distribute match XP/Gold, apply post-match fatigue/form/morale/aging. |
| **Friday** | 19:00 | **Season Transition** *(Week 11 only)* | Run season resolution service: finalize standings, distribute tier promotion/relegation rewards, execute team transfers (promotions/relegations), initialize the next season. |
| **Sunday** | 09:30 | **Race Optimization** | Apply pending headquarters race optimization changes and manage weekly optimization lock cycles. |
| **Weekly** | Sun 23:59 | **Weekly Reset** | Process weekly quest expiration, reset summoning chamber cooldowns, distribute weekly Arena seating revenues. |


---

## Season Lifecycle Timeline

Based on a standard 10-team league group playing a **double round-robin** tournament (each team plays every other team twice: once Home, once Away), the season requires **18 rounds** of matches. 

To provide players with time to prepare rosters, adjust strategies, and play friendly matches:
- **Week 1 (Preparation Gap):** No league matches are scheduled. Players can recruit heroes, build formations, trade on the marketplace, upgrade headquarters facilities, and schedule friendly/practice matches.
- **Weeks 2 to 10 (League Matches):** 18 rounds of league matches are played at a pace of **2 matches per week** (Tuesday and Friday).
- **Week 11 (Post-Season Transition):** League matches are completed. The week is used for rest, standings review, and the **Season Transition** on Friday at 19:00.

Consequently, the entire season spans **11 weeks (77 days)**.

> [!NOTE]
> The default `season_length` of 28 days (4 weeks) is extended to **77 days** to accommodate the preparation week, 18 rounds of league play, and the post-season transition phase in Week 11.

### Chronological Season Timeline

- **Week 1:**
  - No league matches. Reserved for recruitment, training, trade, and friendly matches.
- **Weeks 2 to 10:**
  - **Tuesday 18:00:** Mid-week match (Odd rounds: 1, 3, 5, 7, 9, 11, 13, 15, 17)
  - **Friday 18:00:** End-week match (Even rounds: 2, 4, 6, 8, 10, 12, 14, 16, 18)
- **Week 11:**
  - **Tuesday 18:00:** Rest day / Standings review
  - **Friday 18:00:** Rest day / Final preparation
  - **Friday 19:00:** **Season Transition** (resolution of promotions, relegations, rewards, and preparation for Season N+1)

```mermaid
gantt
    title Double Round-Robin Season Timeline (77 Days)
    dateFormat  YYYY-MM-DD
    axisFormat %w
    
    section Week 1 (Prep)
    Preparation Gap (No League Matches) :active, 2026-06-02, 7d
    
    section Weeks 2-3 (First Leg)
    Rounds 1-4 (Tue/Fri) :active, 2026-06-09, 11d
    
    section Weeks 4-5
    Rounds 5-8 (Tue/Fri) :active, 2026-06-22, 12d
    
    section Week 6 (Mid-Way)
    Rounds 9-10 (Tue/Fri) :active, 2026-07-06, 5d
    
    section Weeks 7-8 (Second Leg)
    Rounds 11-14 (Tue/Fri) :active, 2026-07-13, 12d
    
    section Weeks 9-10
    Rounds 15-18 (Tue/Fri) :active, 2026-07-27, 12d
    
    section Week 11 (Transition)
    Season Transition (Fri 19:00) :crit, 2026-08-10, 5d
```

---

## Match Scheduling & Venue Alternation

To ensure fair scheduling using **Berger's Algorithm** (double round-robin), match fixtures are generated before the season starts.

### Weekly Home/Away Balance Rule
For weeks 2 to 10 (which contain exactly 2 rounds per week):
- Each team is scheduled to play **exactly 1 Home match** and **exactly 1 Away match** per week.
- This prevents a team from playing two consecutive home or away matches within the same week, balancing arena ticket revenue and home field advantages.
- Since 18 is an even number, every team will have played exactly **9 Home** matches and **9 Away** matches by the end of the season.

*For implementation details of the fixture scheduling algorithm, see [League System - Match Scheduling](league-system.md#match-scheduling-and-processing).*

---

## Technical Architecture & Tick Runner

### 1. Database Persistence (`KingdomTickLog`)

To guarantee that scheduled ticks are run exactly once (idempotency) and that no ticks are missed during system downtime, the status of each tick execution is logged in the database:

- **Entity**: `App\Entity\Kingdom\KingdomTickLog`
- **Table**: `kingdom_tick_log`
- **Fields**:
  - `id` (INT, Primary Key)
  - `kingdom_id` (INT, Foreign Key to `kingdom`, NOT NULL)
  - `tickType` (VARCHAR(30) / Enum: e.g. `daily_reset`, `fatigue_recovery`, `league_match`, `weekly_training`, `season_transition`, `weekly_reset`)
  - `scheduledAt` (DATETIME, UTC, NOT NULL) - The scheduled real-world timestamp when the tick should have executed.
  - `status` (VARCHAR(15) / Enum: `processing`, `completed`, `failed`)
  - `errorMessage` (TEXT, Nullable)
  - `executedAt` (DATETIME, UTC) - The real-world timestamp when processing actually ran.
- **Constraints**: Unique constraint on `[kingdom_id, tick_type, scheduled_at]`.

### 2. Tick Runner Command (`app:ticks:run`)

A background cron job triggers `bin/console app:ticks:run` periodically (e.g. every minute).
For each active Kingdom:
1. It queries `KingdomTickLog` to find the last completed scheduled timestamp ($t_{last}$) for each tick type. If no log entry exists, it defaults to the `season_start_date`.
2. It generates all scheduled occurrences of that tick type between $t_{last}$ (exclusive) and `now` (inclusive) using the Weekly Server Tick Schedule.
3. For each pending tick:
   - It persists a log entry with `status = processing`.
   - It dispatches a `ProcessKingdomTicksMessage(kingdomId)` to Symfony Messenger.

### 3. Chronological & Priority Execution Flow

Within the message handler (`ProcessKingdomTicksHandler`):
- All pending tick logs for the specified Kingdom are processed sequentially.
- Ticks are sorted first by `scheduledAt ASC` (chronological order) and second by **logical priority** to avoid dependency conflicts:
  1. **Priority 1: Queue Completions** (Facility upgrades, item crafting, dungeon completions) - updates roster/infrastructure.
  2. **Priority 2: Weekly Training** (Friday 10:00) - upgrades hero stats.
  3. **Priority 3: Match Resolution** (League/Friendly matches) - executes battles, applies fatigue/injury.
  4. **Priority 4: Post-Match Transitions** (Season Transitions on Week 11 Friday 19:00).
  5. **Priority 5: Fatigue & Form Recovery** (Daily 04:00) - recovers hero stats.
  6. **Priority 6: Reset / Maintenance** (Daily 00:00, Weekly Arena Revenue Sun 23:59).

If any tick fails, the handler halts execution for that Kingdom, logs the error, and alerts administrators. This prevents subsequent ticks from executing out of order, preserving data integrity.

### 4. Messenger Integration & Queues

We configure three priority transports in `config/packages/messenger.yaml`:
- **`async_high`**: Immediate interactions (real-time friendly match combat simulation, immediate player action processing).
- **`async_medium`**: Scheduled tick processing (e.g. `ProcessKingdomTicksMessage`).
- **`async_low`**: Analytics, history logs, and non-blocking notifications.

