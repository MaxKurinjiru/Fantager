# Kingdom Selection Screen

Reference: [screens-overview.md](../screens-overview.md#1-kingdom-selection-screen)

**When:** During account creation (one-time only)

### Displayed Information:
- List of available Kingdoms (servers)
- For each Kingdom:
  - Name and theme/lore
  - Main language
  - Time zone
  - Game speed
  - Current player count / Max capacity
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
