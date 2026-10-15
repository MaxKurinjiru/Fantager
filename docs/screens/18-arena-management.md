# Arena Management Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#18-arena-management-screen-optionalextended-feature) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [headquarters-system.md](../systems/headquarters-system.md)

**When:** Ticket price, capacity, and projected home-match gold.

**Status:** HQ panel, not a standalone page. `GET /app/arena` → `/app/hq?facility=arena`. Match-day revenue is live. Extended attendance **analytics UI** (graphs) is still pending ([#4](../product/known-issues.md) / A7.8). Combat home-advantage buff is **not** in the engine.

**Live UI:** `templates/components/hq/facility_panel/_arena.html.twig`.

---

## Implemented

### Displayed information

- Arena level, seating capacity, ticket price, fan base, show-up rate
- Fan-club hint (fan base vs target, reputation, morale, chemistry) — chemistry here is **display** for attendance math, not a combat modifier (#7)
- Ticket price slider/input **1–50** gold + elasticity badge + save
- Projection table (price / attendance / occupancy / elasticity / projected gold) when `projections` is non-empty
- Next home match (opponent, kickoff, projected revenue) when set
- Recent ticket-revenue rows + link to `/app/finance`
- How-it-works rules (fixed price, capacity, attendance, home only)
- Link to [friendly matches](18a-friendly-matches.md)

### Revenue model (backend)

- `Team.ticketPrice` via `POST /api/v1/hq/arena/tickets/price` (alias `/api/v1/arena/tickets/price`)
- Capacity: base seating × Arena `arena_capacity`
- Attendance from both teams’ fan appeal (`FanClubService`)
- Payout to **home** team on the League Match tick (`ArenaRevenueService`)
- Arena `ticket_revenue_pct` and Treasury `gold_income_pct`
- Audience Favorite trait: +5% ticket gold per matching home-lineup hero

### Backend

| Piece | Location |
|-------|----------|
| Panel | `/app/hq?facility=arena` — `Web\HeadquartersController` |
| Status | `GET /api/v1/arena` |
| Price | `POST /api/v1/hq/arena/tickets/price` |
| Services | `ArenaService`, `ArenaRevenueService`, `FanClubService` |

CLI: `app:economy:distribute-arena-revenue` for a manual fixture payout.

Facility level/capacity still upgrade on the HQ grid ([08-headquarters.md](08-headquarters.md)).

---

## Planned

- Attendance analytics graphs / richer history UI (A7.8) — the recent-revenue **table** is live
- Combat home-advantage

---

## Frontend

| Piece | Location |
|-------|----------|
| Panel | `templates/components/hq/facility_panel/_arena.html.twig` |
| Stimulus | `arena` (inside HQ) |
