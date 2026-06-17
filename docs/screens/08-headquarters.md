# Headquarters Screen

Reference: [screens-overview.md](../screens-overview.md#8-headquarters-screen), [headquarters-system.md](../systems/headquarters-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

> **Implementation:** HQ is the central hub for facility management. Arena and Summoning Chamber panels are opened via query params (`?facility=arena`, `?facility=summoning_chamber`). Legacy routes `/app/arena` and `/app/summon` redirect here.

Displayed Information:
- HQ Overview: level, theme preview, arena adaptation
- Facilities List (**7 facilities**): training, medical wing, library/academy, treasury, barracks, summoning chamber, arena — levels and upgrade costs
- Passive Bonuses Summary: total bonuses and race-specific bonuses
- Facility panels: Arena (capacity, fan appeal, revenue projection), Summoning Chamber (summon + history subtab)

Possible Actions/Buttons:
- Upgrade Facility
- Downgrade Facility (financial crisis recovery)
- Change Arena Adaptation
- Summon Hero (Summoning Chamber panel)

Backend Requirements:
- HQ data endpoint — `GET /api/v1/hq`
- Facility upgrade/downgrade — `POST /api/v1/hq/upgrade`, `POST /api/v1/hq/downgrade`
- Arena adaptation change — `POST /api/v1/hq/optimize`
- Passive bonuses calculation — `HeadquartersService`, `FacilityType::getPassiveBonuses()`

Implementation:
- **Route:** `GET /app/hq` — `HeadquartersController`
- **Stimulus:** `hq_controller.js`, `summoning_controller.js`, `dashboard_banner_controller.js`
