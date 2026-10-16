# Arena Management Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [headquarters-system.md](../systems/headquarters-system.md)

**When:** Ticket price, capacity, projected home-match gold, and recent attendance analytics.

**Status:** HQ panel, not a standalone page. `GET /app/arena` → `/app/hq?facility=arena`. Match-day revenue and attendance **graphs/history** are live ([#4](../product/known-issues.md) Resolved / A7.8). Combat home-advantage buff is **not** in the engine. Lock: [Locked: attendance analytics](#locked-attendance-analytics).

**Live UI:** `templates/components/hq/facility_panel/_arena.html.twig`.

---

## Implemented

### Displayed information

- Arena level, seating capacity, ticket price, fan base, show-up rate
- Fan-club hint (fan base vs target, reputation, morale, chemistry) — chemistry here is **live** for attendance math, not a combat modifier (#7 Resolved)
- Ticket price slider/input **1–50** gold + elasticity badge + save
- Projection table (price / attendance / occupancy / elasticity / projected gold) when `projections` is non-empty
- Next home match (opponent, kickoff, projected revenue) when set
- Attendance analytics for the last **18** `arena_revenue` payouts (same window for table, chart, and summary)
- How-it-works rules (fixed price, capacity, attendance, home only)
- Link to [friendly matches](18a-friendly-matches.md)

### Attendance analytics

- Summary: match count in the window, total gold, average attendance (rows with stored attendance only)
- Dual-axis Chart.js line: attendance and ticket gold, chronological (oldest first)
- History table: date, opponent, ticket price, attendance, occupancy %, gold
- Occupancy is `attendance / capacity × 100` from that payout’s ledger context; missing attendance or capacity shows an em dash
- Empty state: same copy as no home-match revenue yet
- Link to `/app/finance` ([20-finance.md](20-finance.md))

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
| Status | `GET /api/v1/arena` (includes `recent_revenue` + `analytics`) |
| Price | `POST /api/v1/hq/arena/tickets/price` |
| Services | `ArenaService`, `ArenaRevenueService`, `FanClubService` |

CLI: `app:economy:distribute-arena-revenue` for a manual fixture payout.

Facility level/capacity still upgrade on the HQ grid ([08-headquarters.md](08-headquarters.md)).

---

## Locked: attendance analytics

**#4 (A7.8, 16 Oct 2026).** Graphs and richer history stay in this HQ panel. Data is the last **18** home-match `FinancialRecord` rows of type `arena_revenue` (one league season of home games). Summary totals and averages use that same window — not a separate season query. Chart series are attendance and gold only (not fan-base). `GET /api/v1/arena` is the payload; there is no extra analytics route.

Cut from this screen (do not add without a new product decision): combat home-advantage, match results on the history table, seating-upgrade charts, a standalone `/app/arena` page, a new snapshot entity.

---

## Planned

- Combat home-advantage

---

## Frontend

| Piece | Location |
|-------|----------|
| Panel | `templates/components/hq/facility_panel/_arena.html.twig` |
| Stimulus | `arena` (pricing), `arena_analytics` (history chart) |
