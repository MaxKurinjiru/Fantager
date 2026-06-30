# Item System

Reference: [game-summary.md](../game-summary.md#28-item-system)

Purpose: Document items, equipment slots, dismantling, durability/repair, and marketplace interactions.

---

## Item Entity

Items are represented by the `Item` entity (`src/Entity/Item/Item.php`). Key fields:

| Field | Type | Description |
|-------|------|-------------|
| `owner_team_id` | FK → Team | The team that currently owns the item |
| `equipped_hero_id` | FK → Hero (nullable) | Hero the item is equipped on; `null` if in rucksack |
| `equipped_slot` | `ItemSlotType` enum (nullable) | The specific slot where the item is equipped |
| `name` | string | Item display name |
| `slot_type` | `ItemSlotType` enum | The slot this item can be equipped in |
| `category` | `ItemCategory` enum | Weapon, Shield, Spell Accelerator, Armor, Accessory, Material |
| `rarity` | `ItemRarity` enum | Common, Uncommon, Rare, Epic, Legendary, Mythic |
| `durability` | int (0–100) | Current durability; decreases after combat |
| `status` | `ItemStatus` enum | `available` or `selling` (in marketplace escrow) |
| `bonuses` | JSON | Map of attribute bonuses (e.g. `{"str": 3, "dex": 2}`) |
| `special_effects` | JSON | Optional list of special effect definitions |

---

## Equipment Slots

Each hero has 8 equipment slots corresponding to `ItemSlotType`:

| Slot | Description |
|------|-------------|
| `main_hand` | Primary weapon |
| `off_hand` | Shield or secondary weapon |
| `head` | Helmet or headpiece |
| `body` | Chest armour |
| `hands` | Gloves |
| `feet` | Boots |
| `amulet` | Neck accessory |
| `ring` | Ring accessory |

An item can only be equipped in the slot matching its `slot_type`. Equipping an item to a slot automatically unequips any previously equipped item in that slot.

---

## Equip / Unequip Rules

- An item must have `status = available` to be equipped.
- The item's `owner_team_id` must match the hero's team.
- The item's `slot_type` must match the target `slot`.
- Equipping a listed (escrow) item is blocked — cancel the marketplace listing first.

---

## Dismantling

Dismantling permanently destroys an item and returns **rarity-specific Essence** to the team's wallet.

### Dismantle Requirements
- Item must have `status = available`.
- Item must not be equipped (`equipped_hero_id` must be `null`).

### Dismantle Essence Yields

| Rarity | Essence Gained (same tier) |
|--------|---------------------------|
| Common | 5 |
| Uncommon | 5 |
| Rare | 4 |
| Epic | 3 |
| Legendary | 2 |
| Mythic | 1 |

Higher-rarity items yield fewer units because each unit is proportionally more valuable.

---

## Durability & Repair

### Durability
- Range: `0` to `100` (stored as integer).
- Decreases after combat (exact per-battle reduction defined by the combat engine — Phase 5).
- An item with `durability = 0` may be penalised in combat (combat formulas TBD).

### Repair
Repair restores durability to `100`. The Gold cost scales with **rarity** and the **number of missing durability points**.

**Formula:**
```
Gold Cost = (100 - current_durability) × cost_per_point[rarity]
```

### Repair Cost Per Missing Durability Point

| Rarity | Gold per Missing Point |
|--------|------------------------|
| Common | 2 |
| Uncommon | 5 |
| Rare | 10 |
| Epic | 20 |
| Legendary | 40 |
| Mythic | 80 |

**Example:** Repairing an Epic item from `durability = 60` costs `(100 - 60) × 20 = 800` Gold.

### Repair Requirements
- Item must have `status = available`.
- The team must own the item (`owner_team_id` match).
- The team must have sufficient Gold.

---

## Merchant Purchase (`ItemService::buyFromMerchant`)

Teams can buy items directly from the in-game merchant via `POST /api/v1/items/buy`.

| Step | Details |
|------|---------|
| Gold deduction | `EconomyService::deductGold` (`MarketplacePurchase`, actor `Active`) |
| Item creation | New `Item` with `owner_team_id = buyer`, `status = Available` |
| Chronicle | `TeamChronicleService::recordItemPurchased` with `seller = null` (shows as "merchant") |
| Transaction | `MarketplaceTransaction` with `seller_team_id = null`, `listing_id = null`, `fee_amount = 0` |

---

## Marketplace Interactions

- Listing an item for sale sets `status = selling` (escrow). The item cannot be equipped or dismantled while listed.
- Cancelling a listing returns the item to `status = available`.
- On a successful sale the item is transferred to the buyer's team; `owner_team_id` changes and `equipped_hero_id` is cleared.
- Chronicle events `item_purchased` (buyer) and `item_sold` (seller) are recorded on buy-now and auction settlement.

See [Marketplace System](marketplace-system.md) for listing, bidding, and transaction details.

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/items` | Inventory list (optionally filtered by hero) |
| PUT | `/api/v1/heroes/{heroId}/equipment` | Equip or unequip an item |
| POST | `/api/v1/items/dismantle` | Dismantle item for Essence |
| POST | `/api/v1/items/{id}/repair` | Repair durability (costs Gold) |
| POST | `/api/v1/items/buy` | Purchase item from the merchant |

---

## Open Issues

- Item generation (loot drops, crafted results) — defined via `CraftingRecipe` entities; actual item generation on crafting completion is pending (Phase 7).
- Durability degradation per battle — formula pending combat engine implementation (Phase 5).
- Enchanting mechanics referenced in Essence spending (2.3) — not yet designed. Tracked in [known-issues.md](../known-issues.md#2).
