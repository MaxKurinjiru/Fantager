# Formation Setup Screen

Reference: [screens-overview.md](../screens-overview.md#7-formation-setup-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- **Formation Selector:**
	- Formation 1 (editable name)
	- Formation 2 (editable name)
	- Default formation indicator
- **Formation Layout (visual grid):**
	- **Front Line (3 positions)**
	- **Back Line (3 positions)**
	- Positions show drag&drop hero cards or empty slots
- **Hero Cards (in layout):**
	- Avatar/icon
	- Name
	- Level
	- Primary role/specialization (icon)
	- Quick stats
- **Hero Pool (sidebar):**
	- Available heroes for assignment
	- Filter by role, stats, race
- **Strategy Settings Panel:**
	- **Pre-match Approach:** Aggressive / Balanced / Defensive (radio buttons)
	- **Per-Hero Settings (when hero is selected in layout):**
		- **Targeting Priority:** Priority 1-6 or Flexible (dropdown for each priority)
		- **Action Sequence:** Attack / Cast Spell / Defend / Heal / Buff / Debuff / Auto-Suggest (action order, drag&drop)
		- **Spell Priority (Formation-level):**
			- Spell selection (dropdown from known spells)
			- Casting conditions (On Low Health, On Low Morale, etc.)
			- Spell targets (Self, Lowest Health, Highest Priority, Area)
		- **Conditional Tactics:** trigger and action settings
- **Formation Synergy Indicator:**
	- Race relationship warnings/bonuses
	- Role balance check (tank, damage, support, healer)
	- Team chemistry preview

Possible Actions/Buttons:
- **Drag & Drop Heroes** - move heroes between Pool and Layout positions
- **Swap Heroes** - swap two heroes
- **Clear Slot** - remove hero from position
- **Save Formation** - save changes
- **Clone Formation** - duplicate to second slot
- **Set as Default** - mark formation as default
- **Test Formation** - simulation or practice match
- **View Opponent Formation** (before match) - if available
- **Quick Fill** - auto-suggest optimal formation

Backend Requirements:
- Formation GET/POST/PUT endpoints
- Formation validation (6 heroes required, unique heroes)
- Synergy calculation API
- Formation simulation/testing

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
