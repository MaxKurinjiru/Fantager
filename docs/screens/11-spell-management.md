# Spell Management Screen

Reference: [screens-overview.md](../screens-overview.md#11-spell-management-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Hero Selector: hero selection, current magic capacity, school mastery levels
- Known Spells Library: list of learned spells with filters and spell details
- Equipped Spells Panel: slots 1-5 with drag&drop
- Available Spells (Store/Academy): spells available to learn with costs and requirements

Possible Actions/Buttons:
- Equip Spell, Unequip Spell, Learn New Spell, Swap Spells, View Spell Details, Train School Mastery, Expand Spell Slots

Backend Requirements:
- Hero spells endpoint
- Spell library endpoint
- Learn spell endpoint
- Equip/unequip endpoint

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
