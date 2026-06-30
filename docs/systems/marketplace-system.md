# Marketplace System

Reference: [game-summary.md](../game-summary.md#211-marketplace-system), [MarketplaceService.php](../../src/Service/Marketplace/MarketplaceService.php), [MarketplaceTransaction.php](../../src/Entity/Marketplace/MarketplaceTransaction.php)

Purpose: Document listing lifecycle, auctions, fees, limit checks, transaction records, and chronicle integration.

---

## Listing Types

| `ListingType` | What is listed | Entity used |
|:---|:---|:---|
| `Hero` | A combatant hero | `listing.hero_id` |
| `Trainer` | A trainer hero | `listing.hero_id` |
| `Item` | An inventory item | `listing.item_id` |

- Listing a hero/trainer sets `hero.status = Selling` (escrow).
- Listing an item sets `item.status = Selling` (escrow).
- **Trainers listed for sale:** all items equipped on the trainer are automatically unequipped before the listing is created.
- Cancelling a listing returns the entity to its previous status (`Available`).

---

## Buy-Now Flow (`buyListing`)

1. **Ownership check:** buyer cannot be the seller.
2. **Roster / trainer limit check:**
   - If listing type is `Hero`: `countActiveCombatantsByTeam(buyer) >= rosterLimit` → throws `error.marketplace_buy_roster_full`.
   - If listing type is `Trainer`: `countActiveTrainersByTeam(buyer) >= trainerLimit` → throws `error.marketplace_buy_trainer_limit_reached`.
3. **Price check:** listing must have a `buyout_price_gold` set.
4. **Gold check:** buyer must have sufficient gold (including the kingdom tax).
5. **Atomic settlement:**
   - Gold deducted from buyer via `EconomyService::deductGold` (`MarketplacePurchase`).
   - Tax paid to kingdom treasury.
   - Gold credited to seller via `EconomyService::addGold` (`MarketplaceSale`).
   - Ownership transferred (`hero.team` or `item.owner_team_id` updated).
   - `MarketplaceTransaction` persisted with `entity_name` snapshot.
   - For items: `TeamChronicleService::recordItemPurchased` (buyer) and `recordItemSold` (seller).

---

## Auction / Bid Flow

1. Same **roster / trainer limit checks** as buy-now (at bid time, not at settlement).
2. Bidder must have sufficient gold (amount is reserved/locked).
3. Settlement runs during the `marketplace_resolution` tick when the listing expires.
4. For items: `recordItemPurchased` and `recordItemSold` are called on auction settlement.

---

## Transaction Record (`MarketplaceTransaction`)

Table: `marketplace_transaction` — entity `App\Entity\Marketplace\MarketplaceTransaction`.

| Field | Type | Notes |
|-------|------|-------|
| `buyer_team_id` | FK `Team` (not null) | |
| `seller_team_id` | FK `Team` (nullable) | `null` for merchant purchases |
| `listing_id` | FK `MarketplaceListing` (nullable) | `null` for merchant purchases |
| `entity_name` | `string` (nullable) | Snapshot of the hero/item name at time of sale |
| `amount` | `int` | Total gold paid by buyer |
| `fee_amount` | `int` | Tax/fee taken (0 for merchant) |
| `type` | `TransactionType` | `BuyNow` or `AuctionWin` |
| `created_at` | `datetime_immutable` | UTC |

- `entity_name` is stored as a snapshot so that the transaction history remains readable even after a hero/item is renamed or deleted.
- In `getTransactionHistory`, if `entity_name` is `null` (legacy rows before this field existed), the value is resolved live from the linked listing.
- `seller_name` in the API response falls back to the translated `marketplace.merchant` key when `seller_team_id` is `null`.

---

## Merchant Purchases (`ItemService::buyFromMerchant`)

When a team buys an item from the in-game merchant (not from another player):

- Gold is deducted via `EconomyService::deductGold` (`MarketplacePurchase`, actor `Active`).
- A `MarketplaceTransaction` is created with `seller_team_id = null`, `listing_id = null`, `fee_amount = 0`.
- `TeamChronicleService::recordItemPurchased` is called with `seller = null` (displays as "merchant").

---

## Fee / Tax Calculation

Tax rates are kingdom-specific and configured in `kingdom.marketplace_tax_rate`. The fee is deducted from the settlement amount before crediting the seller.

---

## APIs

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/marketplace` | Search active listings (filterable) |
| POST | `/api/v1/marketplace/listings` | Create listing (hero / trainer / item) |
| DELETE | `/api/v1/marketplace/listings/{id}` | Cancel own listing |
| POST | `/api/v1/marketplace/listings/{id}/buy` | Buy-now purchase |
| POST | `/api/v1/marketplace/listings/{id}/bid` | Place auction bid |
| GET | `/api/v1/marketplace/transactions` | Transaction history for the active team |

---

## Related Systems

- [Team Chronicle System](team-chronicle-system.md) — `item_purchased` / `item_sold` events
- [Item System](item-system.md) — merchant purchase flow, item status lifecycle
- [Economy System](economy-system.md) — gold deduction / credit (`EconomyService`)
- [NPC Simulation System](npc-simulation-system.md) — NPC buying and selling behaviour
- [Headquarters System](headquarters-system.md) — roster and trainer limits
