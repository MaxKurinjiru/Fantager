# Item/Equipment Screen

Reference: [screens-overview.md](../screens-overview.md#10-itemequipment-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Inventory Panel: owned items list with filters; item icon, name, rarity, slot type, attribute bonuses, durability, equipped-by
- Hero Equipment Panel: selected hero paperdoll/grid with equipment slots and stat comparison

Possible Actions/Buttons:
- Equip Item, Unequip Item, Swap Items, Sell Item, Dismantle Item, Craft Item, Repair Item, Enchant Item, Filter/Sort, Compare Items

Backend Requirements:
- Inventory list endpoint
- Equipment GET/PUT endpoints
- Dismantle/repair/craft endpoints
- Validation (race restrictions, slot compatibility)

Sections to fill:
- Display data contract (fields returned by API)
- Actions and API calls
- Validation and server-side checks
- UX notes and edge cases
- Tests and mocks
- Implementation notes
