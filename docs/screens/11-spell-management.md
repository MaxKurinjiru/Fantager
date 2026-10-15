# Spell Management Screen & Academy Store

Reference: [screens/overview-legacy.md](overview-legacy.md#11-spell-management-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [spell-system.md](../systems/spell-system.md)

**When:** Equip a hero’s spell slots, or buy spells from the Academy.

**Status:** Hero spells live on **Hero Detail → Spells**. `GET /app/spells` **redirects** to that tab (or `/app/heroes` if no `hero_id`). Academy store is `/app/academy`. Seeded catalog is **T1–T2**; mastery can reach T5 (A7.11). No essence / enchanting.

**Live UI:** `templates/hero/detail.html.twig` (spells tab), `templates/spell/academy.html.twig`, `templates/components/spell/`.

---

## Implemented

### Routing

`Web\SpellController::index()`:

- `GET /app/spells?hero_id={id}` → `/app/heroes/{id}?tab=spells`
- `GET /app/spells` → `/app/heroes`

### Hero Detail → Spells

- Equipped slots 1–N limited by `magicCapacity` (`trainee_manager` / spell slots) — equip / unequip
- School mastery for Fire, Water, Air, Earth, Light, Dark
- Inner tabs: **Learned** vs **Store** (same academy cards, scoped to this hero)

### Academy (`GET /app/academy`)

- Trainee `<select>` (`?hero_id=`)
- School filter, tier filter (**1** and **2** only)
- Treasury badge
- Spell cards: description, cooldown, mana, gold cost, learn eligibility vs mastery

### Backend

| Action | Endpoint |
|--------|----------|
| Library | `GET /api/v1/spells` |
| Learn | `POST /api/v1/heroes/{id}/spells/learn` |
| Equip / unequip | `POST /api/v1/heroes/{id}/spells/equip`, `/unequip` |

---

## Planned

- Seeded T3–T5 spells, or an explicit “unused tiers” product lock (A7.11)
- Standalone `/app/spells` page (redirects on purpose)

---

## Frontend

| Piece | Location |
|-------|----------|
| Hero tab | `templates/hero/detail.html.twig` + `templates/components/spell/`, `school_mastery.html.twig` |
| Academy | `templates/spell/academy.html.twig` |
| Stimulus | `spellbook` |
