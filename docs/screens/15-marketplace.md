# Marketplace Screen

Reference: [screens-overview.md](../screens-overview.md#15-marketplace-screen), [economy-system.md](../systems/economy-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

> **Implementation:** Marketplace is a **standalone screen** at `GET /app/marketplace`. The financial ledger lives on a separate screen at `GET /app/finance` (sidebar → Finance).

## Routes

| Route | Tab | Purpose |
|-------|-----|---------|
| `/app/marketplace?tab=browse` | browse | Search and buy listings |
| `/app/marketplace?tab=basic_equipment` | basic_equipment | Fixed-price basic gear merchant (`POST /app/marketplace/buy-basic`) |
| `/app/marketplace?tab=sell` | sell | Create new listings |
| `/app/marketplace?tab=mylistings` | mylistings | Manage own active listings |
| `/app/marketplace?tab=history` | history | Purchase/sale transaction history |

**Related screen:** full gold/essence audit log → `/app/finance` (not a marketplace tab).

Displayed Information:
- Marketplace Tabs: Heroes, Items, Trainers
- **Basic equipment tab:** fixed-price starter gear catalog from `ItemService::BASIC_EQUIPMENT`
- Listings:
	- Thumbnail, name, level/age, key stats, rarity
	- **Personality trait** (hero listings only, when present)
	- Price (Buy Now), seller name, time remaining, bids (for auctions)
- Filtering & Sorting:
	- Race, level, age phase, price range, base OVR, complex rating, rarity, seller reputation
- Search Bar: text search by name or attributes

Possible Actions/Buttons:
- Buy Now
- Place Bid (auction)
- View Details / Inspect Hero
- List Item/Hero for Sale
- Manage My Listings
- View Purchase History
- **Buy basic equipment** (basic_equipment tab, CSRF-protected form)

Backend Requirements:
- Listings endpoint with filters and pagination — `GET /api/v1/marketplace`
- Purchase endpoint — `POST /api/v1/marketplace/purchase`
- Listing creation/cancellation — `POST` / `DELETE /api/v1/marketplace/listings/{id}`
- Transaction fee calculation and Royal Treasury collection — `RoyalTreasuryService`
- Auction processing and bid validation — `POST /api/v1/marketplace/bid`
- Basic gear purchase — `POST /app/marketplace/buy-basic` → `ItemService::purchaseBasicItem()`

Implementation:
- **Controller:** `Web\MarketplaceController` (`app_marketplace`), `Api\V1\MarketplaceController`
- **Stimulus:** `marketplace_controller.js`
- **Hero trait on browse cards:** `template-card-hero` + `marketplace_controller.js#renderHeroTrait`; labels via `data-marketplace-traits-value`
- **Hero trait on sell picker:** `trait_badge.html.twig` in `sell_tab.html.twig`
- **API:** Hero entity in `MarketplaceService::serializeListing()` includes nullable `trait`, `ratings.base_ovr`, `ratings.complex_rating`
