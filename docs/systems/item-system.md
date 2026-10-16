# Item System

Reference: [product/overview.md](../product/overview.md#items)

Status: Live (no enchanting — [#2](../product/known-issues.md) Resolved / A7.4 Cut)  
Screens: [10-item-equipment.md](../screens/10-item-equipment.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none for enchanting. Occupancy locked ([#8](../product/known-issues.md)).

Purpose: Document items, equipment slots, dismantling, durability/repair, and marketplace interactions.

## Item Entity

Items are represented by the `Item` entity (`src/Entity/Item/Item.php`). Key fields:

| Field | Type | Description |
|-------|------|-------------|
| `owner_team_id` | FK → Team | The team that currently owns the item |
| `equipped_hero_id` | FK → Hero (nullable) | Hero the item is equipped on; `null` if in rucksack |
| `equipped_slot` | `ItemSlotType` enum (nullable) | The specific slot where the item is equipped |
| `name` | string | Item display name |
| `slot_type` | `ItemSlotType` enum | Native type (`ring` items stay `ring` when worn in `ring_2`) |
| `category` | `ItemCategory` enum | Weapon, Shield, Spell Accelerator, Armor, Accessory, Material |
| `rarity` | `ItemRarity` enum | Common, Uncommon, Rare, Epic, Legendary, Mythic |
| `durability` | int (0–100) | Current durability; decreases after combat |
| `status` | `ItemStatus` enum | `available` or `selling` (in marketplace escrow) |
| `bonuses` | JSON | Map of attribute bonuses (e.g. `{"str": 3, "dex": 2}`) |
| `special_effects` | JSON | Unused / reserved (A7.4). Merchant writes `[]`. Combat does not read it. Omitted from item API `serialize()`. |

---

## Equipment Slots

Each hero has **9** equipment slots (`ItemSlotType`):

| Slot | Description |
|------|-------------|
| `main_hand` | Primary weapon |
| `off_hand` | Shield, spell accelerator, or a dual-wielded one-hander |
| `head` | Helmet or headpiece |
| `body` | Chest armour |
| `hands` | Gloves |
| `feet` | Boots |
| `amulet` | Neck accessory |
| `ring` | First ring |
| `ring_2` | Second ring |

An item's `slot_type` is its **native** type (`ring` items stay `ring` even when worn in `ring_2`). Equipping clears the target slot, plus the other hand when two-handed occupancy applies. See [Locked: item occupancy](#locked-item-occupancy).

---

## Equip / Unequip Rules

- An item must have `status = available` to be equipped.
- The item's `owner_team_id` must match the hero's team.
- The target `slot` must be in `ItemService::compatibleSlots()` (not always equal to `slot_type`).
- Race restrictions from `RaceConfig::getEquipmentRestrictions()` are enforced on **native** `slot_type`, **category**, and the **target** slot (Giants cannot dual-wield into `off_hand`).
- Equipping a listed (escrow) item is blocked — cancel the marketplace listing first.

---

## Locked: item occupancy

**#8 Resolved (A7.3 Implement).** Do not add a third ring, extra attacks for dual-wield, or two-handed occupancy for subtypes outside the list below.

| Rule | Meaning |
|------|---------|
| Nine slots, two rings | Paperdoll slots are the eight classic pieces plus `ring_2`. Ring items (`slot_type = ring`) may be equipped in `ring` or `ring_2`. |
| Two-handed clears `off_hand` | Subtypes `two_handed_sword`, `two_handed_axe`, `two_handed_mace`, `bow`, `crossbow`, and `staff` occupy both hands. Equipping one to `main_hand` unequips `off_hand`. Equipping anything to `off_hand` unequips that two-hander. |
| Dual-wield | `one_handed_sword`, `one_handed_axe`, `one_handed_mace`, and `dagger` may go in `main_hand` or `off_hand`. Two-handers, bows, staves, shields, and wands cannot sit in the other hand. |
| Combat stacking | `CombatStatCalculator` still sums bonuses from **all** equipped items (durability-scaled). Dual-wield therefore stacks `damage`. Weapon reach / `weaponSubType` comes from **main_hand**, else off-hand. No extra swing. Targeting is per-slot L1 on Formation Setup ([#12](../product/known-issues.md)). |
| Race | Giant/Ent restrictions stay `RaceConfig` (Giant: no `off_hand`; Ent: no weapon / shield / armor). |
| Pre-A7.3 rows | Two-hand + off-hand pairs that already exist are cleared by `app:item-occupancy:refresh` (no chronicle). |

---

## Locked: no enchanting

**#2 Resolved (A7.4 Cut).** Enchanting is not in the live game. Do not reintroduce essence. Do not ship an enchant UI, cost, or combat effect.

| Rule | Meaning |
|------|---------|
| Not live | No enchant service, route, or UI. Equipment actions stay equip / unequip / repair / dismantle. |
| No essence | Team / ledger / spell essence columns were dropped (`Version20261014140000`). Do not add them back. |
| `special_effects` unused | JSON column stays. Merchant and generators write `[]`. `CombatStatCalculator` reads `bonuses` only. Item API `serialize()` omits the key so it does not look live. Do not drop the column. |
| Later | A later product decision is required before adding enchanting. |

---

## Dismantling

Dismantling permanently destroys an item and returns **gold scrap** to the team's wallet (`FinancialRecordType::DismantleGain`).

### Dismantle Requirements
- Item must have `status = available`.
- Item must not be equipped (`equipped_hero_id` must be `null`).

### Dismantle Gold Yields

| Rarity | Gold Gained |
|--------|-------------|
| Common | 25 |
| Uncommon | 50 |
| Rare | 100 |
| Epic | 250 |
| Legendary | 500 |
| Mythic | 1000 |

---

## Durability & Repair

### Durability
- Range: `0` to `100` (stored as integer).
- Decreases after combat in `LeagueMatchResolutionService` for every equipped item on participating heroes.
- An item with `durability = 0` contributes no combat bonuses (`CombatStatCalculator` scales by durability / 100).

**Loss per battle (same value applied to each equipped item on the hero):**
```
loss = max(1, min(20, floor(rounds / 10) + floor(hits_received / 3)))
new_durability = max(0, current_durability - loss)
```
- `rounds` — battle round count at resolution (`Battle::getCurrentRound()`)
- `hits_received` — that hero’s total from combat result `item_hit_counts`

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

## Merchant Purchase (`ItemService::purchaseBasicItem`)

Teams can purchase fixed-price basic equipment from the merchant via the web frontend at `POST /app/marketplace/buy-basic` (request parameters: `item_key`, CSRF token `buy_basic`).

| Step | Details |
|------|---------|
| Gold deduction | `EconomyService::deductGold` (`MarketplacePurchase`, actor `Active`) |
| Item creation | New `Item` with `ownerTeam = buyer`, `status = Available` |
| Chronicle | `TeamChronicleService::recordItemPurchased` with `seller = null` (shows as "merchant") |
| Transaction | `MarketplaceTransaction` with `seller_team_id = null`, `listing_id = null`, `fee_amount = 0` |

---

## Marketplace Interactions

- Suggested list price is `ItemService::estimateMarketPrice()` (catalog commons: 70% of merchant cost; otherwise rarity table). Player listings must sit in the −50% / +100% band ([marketplace-system.md](marketplace-system.md#locked-listing-price-band)).
- Listing an item for sale sets `status = selling` (escrow). The item cannot be equipped or dismantled while listed.
- Cancelling a listing returns the item to `status = available`.
- On a successful sale the item is transferred to the buyer's team; `owner_team_id` changes and `equipped_hero_id` is cleared.
- Chronicle events `item_purchased` (buyer) and `item_sold` (seller) are recorded on buy-now and auction settlement.

See [Marketplace System](marketplace-system.md) for listing, bidding, and transaction details.

---

## API & Web Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/items` | Inventory list (optionally filtered by hero) |
| PUT | `/api/v1/heroes/{heroId}/equipment` | Equip or unequip an item |
| POST | `/api/v1/items/dismantle` | Dismantle item for gold scrap |
| POST | `/api/v1/items/{id}/repair` | Repair durability (costs Gold) |
| POST | `/app/marketplace/buy-basic` | Purchase basic item from the merchant (Web route, redirects back) |

---

## Open Issues

- Item generation (loot drops, crafted results) — defined via `CraftingRecipe` entities; actual item generation on crafting completion is deferred (Milestone 8 / future).
- Durability degradation per battle — ✅ formula above in `LeagueMatchResolutionService`.
- Occupancy (two-handed / dual-wield / two rings) — ✅ locked above (A7.3 / [#8](../product/known-issues.md)).
- Enchanting — ✅ cut (A7.4 / [#2](../product/known-issues.md)). Not in the live game. `special_effects` unused (omitted from item API).
