# Headquarters Screen

Reference: [screens-overview.md](../screens-overview.md#8-headquarters-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- HQ Overview: level, theme preview, race optimization
- Facilities List: training, medical wing, library/academy, forge/workshop, treasury, barracks, summoning chamber, arena — levels and upgrade costs
- Passive Bonuses Summary: total bonuses and race-specific bonuses

Possible Actions/Buttons:
- Upgrade Facility
- Change Race Optimization
- Customize Theme
- Visit Summoning Chamber

Backend Requirements:
- HQ data endpoint
- Facility upgrade endpoint (validation, cost deduction)
- Race optimization change endpoint
- Passive bonuses calculation

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
