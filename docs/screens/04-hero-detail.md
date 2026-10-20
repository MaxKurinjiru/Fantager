# Hero Detail Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [hero-system.md](../systems/hero-system.md), [hero-chronicle-system.md](../systems/hero-chronicle-system.md)

**When:** Inspect one hero — overview, equipment, spells, history.

**Status:** Live at `GET /app/heroes/{id}`. Tabs: `overview`, `equipment`, `spells`, `history`. There is **no** Training tab (assignment sits on overview). There is **no** “assign to formation” control. XP bar uses locked cumulative thresholds; displayed level is stored `hero.level`. A hero with status `dead` (combat death or dismissal) stays on this URL, including the history tab. The graveyard links here while the row exists.  
**Lock:** [hero-system.md — hero level invariant](../systems/hero-system.md#locked-hero-level-invariant), [item-system.md — item occupancy](../systems/item-system.md#locked-item-occupancy)

**Live UI:** `templates/hero/detail.html.twig`, `templates/components/hero/`.

---

## Implemented

### Header (`header_card.html.twig`)

- Avatar, status badge, inline rename
- Race, age + phase, mortality warning
- Base OVR, complex rating, gold value, weekly salary
- Level from stored `hero.level` (do not derive from XP). XP bar via `hero_xp_bar()` (`C(level)` / `C(level + 1)`)
- Form, fatigue, morale bars
- Trait badge + description when present
- Manage menu (available combatant): sell, dismiss, promote to trainer (cap / unassigned)

### Overview tab

- Primary attributes with gear bonuses (`attributes_panel.html.twig`)
- Derived combat stats: HP, ATK, SP, DEF, RES, INIT (`derived_combat_stats_panel.html.twig`)
- Trainer assignment / unassign / forecast ([05-training.md](05-training.md))

Match record (`matches_played`, win rate, fallen, training counts) is on the **History** tab, not overview. Magic capacity is on the **Spells** tab.

### Equipment tab

See [10-item-equipment.md](10-item-equipment.md). Nine slots, two rings, click-to-equip, gear vs unequipped, team wear, rucksack filters.

### Spells tab

See [11-spell-management.md](11-spell-management.md). Equipped slots, school mastery, learned list, inline academy store.

### History tab

- Combat/training summary tiles (`combat_stats.html.twig`): matches, win rate, fallen, completed trainings, total stat gain
- Weapon mastery panel
- Last chronicle entries via `HeroChroniclePresenter` (`recent_activity.html.twig`) — summons, transfers, matches, training, mastery, death. Server-rendered; no hero-chronicle REST API.

### Actions

| Action | How |
|--------|-----|
| Rename | `hero-rename` → `PUT /api/v1/heroes/{id}` |
| Sell | Modal → marketplace listing |
| Dismiss | Modal → `POST /api/v1/heroes/{id}/dismiss` |
| Promote | `POST /api/v1/training/trainers/promote` |
| Assign / unassign trainer | Training APIs on overview |
| Equip / spells | Other tabs |

### Backend

- **Web:** `GET /app/heroes/{id}` → `Web\HeroController::detail()` (`?tab=`)
- **API:** `GET /api/v1/heroes/{id}` includes `trait`, `ratings` via `HeroService::serialize()`

---

## Sketch leftover

Not a contract:

- Dedicated Training tab
- Quick-add to formation
- 3D avatar

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/hero/detail.html.twig` |
| Header / panels | `templates/components/hero/` |
| Stimulus | `hero_rename`, `hero_dismiss`, `hero_sell`, `hero-promote`, `training`, `equipment`, `spellbook`, `tab` |
