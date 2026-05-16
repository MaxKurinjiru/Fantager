# Graveyard Screen

Reference: [screens-overview.md](../screens-overview.md#16-graveyard-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Graveyard List:
	- Hero name, race, final level, age at death
	- Cause of death, battles fought, wins, achievements, date of death
- Statistics Summary:
	- Total fallen heroes, average lifespan, notable heroes
- Memorial Wall:
	- Visual gravestones with detail modal and tribute actions

Possible Actions/Buttons:
- View Hero Details
- Filter by Race
- Sort and Search
- Share Memorial / Send Tribute

Backend Requirements:
- Graveyard list endpoint
- Hero permanent death logging and archival
- Memorial statistics and pagination
- Export/share endpoints (image/pdf)

Sections to fill:
- Graveyard listing endpoints
- Export/share mechanics
- Implementation notes
