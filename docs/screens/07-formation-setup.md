# Formation Setup Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#7-formation-setup-screen) (historical), [formation-system.md](../systems/formation-system.md), [architecture/screen-code-map.md](../architecture/screen-code-map.md)

**When:** Edit saved lineups, or prepare a specific fixture (`?fixture_id=`).

**Status:** Lineup drag-and-drop is **live**. Per-slot L1/L2 tactics editor is **live** (A7.6 / [#12](../product/known-issues.md)). Synergies are **Cut** (#9 / A7.5). Clone and simulate stay **Planned** (table below) — do not describe them as live. Save rejects duplicate `hero_id` (A6.1 / [#10](../product/known-issues.md)). Lock: [formation-system.md](../systems/formation-system.md#locked-l1l2-editor).

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
- Movement goal: one formation-wide `strategy.movement_goal` (`advance_to_melee` | `seek_cover_ranged` | `protect_backline` | `flank_rear`), copied onto every slot on save and merged with that slot’s L1/L2
- Slot tactics panel (click a pitch slot): L1 `target_order` + `fallback`; thin L2 equipped `spell_priorities`. Empty lists keep L0.

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
| PUT | `/api/v1/formations` | Upsert; 0–6 slots; duplicate `hero_id` rejected (422) |
| DELETE | `/api/v1/formations/{id}` | |
| GET/PUT | `/api/v1/fixtures/{id}/formation` | Default / saved / custom |
| POST | `/api/v1/fixtures/{id}/formation/promote` | Custom → saved |

Match **kickoff** uses the assigned lineup if it has 6 unique combat-ready heroes; otherwise the default; otherwise fills from the roster. Forfeit only when the team has < 6 combat-ready heroes ([combat-system.md](../systems/combat-system.md#match-eligibility)). Empty slots are allowed at **save**.

See `assets/controllers/formation_controller.js` and [formation-system.md](../systems/formation-system.md).

---

## Planned / not implemented

These appear in older screen sketches but are **not** in the current Formation UI or save payload:

| Feature | Status |
|---------|--------|
| Per-hero targeting priority (`target_order` / Flexible) | **L1 live** (ordered enemy slots + fallback). Flexible AI Cut |
| Action sequence (Attack / Cast / Defend / Heal / …) | Cut / deferred L3 |
| Spell priority editor (spell, conditions, targets) | **Thin L2 live** — equipped spells; `always` / HP-below; `priority` / ally / self. `enemy_status` / `lowest_hp_enemy` Cut |
| Conditional tactics editor | Cut / deferred L3 |
| Formation synergy indicator (race / role / chemistry) | Cut (#9 / A7.5) — chemistry is flavor ([team-system.md](../systems/team-system.md#locked-chemistry-flavor)) |
| Clone Formation | Planned / not implemented |
| Test Formation (simulation / practice match) | Planned (`POST /api/v1/formations/simulate`; later product decision) |
| Quick Fill (auto-suggest optimal formation) | Planned / not implemented |
| Hero pool filter by role / stats / race | Planned / not implemented |
| Synergy calculation API | Cut (#9 / A7.5) |
| View opponent formation | Planned / not implemented |

L1/L2 editor is locked (A7.6 / #12). Do not invent L3 sequences, synergy tables (#9 Cut), or a simulate sandbox here.
