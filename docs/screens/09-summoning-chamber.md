# Summoning Chamber Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#9-summoning-chamber-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [summoning-system.md](../systems/summoning-system.md)

**When:** Spend a cycle summon to roll a junior hero.

**Status:** Panel inside HQ — not a standalone page. `GET /app/summon` → `/app/hq?facility=summoning_chamber`; history → `&subtab=history`. **Genie dual rule:** players use relationship ≥ 50 (Genie can roll all races); NPC marketplace may apply a stricter Genie-only filter (A7.9). `summon_stat_total_cap` is unused (A6.5).

**Live UI:** `templates/components/hq/facility_panel/_summoning.html.twig`, `templates/components/summoning/`.

---

## Implemented

### Cycle header

- Summons used / max this cycle (kingdom game speed)
- Gold cost per summon
- Summon button disabled with translated reason when unavailable (limit, roster full, etc.)

### Summon subtab

- Home arena theme (HQ race optimization) and compatible-race cards (avatar, name, combat-bonus blurb)
- Portal idle / channeling / **reveal card**: name, race, level 1, primary attributes, trait badge when present
- Inspect → hero detail; summon again reloads

Starting level is 1. Age/stat **preview ranges** from the original sketch are **not** a separate live panel; expected ranges are implied by race cards + chamber bonuses, not listed as min/max numbers on this UI.

### History subtab

Table: hero name (or fallen/dismissed), race, gold cost, timestamp. Filter by race, sort by date, pagination.

### Backend

| Piece | Location |
|-------|----------|
| Panel | `/app/hq?facility=summoning_chamber` |
| Status | `GET /api/v1/summoning/status` |
| Summon | `POST /api/v1/summoning` — `HeroService::serialize()` including nullable `trait` |

---

## Planned

Original sketch extras — do not treat as a contract:

- Standalone `/app/summon` page (redirects)
- Explicit min/max age and expected-stat range widgets
- Player-facing Genie-only filter (decision is A7.9)

---

## Frontend

| Piece | Location |
|-------|----------|
| Panel | `templates/components/hq/facility_panel/_summoning.html.twig` |
| Cycle / races / portal | `templates/components/summoning/` |
| Stimulus | `summoning` (`data-summoning-traits-value` from `hero_trait_js_labels()`) |
