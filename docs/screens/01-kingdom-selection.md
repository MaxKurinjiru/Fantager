# Kingdom Selection (Registration Step)

Reference: [screens-overview.md](../screens-overview.md#1-kingdom-selection-screen)

**When:** Part of the registration form — not a separate screen. The player selects a Kingdom before submitting the registration form. The choice is permanent and cannot be changed after registration.

### Displayed Information:
- List of available Kingdoms (servers)
- For each Kingdom:
  - Name and theme/lore
  - Main language
  - Time zone
  - Game speed
  - **Current player count / Total capacity** (capacity derived from `league_tiers_config`; e.g. "23 / 60")
  - Season length
  - Kingdom icon/flag

### Possible Actions/Buttons:
- **Select Kingdom** - confirm selection (permanent, cannot be changed later)
- **Show Details** - expand information about specific Kingdom
- **Filter/Sort** - by language, game speed, occupancy

### Backend Requirements:
- API endpoint for fetching Kingdom list
- Kingdom availability validation (capacity)
- Writing selection to player profile

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
