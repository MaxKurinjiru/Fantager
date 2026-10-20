# Formation Setup Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [formation-system.md](../systems/formation-system.md)

**When:** Edit saved lineups, or prepare a specific fixture (`?fixture_id=`).

**Status:** Lineup drag-and-drop is **live**. Per-slot L1/L2 tactics editor is **live**. Synergies are **Cut**. Save rejects duplicate `hero_id`.  
**Lock:** [formation-system.md — L1/L2 editor](../systems/formation-system.md#locked-l1l2-editor)  
Slot tactics: Healer / CC / Nuker buttons write thin L2 lists from equipped spells. Spec: [spell-system.md](../systems/spell-system.md#l0-utility-and-l2-templates).

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
- Match-ready panel (`ready_slots` / 6, per-slot empty / unready / duplicate issues)
- Fill empty slots from the combat-ready roster (writes a custom match lineup; does not edit the saved default)
- Promote custom match lineup to a saved formation
- Does **not** show the opponent’s formation

**Strategy panel**

- Formation name
- Set as default (disabled in match-prep)
- Pre-match approach: Aggressive / Balanced / Defensive (`approach`)
- Movement goal: one formation-wide `strategy.movement_goal` (`advance_to_melee` | `seek_cover_ranged` | `protect_backline` | `flank_rear`), copied onto every slot on save and merged with that slot’s L1/L2
- Slot tactics panel (click a pitch slot): L1 `target_order` + `fallback`; thin L2 equipped `spell_priorities`; Healer / CC / Nuker templates. Empty lists keep L0.

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
- In match prep: change assignment mode; fill from roster; promote custom lineup

### Backend

| Method | Path | Notes |
|--------|------|-------|
| GET | `/app/formation` | Optional `?fixture_id=` |
| GET | `/api/v1/formations` | Saved formations |
| PUT | `/api/v1/formations` | Upsert; 0–6 slots; duplicate `hero_id` rejected (422) |
| DELETE | `/api/v1/formations/{id}` | |
| GET/PUT | `/api/v1/fixtures/{id}/formation` | Default / saved / custom |
| POST | `/api/v1/fixtures/{id}/formation/fill-roster` | Fill empty / unready slots from the combat-ready roster |
| POST | `/api/v1/fixtures/{id}/formation/promote` | Custom → saved |

Match **kickoff** uses the assigned lineup if it has 6 unique combat-ready heroes; otherwise the team default. Kickoff does **not** fill a player roster. Forfeit when that formation is short ([combat-system.md](../systems/combat-system.md#match-eligibility)). Empty slots are allowed at **save** (0–6 assigned heroes; the UI does not require a hero). Before kickoff, **Fill empty slots from roster** writes a match-specific formation using the same fill order as NPC kickoff (`id` ascending).

See `assets/controllers/formation_controller.js` and [formation-system.md](../systems/formation-system.md).

---

## Parked

Index: [known-issues.md](../product/known-issues.md) § Later product decisions. Unique to this screen:

- Clone formation
- Quick Fill (auto-suggest)
- Hero pool filter by role / stats / race
- View opponent formation

L3 sequences, Flexible AI, `enemy_status` / `lowest_hp_enemy`, synergy tables, and a simulate sandbox (`POST /api/v1/formations/simulate`) are **Cut**. Chemistry is flavor: [team-system.md](../systems/team-system.md#locked-chemistry-flavor). Do not invent L3, synergy, or a simulate sandbox here.
