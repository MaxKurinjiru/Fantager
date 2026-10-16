# Item/Equipment Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [item-system.md](../systems/item-system.md)

**When:** Equip, unequip, repair, or dismantle items for one hero.

**Status:** There is **no standalone inventory page**. `GET /app/inventory` **redirects**. Equipment is the **hero-detail Equipment tab**. Interaction is **click-to-equip** on the paperdoll / rucksack — **not** drag-and-drop. Occupancy (two-handed, dual-wield, two rings) is **locked** ([#8](../product/known-issues.md)). Enchanting is **not in the live game** ([#2](../product/known-issues.md) Resolved / A7.4 Cut).

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
- Unequip (✕) on a filled slot

**Rucksack** (`components/item/rucksack_items.html.twig`)

- Team items that are **not** currently equipped (`equippedHero` is null)
- Name, rarity, slot type, durability, numeric bonuses
- Equip / Repair / Dismantle buttons

No rucksack filter/sort controls. No equipment-vs-naked stat comparison panel on this tab (derived combat stats live on hero overview). Ring items show Equip Ring / Equip Ring 2; dual-wieldable one-handers show Main Hand / Off-Hand.

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

## Planned / not implemented

| Feature | Status |
|---------|--------|
| Standalone Inventory screen (`/app/inventory` as its own UI) | Not implemented — route redirects |
| Drag-and-drop equip / paperdoll | Not implemented — click-to-equip |
| Rucksack filter / sort | Not implemented |
| Compare items / swap between two heroes | Not implemented |
| Stat summary with vs without equipment on this tab | Not implemented |
| Craft Item | Deferred — [future/crafting-system.md](../future/crafting-system.md) |
| Enchant Item | Cut — not in the live game (#2 / A7.4) |
| Craft API endpoints | Deferred |

---

## Frontend

| Piece | Location |
|-------|----------|
| Host page | `templates/hero/detail.html.twig` (`?tab=equipment`) |
| Paperdoll / rucksack | `templates/components/item/equip_manager.html.twig`, `rucksack_items.html.twig` |
| Stimulus | `equipment` |
