# Arena Management Screen

Reference: [screens-overview.md](../screens-overview.md#18-arena-management-screen-optionalextended-feature)

> **Implementation:** Arena is a **panel inside HQ**, not a standalone page. `GET /app/arena` redirects to `/app/hq?facility=arena`.

## Revenue Model

- **Configurable ticket price**: `Team.ticketPrice` (1–50 gold), updated via `POST /api/v1/hq/arena/tickets/price` (`ArenaService::updateTicketPrice`). Default is 5 gold.
- **Capacity**: Base seating × Arena facility `arena_capacity` bonus (home team HQ).
- **Attendance**: Fills proportionally from **both** teams' fan appeal (reputation, morale, chemistry) via `FanClubService`.
- **Payout**: Home team only, triggered on **League Match** tick when the fixture is processed.
- **Bonuses**: Home team's Arena (`ticket_revenue_pct`) and Treasury (`gold_income_pct`) multipliers apply.

## Implementation Notes

- **Web panel**: `/app/hq?facility=arena` — `Web\HeadquartersController` + HQ templates
- **Legacy redirect**: `/app/arena` → HQ arena panel
- **API**: `GET /api/v1/arena` — status and projections; `POST /api/v1/hq/arena/tickets/price` (+ `/api/v1/arena/tickets/price` alias)
- **Services**: `ArenaService`, `ArenaRevenueService`, `FanClubService`
- **CLI**: `app:economy:distribute-arena-revenue --time="YYYY-MM-DD HH:MM:SS"` for manual fixture payout
- **HQ upgrades**: Arena level/capacity via `/app/hq`

Friendly match scheduling remains planned (`POST /api/v1/arena/schedule-match`). Extended attendance analytics UI is still pending.
