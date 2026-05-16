# Marketplace Screen

Reference: [screens-overview.md](../screens-overview.md#15-marketplace-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes.

Displayed Information:
- Marketplace Tabs: Heroes, Items, Trainers
- Listings:
	- Thumbnail, name, level/age, key stats, rarity
	- Price (Buy Now), seller name, time remaining, bids (for auctions)
- Filtering & Sorting:
	- Race, level, age phase, price range, rarity, seller rating
- Search Bar: text search by name or attributes

Possible Actions/Buttons:
- Buy Now
- Place Bid (auction)
- View Details / Inspect Hero
- Add to Watchlist
- List Item/Hero for Sale
- Manage My Listings
- View Purchase History

Backend Requirements:
- Listings endpoint with filters and pagination
- Purchase endpoint (validation, currency deduction, escrow transfer)
- Listing creation/cancellation endpoints
- Transaction fee calculation and fee distribution
- Auction processing and bid validation
- Watchlist and notification hooks

Sections to fill:
- Listings endpoints and filters
- Purchase/listing flows
- Auction mechanics and escrow
- Implementation notes
