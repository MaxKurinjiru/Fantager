# Trainer Management Screen

Reference: [screens-overview.md](../screens-overview.md#6-trainer-management-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Trainer List:
	- Trainer name
	- Original race
	- Attribute values (STR, DEX, KON, SPD, INT, WIL, CHA, LCK)
	- Age (+ Death Expectation warning)
	- Status (Active, Aging risk)
	- Assigned to hero (if any)
- Trainer Detail (when selected):
	- Full stats
	- Age progression timeline
	- Training history
	- Cost (if on Marketplace)

Possible Actions/Buttons:
- Assign to Hero
- Unassign
- Sell on Marketplace
- Buy from Marketplace
- Convert Hero to Trainer

Backend Requirements:
- Trainers list endpoint
- Trainer assignment/unassignment
- Marketplace integration

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
