APIs and flows:
- POST /api/transactions — atomic transaction processing (ensure idempotency)
- Marketplace fees applied on listing completion
- Logging of transactions for audit and fraud detection


# Economy System

Reference: [game-summary.md](../game-summary.md#23-economy-system)

Purpose: Document currencies, sinks/sources, anti-exploit measures and economic invariants.

Sections to fill:
- Currency definitions and rules
- Transaction flow and atomicity requirements
- Marketplace mechanics and fee handling
- Inflation control mechanisms
- Audit/logging requirements
- Tests and simulation scenarios
- Implementation notes

Summary of currencies:
- Gold: primary, earned from matches, marketplace sales, quests; spent on training, upgrades, marketplace purchases.
- Crystals: premium/event currency; account-bound; used for QoL and cosmetic features (no direct competitive advantages).
- Essence: crafting/upgrade currency obtained from dismantling and events; used for crafting/enchantment.

Economic controls:
- Marketplace tax percentage per kingdom
- Diminishing returns on repeated activities
- Time-gated income and scaling costs

APIs and flows:
- POST /api/transactions — atomic transaction processing (ensure idempotency)
- Marketplace fees applied on listing completion
- Logging of transactions for audit and fraud detection

