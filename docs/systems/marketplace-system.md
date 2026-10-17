# Marketplace System

Reference: [product/overview.md](../product/overview.md#marketplace), [MarketplaceService.php](../../src/Service/Marketplace/MarketplaceService.php), [MarketplaceTransaction.php](../../src/Entity/Marketplace/MarketplaceTransaction.php)

Status: Live  
Screens: [15-marketplace.md](../screens/15-marketplace.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none  
Lock: [Locked: listing price band](#locked-listing-price-band)

Purpose: Document listing lifecycle, auctions, fees, limit checks, transaction records, and chronicle integration.

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

## Locked: listing price band

**#15 Resolved (A7.10 Implement).** `createListing` enforces a band around the suggested price. Do not add a second formula, a bid cap, or a listing with no suggested price.

| Rule | Meaning |
|------|---------|
| Suggested | Heroes/trainers: `HeroRatingCalculator::estimateMarketPrice()` (`complex_rating × gold_per_complex_point`, trainers × `trainer_market_multiplier`). Items: `ItemService::estimateMarketPrice()` (catalog commons = 70% of merchant cost; otherwise rarity table). Suggested is at least **1** gold. |
| Floor / ceiling | `floor = max(1, round(suggested × 0.5))` (−50%). `ceiling = max(floor, round(suggested × 2.0))` (+100%). |
| Enforce | Buy-now price, auction starting price, and optional auction buyout must each lie in `[floor, ceiling]` (`error.marketplace_price_out_of_range`). Still require gold ≥ 1. |
| Hint | Sell tab and hero-detail sell panel show suggested + allowed range and a “use suggested” button. HTML `min`/`max` match the band. |
| Not a bid cap | Auction **bids** may exceed the ceiling. NPC listings use suggested, so they stay in band. |
| Shared | Same suggested path for player UI and NPC sell (`NpcEconomySimulator`). |

---

## Buy-Now Flow (`buyListing`)

1. **Ownership check:** buyer cannot be the seller.
2. **Roster / trainer limit check:**
   - If listing type is `Hero`: `countActiveCombatantsByTeam(buyer) >= rosterLimit` → throws `error.marketplace_buy_roster_full`.
   - If listing type is `Trainer`: `countActiveTrainersByTeam(buyer) >= trainerLimit` → throws `error.marketplace_buy_trainer_limit_reached`.
3. **Price check:** listing must have a `buyout_price_gold` set.
4. **Gold check:** buyer must have sufficient gold for the **buyout price only** (the marketplace tax is not charged to the buyer).
5. **Atomic settlement** (`MarketplaceService::buyListing`):
   - Gold deducted from buyer via `EconomyService::deductGold` (`MarketplacePurchase`) for `buyout_price` only.
   - Seller credited the full buyout via `EconomyService::addGold` (`MarketplaceSale`).
   - Tax = `floor(buyout × MarketplaceService::TAX_RATE_PERCENT / 100)` is then deducted from the **seller** (`MarketplaceFee`) and paid to the kingdom treasury. The rate is **10** in every kingdom.
   - Ownership transferred (`hero.team` or `item.owner_team_id` updated).
   - `MarketplaceTransaction` persisted with `entity_name` snapshot (`amount` = buyout, `fee_amount` = tax).
   - For items: `TeamChronicleService::recordItemPurchased` (buyer) and `recordItemSold` (seller).

---

## Auction / Bid Flow

1. Same **roster / trainer limit checks** as buy-now (at bid time, not at settlement).
2. Bidder must have sufficient gold (amount is reserved/locked).
3. Settlement runs during the `daily_reset` tick when the listing has expired (`MarketplaceService::processExpiredListingsForKingdom`).
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

## Merchant Purchases (`ItemService::purchaseBasicItem`)

When a team buys a fixed-price basic item from the in-game merchant (not from another player) — UI: Marketplace **basic_equipment** tab / `POST /app/marketplace/buy-basic`:

- Gold is deducted via `EconomyService::deductGold` (`MarketplacePurchase`, actor `Active`).
- A `MarketplaceTransaction` is created with `seller_team_id = null`, `listing_id = null`, `fee_amount = 0`.
- `TeamChronicleService::recordItemPurchased` is called with `seller = null` (displays as "merchant").

---

## Fee / Tax Calculation

The tax rate is `MarketplaceService::TAX_RATE_PERCENT` (**10**), not a kingdom setting. The buyer pays the listed buyout; the fee is taken from the **seller’s** settlement (seller is credited the buyout, then taxed).

---

## APIs

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/marketplace` | Search active listings (filterable) |
| POST | `/api/v1/marketplace/listings` | Create listing (hero / trainer / item) |
| DELETE | `/api/v1/marketplace/listings/{id}` | Cancel own listing |
| GET | `/api/v1/marketplace/my-listings` | Current team's active listings |
| POST | `/api/v1/marketplace/purchase` | Buy-now purchase (`listing_id` in body) |
| POST | `/api/v1/marketplace/bid` | Place auction bid (`listing_id`, amount in body) |
| GET | `/api/v1/marketplace/history` | Transaction history for the active team |

---

## Related Systems

- [Team Chronicle System](team-chronicle-system.md) — `item_purchased` / `item_sold` events
- [Item System](item-system.md) — merchant purchase flow, item status lifecycle
- [Economy System](economy-system.md) — gold deduction / credit (`EconomyService`)
- [NPC Simulation System](npc-simulation-system.md) — NPC buying and selling. Player buys have no race filter. NPC hero buys use the shared summoning compatible pool ([summoning-system.md](summoning-system.md#locked-genie-summon-pool)).
- [Headquarters System](headquarters-system.md) — roster and trainer limits
