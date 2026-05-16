# Marketplace System

Reference: [game-summary.md](../game-summary.md#211-marketplace-system)

Purpose: Document listing lifecycle, auctions, fees, and anti-exploit measures.

Sections to fill:
- Listing model and states
- Buy-now vs auction mechanics
- Fee calculation and settlement
- Anti-fraud and abuse detection
- Integration with economy accounting
- Implementation notes



Summary:
- Marketplace supports listings for heroes, items, and trainers; transactions are Gold-only. Crystals and Essence are non-tradeable.
- Apply kingdom-specific tax rates and maintain transaction logs for disputes/auditing.

APIs:
- GET /api/marketplace — search listings
- POST /api/marketplace/listings — create listing
- POST /api/marketplace/purchase — purchase flow (atomic)

