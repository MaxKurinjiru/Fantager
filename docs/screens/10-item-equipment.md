# Item/Equipment Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#10-itemequipment-screen), [item-system.md](../systems/item-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

## Implemented (current UI)

There is **no standalone inventory page**. `GET /app/inventory` redirects to the hero roster (or to a hero’s **equipment** tab when `?hero_id=` is set). Equipment management lives on **Hero Detail → Equipment tab**; buying/selling uses the **Marketplace**.

Displayed Information:
- **Hero Equipment Panel** (paperdoll/grid): equipment slots for the selected hero, equipped items, stat comparison
- **Rucksack / owned items** list for that team (filters, rarity, slot, durability, equipped-by) on the same tab

Possible Actions/Buttons (implemented):
- Equip Item, Unequip Item, Swap Items
- Dismantle Item (gold scrap)
- Repair Item
- Filter/Sort (within the equipment tab)
- Sell / buy via Marketplace (`/app/marketplace`) and basic merchant (`purchaseBasicItem`)

Backend Requirements (implemented):
- `GET /api/v1/items` — inventory list
- `PUT /api/v1/heroes/{heroId}/equipment` — equip/unequip
- `POST /api/v1/items/dismantle` — dismantle for gold scrap
- `POST /api/v1/items/{id}/repair` — repair durability
- Validation (race restrictions, slot compatibility)

---

## Planned / not implemented

| Feature | Status |
|---------|--------|
| Standalone Inventory screen (`/app/inventory` as its own UI) | Not implemented — route redirects to heroes / hero equipment tab |
| Craft Item | Planned / deferred — see [future/crafting-system.md](../future/crafting-system.md) |
| Enchant Item | Planned / not designed — see [item-system.md](../systems/item-system.md) |
| Craft API endpoints | Planned / deferred |

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
