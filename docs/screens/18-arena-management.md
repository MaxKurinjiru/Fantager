# Arena Management Screen

Reference: [screens-overview.md](../screens-overview.md#18-arena-management-screen-optionalextended-feature)

> **Implementation:** Arena is a **panel inside HQ**, not a standalone page. `GET /app/arena` redirects to `/app/hq?facility=arena`.

## Revenue Model

- **Fixed ticket price**: `ArenaRevenueService::TICKET_PRICE` (5 gold) — not player-configurable.
- **Capacity**: Base seating × Arena facility `arena_capacity` bonus (home team HQ).
- **Attendance**: Fills proportionally from **both** teams' fan appeal (reputation, morale, chemistry) via `FanClubService`.
- **Payout**: Home team only, triggered on **League Match** tick when the fixture is processed.
- **Bonuses**: Home team's Arena (`ticket_revenue_pct`) and Treasury (`gold_income_pct`) multipliers apply.

## Implementation Notes

- **Web panel**: `/app/hq?facility=arena` — `Web\HeadquartersController` + HQ templates
- **Legacy redirect**: `/app/arena` → HQ arena panel
- **API**: `GET /api/v1/arena` — read-only status and projections
- **Services**: `ArenaService`, `ArenaRevenueService`, `FanClubService`
- **CLI**: `app:economy:distribute-arena-revenue --time="YYYY-MM-DD HH:MM:SS"` for manual fixture payout
- **HQ upgrades**: Arena level/capacity via `/app/hq`

Friendly match scheduling remains planned (requires combat engine).
