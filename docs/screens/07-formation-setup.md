# Formation Setup Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#7-formation-setup-screen) (historical), [formation-system.md](../systems/formation-system.md), [architecture/screen-code-map.md](../architecture/screen-code-map.md)

**When:** Edit saved lineups, or prepare a specific fixture (`?fixture_id=`).

**Status:** Lineup drag-and-drop is **live**. Synergies, clone, and simulate are **Planned** (table below) — do not describe them as live. Per-slot tactics UI is A7.6. Save-time unique `hero_id` is [#10](../product/known-issues.md).

**Live UI:** `templates/formation/index.html.twig`, `templates/components/formation/`, Stimulus `formation`.

---

## Implemented

### Displayed information

**Formation selector** (normal mode, not match prep)

- Up to 4 saved formations (names; default labelled)
- “Create new” option
- Delete button for the selected saved formation

**Match prep** (`?fixture_id=` when the fixture belongs to the team)

- Opponent name and kickoff time
- Assign default / a saved formation / keep a custom match lineup
- Promote custom match lineup to a saved formation
- Does **not** show the opponent’s formation

**Strategy panel**

- Formation name
- Set as default (disabled in match-prep)
- Pre-match approach: Aggressive / Balanced / Defensive (`approach`)
- Movement goal: one formation-wide `strategy.movement_goal` (`advance_to_melee` | `seek_cover_ranged` | `protect_backline` | `flank_rear`), copied onto every slot on save
- Spell priorities are **not** editable; saves always send `spell_priorities: []` (engine / NPC-filled lists)

**Pitch (3 front + 3 back)**

- Drag-and-drop hero cards or empty slots
- Occupied slot: race avatar, name, level, race; remove (✕)
- Empty slot: quick-assign `<select>`
- Unavailable statuses shown on pool/slot badges

**Hero pool**

- Combatants except `dead` / `retired`
- Drag available heroes onto the pitch; drop back to the pool to clear
- No filter by role / stats / race

Pitch cards do **not** show a primary-role icon or a full quick-stat grid.

### Actions

- Drag & drop between pool and pitch; swap by dropping onto an occupied slot
- Quick-assign from the empty-slot select
- Clear slot (✕)
- Save (`PUT /api/v1/formations`, or fixture formation PUT in match prep)
- Set as default (checkbox on save)
- Create / delete saved formation
- In match prep: change assignment mode; promote custom lineup

### Backend

| Method | Path | Notes |
|--------|------|-------|
| GET | `/app/formation` | Optional `?fixture_id=` |
| GET | `/api/v1/formations` | Saved formations |
| PUT | `/api/v1/formations` | Upsert; 0–6 slots; duplicate `hero_id` **not** rejected (#10) |
| DELETE | `/api/v1/formations/{id}` | |
| GET/PUT | `/api/v1/fixtures/{id}/formation` | Default / saved / custom |
| POST | `/api/v1/fixtures/{id}/formation/promote` | Custom → saved |

Match **kickoff** still requires ≥ 6 combat-ready heroes; incomplete lineups forfeit ([combat-system.md](../systems/combat-system.md#match-eligibility)). Empty slots are allowed at **save**.

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
| View opponent formation | Planned / not implemented |

UI Implement for L1/L2 editors is A7.6. Do not invent synergy tables (#7) or a simulate sandbox (#9) here.
