# Summoning Chamber Screen

Reference: [screens-overview.md](../screens-overview.md#9-summoning-chamber-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Summon Status:
	- Next Available Summon (countdown timer)
	- Summons Used this Cycle
	- Max Summons per Cycle (based on HQ level)
- Summon Parameters:
	- Arena Theme Adaptation (displaying the theme race of the home Arena)
	- Potential summonable races list (based on affinity and relations with theme race)
	- Starting level: **1**
	- Age range preview (Min Age - Max Junior Age for summonable races)
	- Expected stat range (based on race flat bonuses and Summoning Chamber level)
	- Summon Cost (Gold)
- Recent Summons (history):
	- Recently acquired heroes
	- Their basic stats

Possible Actions/Buttons:
- Summon Hero (pulls a random compatible race based on the team's HQ/Arena race optimization)
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
