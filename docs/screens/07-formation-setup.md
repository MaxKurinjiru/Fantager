# Formation Setup Screen

Reference: [screens-overview.md](../screens-overview.md#7-formation-setup-screen), [formation-system.md](../systems/formation-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

## Implemented (current UI)

Displayed Information:
- **Formation Selector:**
	- Up to 4 saved formations (editable names)
	- Default formation indicator
- **Formation Layout (visual grid):**
	- **Front Line (3 positions)**
	- **Back Line (3 positions)**
	- Positions show drag&drop hero cards or empty slots
- **Hero Cards (in layout):**
	- Avatar/icon
	- Name
	- Level
	- Primary role/specialization (icon)
	- Quick stats
- **Hero Pool (sidebar):**
	- Available heroes for assignment
- **Strategy Settings Panel:**
	- **Pre-match Approach:** Aggressive / Balanced / Defensive (radio buttons) — formation-level `approach`
	- **Movement Goal:** one formation-wide `strategy.movement_goal` (`advance_to_melee` | `seek_cover_ranged` | `protect_backline` | `flank_rear`), copied onto every slot on save
	- Spell priorities are **not editable** in the UI; saves always send `spell_priorities: []` (combat engine applies defaults / NPC-filled lists)

Possible Actions/Buttons:
- **Drag & Drop Heroes** - move heroes between Pool and Layout positions
- **Swap Heroes** - swap two heroes
- **Clear Slot** - remove hero from position
- **Save Formation** - save changes (`PUT /api/v1/formations`)
- **Set as Default** - mark formation as default
- **View Opponent Formation** (before match) - when preparing a fixture (`?fixture_id=`)

Backend Requirements (implemented):
- Formation GET/PUT/DELETE endpoints (`/api/v1/formations`)
- Fixture formation assign/promote (`/api/v1/fixtures/{id}/formation`)
- Formation **save** accepts 0–6 slots (empty slots allowed). **Match entry** still requires ≥ 6 combat-ready heroes; incomplete lineups forfeit at kickoff (see [combat-system.md](../systems/combat-system.md#match-eligibility))
- Persisted fields: `approach`, per-slot `strategy.movement_goal`, empty `spell_priorities: []`

See `assets/controllers/formation_controller.js` and [formation-system.md](../systems/formation-system.md).

---

## Planned / not implemented

These appear in older screen sketches but are **not** in the current Formation UI or save payload:

| Feature | Status |
|---------|--------|
| Per-hero targeting priority (`target_order` / Flexible) | Planned — engine may read L1 `strategy` JSON; UI does not edit it |
| Action sequence (Attack / Cast / Defend / Heal / …) | Planned / not implemented |
| Spell priority editor (spell, conditions, targets) | Planned — UI always saves `spell_priorities: []` |
| Conditional tactics editor | Planned / not implemented |
| Formation synergy indicator (race / role / chemistry) | Planned / not implemented |
| Clone Formation | Planned / not implemented |
| Test Formation (simulation / practice match) | Planned (`POST /api/v1/formations/simulate`) |
| Quick Fill (auto-suggest optimal formation) | Planned / not implemented |
| Hero pool filter by role / stats / race | Planned / not implemented |
| Synergy calculation API | Planned / not implemented |

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
