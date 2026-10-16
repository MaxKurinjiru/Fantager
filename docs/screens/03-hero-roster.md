# Hero Roster Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#3-hero-roster-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [hero-system.md](../systems/hero-system.md)

**When:** Browse the team’s combatants.

**Status:** Live at `GET /app/heroes`. Sketch extras are **Cut** (A7.7 / [#13](../product/known-issues.md) Resolved) — see [Locked: roster extras](#locked-roster-extras).

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
- Batch-operation APIs: **Cut** (A7.7) — do not add them

---

## Locked: roster extras

**#13 (A7.7, 16 Oct 2026).** The live roster card and filter bar above are the contract. Cut from this screen (do not re-add without a new product decision):

- Morale value + icon on the card (`data-morale` sort key stays)
- Equipped item icons
- Tired / training status on the card (training stays a dashboard count + hero-detail overview / `/app/training`)
- Level filter control (sort by level stays)
- Per-card quick-action dropdown (train, equip, spells, sell)
- Multi-select + batch training / formation assignment from the grid
- Marketplace CTA in the roster header (Summon CTA stays)
- Server-side list filtering / batch-operation APIs

Train, equip, spells, and sell stay on hero detail. Trainer assign stays `/app/training` and hero-detail overview. A later product decision is required before adding any sketch extra.

---

## Planned

None for this screen. Sketch extras are **Cut** ([#13](../product/known-issues.md) Resolved / A7.7).

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/hero/roster.html.twig` |
| Card | `templates/components/hero/card.html.twig` |
| Trait / status / attributes | `trait_badge`, `status_badge`, `compact_attributes`, `compact_combat_stats`, `rating_badge` |
| Stimulus | `roster_filter` |
| i18n | `heroes.traits.{key}.name` / `.desc` — [hero-system.md](../systems/hero-system.md) § Player UI |
