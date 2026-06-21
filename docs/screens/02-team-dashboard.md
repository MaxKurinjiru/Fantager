# Team Dashboard (Main Screen)

Reference: [screens-overview.md](../screens-overview.md#2-team-dashboard-main-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- **Team Info Panel:**
	- Team name, emblem, colors
	- Team Reputation
	- Win/Loss record
	- Team Morale (value + indicator)
	- Team Chemistry (value + indicator)
	- **Fan club size** and most recent change (background stat; read-only badge in team banner)
- **Quick Stats:**
	- Number of heroes in roster
	- Current Gold, Essence, **unpaid debt** (when > 0)
	- **Financial crisis banner** with level, debt, recovery actions
	- Next scheduled match (time, opponent)
	- Current league tier & position
- **Team Chronicle (recent events):**
	- Last **5** entries from `team_chronicle` for the player's team
	- Localized message + timestamp per entry
	- Link **Full chronicle** → `/app/chronicle` (`app_team_chronicle`)
	- Includes ownership changes, season results, summons, etc. (see [team-chronicle-system.md](../systems/team-chronicle-system.md))
- **Shortcuts:**
	- Quick access to Formation, Training, Economy (`/app/economy`), League

Possible Actions/Buttons:
- **View Full Roster** - navigate to Hero Roster Screen
- **Manage Headquarters** - navigate to HQ Screen
- **Check League** - navigate to League Screen
- **Go to Economy / Marketplace** - navigate to `/app/economy`
- **View Calendar** - navigate to Calendar
- **View Team Chronicle** - navigate to `/app/chronicle`
- **Team Settings** - change name, emblem, colors

Backend Requirements:
- Dashboard aggregation: `TeamService::getDashboardData()` + `TeamChroniclePresenter::presentRecentForTeam()` (5 entries)
- Full chronicle: `TeamChronicleController` with category/type/sort filters
- Real-time notifications (Server-Sent Events / SSE) — planned; separate from chronicle

Implementation:
- **Route:** `GET /app/dashboard` — `DashboardController`
- **Templates:** `templates/dashboard/index.html.twig`, `templates/components/dashboard/recent_chronicle.html.twig`
- **Chronicle page:** `GET /app/chronicle` — [team-chronicle-system.md](../systems/team-chronicle-system.md)

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
