# Item/Equipment Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#10-itemequipment-screen) (historical), [item-system.md](../systems/item-system.md), [architecture/screen-code-map.md](../architecture/screen-code-map.md)

**When:** Equip, unequip, repair, or dismantle items for one hero.

**Status:** There is **no standalone inventory page**. `GET /app/inventory` **redirects**. Equipment is the **hero-detail Equipment tab**. Interaction is **click-to-equip** on the paperdoll / rucksack — **not** drag-and-drop. Occupancy (two-handed, dual-wield, two rings) is [#8](../product/known-issues.md). Enchanting is [#2](../product/known-issues.md).

---

## Implemented

### Routing

`Web\ItemController::index()`:

- `GET /app/inventory?hero_id={id}` → `/app/heroes/{id}?tab=equipment`
- `GET /app/inventory` (no `hero_id`) → `/app/heroes`

`templates/item/index.html.twig` is leftover and **not** served.

Buying and selling items is the **Marketplace** (`/app/marketplace`), including basic merchant stock.

### Displayed information (Hero Detail → Equipment)

**Paperdoll** (`components/item/equip_manager.html.twig`) — eight slots, **one ring**:

- Head, Amulet, Body, Hands, Main Hand, Off-Hand, Ring, Feet
- Equipped item name + rarity styling; empty-slot placeholder
- Unequip (✕) on a filled slot

**Rucksack** (`components/item/rucksack_items.html.twig`)

- Team items that are **not** currently equipped (`equippedHero` is null)
- Name, rarity, slot type, durability, numeric bonuses
- Equip / Repair / Dismantle buttons

No rucksack filter/sort controls. No equipment-vs-naked stat comparison panel on this tab (derived combat stats live on hero overview). No second ring slot.

### Actions

| Action | How |
|--------|-----|
| Equip | Click Equip on a rucksack row → `PUT /api/v1/heroes/{heroId}/equipment` |
| Unequip | Click ✕ on the paperdoll slot |
| Repair | Click Repair when durability < 100 → `POST /api/v1/items/{id}/repair` |
| Dismantle | Confirm, then `POST /api/v1/items/dismantle` (unequipped only; gold scrap) |
| Buy / sell | Marketplace — not this tab |

`ItemService::equip()` only clears the **target** slot. Two-handed occupying both hands, dual-wield, and two rings are **not** live (#8).

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

## Planned / not implemented

| Feature | Status |
|---------|--------|
| Standalone Inventory screen (`/app/inventory` as its own UI) | Not implemented — route redirects |
| Drag-and-drop equip / paperdoll | Not implemented — click-to-equip |
| Rucksack filter / sort | Not implemented |
| Compare items / swap between two heroes | Not implemented |
| Stat summary with vs without equipment on this tab | Not implemented |
| Two rings / two-handed occupying off-hand / dual-wield | Open design — #8 / A7.3 |
| Craft Item | Deferred — [future/crafting-system.md](../future/crafting-system.md) |
| Enchant Item | Not designed — #2 |
| Craft API endpoints | Deferred |

---

## Frontend

| Piece | Location |
|-------|----------|
| Host page | `templates/hero/detail.html.twig` (`?tab=equipment`) |
| Paperdoll / rucksack | `templates/components/item/equip_manager.html.twig`, `rucksack_items.html.twig` |
| Stimulus | `equipment` |
