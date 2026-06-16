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
- **Quick Stats:**
	- Number of heroes in roster
	- Current Gold, Essence, **unpaid debt** (when > 0)
	- **Financial crisis banner** with level, debt, recovery actions
	- Next scheduled match (time, opponent)
	- Current league tier & position
- **Recent Activity Feed:**
	- Recent matches (results)
	- Completed trainings
	- Marketplace notifications
	- Kingdom events
- **Shortcuts:**
	- Quick access to Formation, Training, Marketplace, League

Possible Actions/Buttons:
- **View Full Roster** - navigate to Hero Roster Screen
- **Manage Headquarters** - navigate to HQ Screen
- **Check League** - navigate to League Screen
- **Go to Marketplace** - navigate to Marketplace
- **View Calendar** - navigate to Events Calendar
- **Team Settings** - change name, emblem, colors

Backend Requirements:
- Dashboard aggregation endpoint (stats, notifications, recent activity)
- Real-time notifications (Server-Sent Events / SSE)


Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
