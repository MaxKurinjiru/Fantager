# Summoning Chamber Screen

Reference: [screens-overview.md](../screens-overview.md#9-summoning-chamber-screen), [summoning-system.md](../systems/summoning-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

> **Implementation:** Summoning Chamber is a **panel inside HQ**. `GET /app/summon` redirects to `/app/hq?facility=summoning_chamber`; history subtab via `&subtab=history`.

Displayed Information:
- Summon Status:
	- Summons Used this Cycle
	- Max Summons per Cycle (based on Kingdom game speed)
- Summon Parameters:
	- Arena Adaptation (displaying the adapted race of the home arena)
	- Potential summonable races list (based on affinity and relations with the adapted race)
	- Starting level: **1**
	- Age range preview (Min Age - Max Junior Age for summonable races)
	- Expected stat range (based on race flat bonuses and Summoning Chamber level)
	- Summon Cost (Gold)
- Recent Summons (history subtab):
	- Recently acquired heroes
	- Their basic stats

Possible Actions/Buttons:
- Summon Hero (pulls a random compatible race based on the team's arena adaptation)
- View Summoned Hero

Backend Requirements:
- Summon availability check — `GET /api/v1/summoning/status`
- Random hero generation + summon endpoint — `POST /api/v1/summoning`

Implementation:
- **Panel route:** `/app/hq?facility=summoning_chamber`
- **Stimulus:** `summoning_controller.js` (embedded in HQ panel)
