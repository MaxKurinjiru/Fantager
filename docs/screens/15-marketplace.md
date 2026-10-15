# Marketplace Screen

Reference: [screens/overview-legacy.md](overview-legacy.md#15-marketplace-screen) (historical), [architecture/screen-code-map.md](../architecture/screen-code-map.md), [marketplace-system.md](../systems/marketplace-system.md), [economy-system.md](../systems/economy-system.md)

**When:** Browse, buy, and list heroes, trainers, and items.

**Status:** Live at `GET /app/marketplace`. Ledger is **`/app/finance`**, not a marketplace tab. Price floor/ceiling is **not** live (A7.10). Auctions **are** live (bid API + UI). Hero sell also starts from hero-detail manage menu.

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
- Sell: entity picker (hero picker shows trait badge), buy-now vs auction, tax rate
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

## Planned

- Suggested list price from complex rating as a hint only (A7.10)
- Price floor/ceiling (cut from live overview)

---

## Frontend

| Piece | Location |
|-------|----------|
| Page | `templates/marketplace/index.html.twig` |
| Tabs | `templates/components/marketplace/` |
| Stimulus | `marketplace` (`template-card-hero`, `data-marketplace-traits-value`) |
