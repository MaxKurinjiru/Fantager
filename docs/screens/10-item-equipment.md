# Item/Equipment Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [item-system.md](../systems/item-system.md)

**When:** Equip, unequip, repair, or dismantle items for one hero.

**Status:** There is **no standalone inventory page**. `GET /app/inventory` **redirects**. Equipment is the **hero-detail Equipment tab**. Interaction is **click-to-equip** on the paperdoll / rucksack — **not** drag-and-drop.  
**Lock:** [item-system.md — item occupancy](../systems/item-system.md#locked-item-occupancy), [item-system.md — no enchanting](../systems/item-system.md#locked-no-enchanting)

---

## Implemented

### Routing

`Web\ItemController::index()`:

- `GET /app/inventory?hero_id={id}` → `/app/heroes/{id}?tab=equipment`
- `GET /app/inventory` (no `hero_id`) → `/app/heroes`

`templates/item/index.html.twig` is leftover and **not** served.

Buying and selling items is the **Marketplace** (`/app/marketplace`), including basic merchant stock.

### Displayed information (Hero Detail → Equipment)

**Paperdoll** (`components/item/equip_manager.html.twig`) — nine slots, **two rings**:

- Head, Amulet, Body, Hands, Main Hand, Off-Hand, Ring, Ring 2, Feet
- Equipped item name + rarity styling; empty-slot placeholder
- Race-restricted empty slots show a short Restricted label (full reason in `title`)
- Unequip (✕) on a filled slot

**Rucksack** (`components/item/rucksack_items.html.twig`)

- Team items that are **not** currently equipped (`equippedHero` is null)
- Name (from `name_key` when set), rarity, slot type, style, durability, numeric bonuses
- Filter by slot and weapon/armor style
- Equip / Repair (shows gold cost) / Dismantle buttons
- Race-restriction copy when the selected hero cannot wear the item (Giant `off_hand`, Ent weapon / shield / armor)

Ring items show Equip Ring / Equip Ring 2; dual-wieldable one-handers show Main Hand / Off-Hand.

**Gear compare** (`components/item/gear_compare.html.twig`) — equipped combat stats vs the same hero with empty slots.

**Team wear** (`components/item/team_wear.html.twig`) — which combatant is wearing which items. Not a standalone inventory page.

### Actions

| Action | How |
|--------|-----|
| Equip | Click Equip on a rucksack row → `PUT /api/v1/heroes/{heroId}/equipment` |
| Unequip | Click ✕ on the paperdoll slot |
| Repair | Click Repair when durability < 100 → `POST /api/v1/items/{id}/repair` |
| Dismantle | Confirm, then `POST /api/v1/items/dismantle` (unequipped only; gold scrap) |
| Buy / sell | Marketplace — not this tab |

`ItemService::equip()` uses occupancy (#8): two-handed clears `off_hand`; dual-wield one-handers may target `off_hand`; rings go in `ring` or `ring_2`.

### Backend

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/inventory` | Redirect only |
| GET | `/api/v1/items` | Inventory list (JSON; tab is mostly server-rendered) |
| PUT | `/api/v1/heroes/{heroId}/equipment` | Equip / unequip |
| POST | `/api/v1/items/dismantle` | Scrap for gold |
| POST | `/api/v1/items/{id}/repair` | Restore durability |

Validation: race restrictions and slot compatibility in `ItemService`.

---

## Parked

Index: [known-issues.md](../product/known-issues.md) § Later product decisions. Unique to this screen:

- Standalone Inventory screen (`/app/inventory` as its own UI; route redirects today)
- Drag-and-drop equip / paperdoll (live is click-to-equip)
- Compare items / swap between two heroes

Crafting is **Deferred**: [future/crafting-system.md](../future/crafting-system.md). Enchanting is **Cut** — [Locked: no enchanting](../systems/item-system.md#locked-no-enchanting).

---

## Frontend

| Piece | Location |
|-------|----------|
| Host page | `templates/hero/detail.html.twig` (`?tab=equipment`) |
| Paperdoll / rucksack | `templates/components/item/equip_manager.html.twig`, `rucksack_items.html.twig` |
| Gear compare / team wear | `templates/components/item/gear_compare.html.twig`, `team_wear.html.twig` |
| Stimulus | `equipment`, `rucksack-filter` |
