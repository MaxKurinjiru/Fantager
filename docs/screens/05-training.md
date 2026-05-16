# Training Screen

Reference: [screens-overview.md](../screens-overview.md#5-training-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Hero Selection Panel:
	- Dropdown or quick-select from hero list
	- Current Form, Fatigue, Morale of hero
- Training Options Tabs:
	- Attribute Training
	- Magic Training (Spell Slots, School Mastery, Spell Learning)
	- Form & Recovery
- Attribute Training View:
	- 8 primary attributes (STR-LCK)
	- Current value
	- Training cost (Gold)
	- Expected increase
	- Trainer cap (if assigned)
	- Success rate (%)
	- Estimated time (server tick cycles)
- Magic Training View:
	- Spell Slot Expansion: current/max, cost, requirements
	- School Mastery: 6 schools, current tier, upgrade cost (Gold + Essence), requirements
	- Spell Learning: available spells (filtered by Mastery), cost (Gold + Essence)
- Form & Recovery View:
	- Current Form (%)
	- Cost for Full Restoration
	- Recovery Training Options (light training)
- Training Queue:
	- List of scheduled trainings
	- Estimated completion time
	- Cancellation option

Possible Actions/Buttons:
- Start Training
- Queue Multiple
- Assign Trainer
- Cancel Training
- Buy Training Package

Backend Requirements:
- Training options endpoint (costs, requirements, success rates)
- Training queue endpoint (GET/POST/DELETE)
- Training calculation/simulation
- Server tick processing

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
