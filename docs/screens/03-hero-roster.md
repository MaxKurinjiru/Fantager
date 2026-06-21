# Hero Roster Screen

Reference: [screens-overview.md](../screens-overview.md#3-hero-roster-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- **Hero List (table/cards):**
	- Hero name
	- Race (icon)
	- Level
	- Age (+ phase icon: Junior/Prime/Veteran/Elder)
	- Primary stats (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) - compact display
	- Form (%)
	- Fatigue (%)
	- Morale (value + icon)
	- Equipped items (icons)
	- Status (available, tired, training, in match)
- **Filtering & Sorting:**
	- Filter by race, level, status
	- Sort by stat, age, form, morale

Possible Actions/Buttons:
- **Select Hero** - navigate to Hero Detail Screen
- **Quick Actions Dropdown:**
	- Train Hero
	- Equip Items
	- Manage Spells
	- Sell on Marketplace
- **Multi-select Actions:**
	- Batch training
	- Formation assignment
- **Add New Hero** - navigate to Summoning Chamber or Marketplace

Backend Requirements:
- Heroes list endpoint with filtering/sorting parameters
- Batch operations support

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
