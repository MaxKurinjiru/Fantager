# Hero Roster Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#3-hero-roster-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [hero-system.md](../systems/hero-system.md)

**When:** Browse the team’s combatants.

**Status:** Live at `GET /app/heroes`. Card extras from the original sketch (item icons, morale on the card, tired/training status, quick-action dropdown, batch training, multi-select, level filter) are **not** live — Wave 7 / A7.7 if product re-specs them.

**Live UI:** `templates/hero/roster.html.twig`, `templates/components/hero/card.html.twig`. Filter/sort is **client-side** (`roster_filter`); the page does not send filter query params.

---

## Implemented

### Displayed information (hero card)

- Name (link to hero detail)
- Race (avatar + translated name)
- Level
- Personality trait badge when present (name + category color; tooltip with effect)
- Age + phase icon (Junior / Prime / Veteran / Elder)
- Form (%) and fatigue (%)
- Compact primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK)
- Compact combat stats (HP, ATK, SP, DEF, RES, INIT)
- Base OVR rating badge
- Status badge: `available`, `in_match`, `selling`, `recovering`, `dead` (not “tired” / “training”)
- **Inspect** → `/app/heroes/{id}`

Morale is stored on the card as a `data-morale` sort key only — **not** shown as a value or icon. Equipped-item icons are **not** on the card.

### Filtering and sorting (client)

Rendered in the filter bar; `roster_filter` hides/sorts already-rendered cards.

| Control | Live values |
|---------|-------------|
| Text search | Name |
| Race | All races, or one of the eight playable races |
| Status | available, in_match, selling, recovering, dead |
| Sort | name, level, base OVR, age, morale (hidden field), form, STR, DEX, KON, SPD, INT |

There is **no** level filter control. Sort does not include WIL / CHA / LCK.

### Actions

- Open hero detail (name or Inspect)
- **Summon** → `/app/hq?facility=summoning_chamber`
- Empty state: same summoning CTA

No per-card dropdown (train / equip / spells / sell), no multi-select, no batch training, no formation assignment from this grid.

### Backend

- **Web:** `GET /app/heroes` → `Web\HeroController::roster()` — `HeroRepository::findCombatantsByTeam()`
- **API:** `GET /api/v1/heroes` returns the team list via `HeroService::serialize()` — **no** filter/sort query parameters; the roster page does not call this endpoint
- Batch-operation APIs: **not** implemented

---

## Planned

Original sketch / A7.7 candidates — do not treat as a contract:

- Morale value + icon on the card
- Equipped item icons
- Tired / training status on the card (training today is a dashboard count + hero-detail training tab)
- Level filter control
- Quick-action dropdown (train, equip, spells, sell)
- Multi-select + batch training / formation assignment
- Marketplace CTA from the roster header
- Server-side list filtering

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/hero/roster.html.twig` |
| Card | `templates/components/hero/card.html.twig` |
| Trait / status / attributes | `trait_badge`, `status_badge`, `compact_attributes`, `compact_combat_stats`, `rating_badge` |
| Stimulus | `roster_filter` |
| i18n | `heroes.traits.{key}.name` / `.desc` — [hero-system.md](../systems/hero-system.md) § Player UI |
