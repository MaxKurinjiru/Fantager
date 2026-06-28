# Marketplace Screen

Reference: [screens-overview.md](../screens-overview.md#15-marketplace-screen), [economy-system.md](../systems/economy-system.md)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

> **Implementation:** Marketplace is part of the **Economy hub** at `GET /app/economy`. Legacy `/app/marketplace` redirects to `?tab=browse`. Financial ledger is on `?tab=ledger` (legacy `/app/finance` redirect).

## Routes

| Route | Tab | Purpose |
|-------|-----|---------|
| `/app/economy?tab=browse` | browse | Search and buy listings |
| `/app/economy?tab=sell` | sell | Create new listings |
| `/app/economy?tab=mylistings` | mylistings | Manage own active listings |
| `/app/economy?tab=history` | history | Purchase/sale transaction history |
| `/app/economy?tab=ledger` | ledger | Full financial audit log |

Displayed Information:
- Marketplace Tabs: Heroes, Items, Trainers
- Listings:
	- Thumbnail, name, level/age, key stats, rarity
	- **Personality trait** (hero listings only, when present)
	- Price (Buy Now), seller name, time remaining, bids (for auctions)
- Filtering & Sorting:
	- Race, level, age phase, price range, rarity, seller rating
- Search Bar: text search by name or attributes

Possible Actions/Buttons:
- Buy Now
- Place Bid (auction)
- View Details / Inspect Hero
- List Item/Hero for Sale
- Manage My Listings
- View Purchase History

Backend Requirements:
- Listings endpoint with filters and pagination — `GET /api/v1/marketplace`
- Purchase endpoint — `POST /api/v1/marketplace/purchase`
- Listing creation/cancellation — `POST` / `DELETE /api/v1/marketplace/listings/{id}`
- Transaction fee calculation and Royal Treasury collection — `RoyalTreasuryService`
- Auction processing and bid validation — `POST /api/v1/marketplace/bid`

Implementation:
- **Controller:** `Web\EconomyController` (`app_economy`), `Api\V1\MarketplaceController`
- **Stimulus:** `marketplace_controller.js`, `ledger_controller.js`
- **Hero trait on browse cards:** `template-card-hero` + `marketplace_controller.js#renderHeroTrait`; labels via `data-marketplace-traits-value`
- **Hero trait on sell picker:** `trait_badge.html.twig` in `sell_tab.html.twig`
- **API:** Hero entity in `MarketplaceService::serializeListing()` includes nullable `trait`
