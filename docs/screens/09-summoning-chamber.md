# Summoning Chamber Screen

Reference: [screens-overview.md](../screens-overview.md#9-summoning-chamber-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Summon Status:
	- Next Available Summon (countdown timer)
	- Summons Used this Cycle
	- Max Summons per Cycle (based on HQ level)
- Summon Parameters:
	- Race selection (dropdown or race icons)
	- Age range preview (Min Age - Max Junior Age for selected race)
	- Expected stat range (based on race multipliers)
	- Summon Cost (Gold)
- Recent Summons (history):
	- Recently acquired heroes
	- Their basic stats

Possible Actions/Buttons:
- Summon Hero
- Select Race
- Auto-Summon
- View Summoned Hero
- Buy Another Slot

Backend Requirements:
- Summon availability check
- Random hero generation
- Summon endpoint (POST)
- Cooldown tracking

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
