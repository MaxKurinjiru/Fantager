# Trainer Management Screen

Reference: [screens-overview.md](../screens-overview.md#6-trainer-management-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Trainer List:
	- Trainer name
	- Original race
	- Attribute values (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) — frozen at conversion, range 1–20
	- Age (+ Death Expectation warning)
	- Status (Active, Aging risk)
- Trainer Detail (when selected):
	- Full stats (per-attribute training caps)
	- Age progression timeline
	- Cost (if on Marketplace)

Possible Actions/Buttons:
- Sell on Marketplace
- Buy from Marketplace
- Convert Hero to Trainer (no specialty selection — stats frozen as-is)

Note: Trainers act as training leaders. Their training focus (Attribute, Magic, Form, or Idle) and hero assignments are configured on the Training Screen.

Backend Requirements:
- Trainers list endpoint
- Marketplace integration
- Trainer conversion endpoint

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes

