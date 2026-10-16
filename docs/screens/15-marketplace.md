# Marketplace Screen

Reference: [architecture/screen-code-map.md](../architecture/screen-code-map.md), [marketplace-system.md](../systems/marketplace-system.md), [economy-system.md](../systems/economy-system.md)

**When:** Browse, buy, and list heroes, trainers, and items.

**Status:** Live at `GET /app/marketplace`. Ledger is **`/app/finance`** ([20-finance.md](20-finance.md)), not a marketplace tab. Listing price band is live. Auctions **are** live (bid API + UI). Hero sell also starts from hero-detail manage menu.  
**Lock:** [Locked: listing price band](#locked-listing-price-band)

**Live UI:** `templates/marketplace/index.html.twig`, `templates/components/marketplace/`.

---

## Implemented

### Tabs (`?tab=`)

| Tab | Purpose |
|-----|---------|
| `browse` | Search and buy (`?category=hero\|trainer\|item`) |
| `basic_equipment` | Fixed-price merchant (`POST /app/marketplace/buy-basic`) |
| `sell` | Create listings (heroes, items, trainers) |
| `mylistings` | Own active listings |
| `history` | Purchase/sale history |

Header: treasury badge.

### Browse

Segmented type control. Filters: search, race, level min/max, age phase, rarity (items), price, base OVR / complex rating, seller reputation, sort. Cards: thumbnail/name, level/age, key stats, rarity, **trait** on heroes, price, seller, time remaining, highest bid / no bids for auctions. Buy now / bid / buyout.

### Other tabs

- Basic equipment: `ItemService::BASIC_EQUIPMENT`, CSRF form
- Sell: entity picker (hero picker shows trait badge), buy-now vs auction, suggested price + allowed range (−50% / +100%), tax rate
- My listings: cancel (auctions with bids cannot cancel)
- History: settled transactions

### Backend

| Method | Path |
|--------|------|
| GET | `/api/v1/marketplace` |
| POST | `/api/v1/marketplace/listings` |
| DELETE | `/api/v1/marketplace/listings/{id}` |
| POST | `/api/v1/marketplace/purchase` |
| POST | `/api/v1/marketplace/bid` |
| GET | `/api/v1/marketplace/my-listings` |
| GET | `/api/v1/marketplace/history` |
| POST | `/app/marketplace/buy-basic` |

Hero payloads include nullable `trait` and `ratings`. Fees: `RoyalTreasuryService`.

---

## Locked: listing price band

**#15 Resolved (A7.10 Implement).** Sell tab and hero-detail sell panel show suggested gold and the allowed range. `POST /api/v1/marketplace/listings` rejects prices outside `[floor, ceiling]`. Auction bids are not capped. Do not add a second pricing formula. Spec: [marketplace-system.md](../systems/marketplace-system.md#locked-listing-price-band).

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/marketplace/index.html.twig` |
| Tabs | `templates/components/marketplace/` |
| Stimulus | `marketplace` (`template-card-hero`, `data-marketplace-traits-value`); hero detail `hero-sell` |
