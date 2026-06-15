# Arena Management Screen

Reference: [screens-overview.md](../screens-overview.md#18-arena-management-screen-optionalextended-feature)

## Revenue Model

- **Fixed ticket price**: `ArenaRevenueService::TICKET_PRICE` (5 gold) — not player-configurable.
- **Capacity**: Base seating × Arena facility `arena_capacity` bonus (home team HQ).
- **Attendance**: Fills proportionally from **both** teams' fan appeal (reputation, morale, chemistry).
- **Payout**: Home team only, triggered on **League Match** tick when the fixture is processed.
- **Bonuses**: Home team's Arena (`ticket_revenue_pct`) and Treasury (`gold_income_pct`) multipliers apply.

## Implementation Notes

- **Web page**: `/app/arena` — `Web\ArenaController`
- **API**: `GET /api/v1/arena` — read-only status and projections
- **Service**: `ArenaRevenueService::calculateMatchRevenue()`, `processLeagueMatchTick()`
- **CLI**: `app:economy:distribute-arena-revenue --time="YYYY-MM-DD HH:MM:SS"` for manual fixture payout
- **HQ upgrades**: Arena level/capacity via `/app/hq`

Friendly match scheduling remains planned (requires combat engine).
