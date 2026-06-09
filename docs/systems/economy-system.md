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

## Audit & Financial Ledger

To ensure absolute economic tracking and player balance auditability, the system records every currency modification (Gold, Crystals, and Essences) in the `FinancialRecord` entity.

### Financial Ledger Properties
- **Team**: The owner team whose wallet changed.
- **Type**: The action reason (e.g., `training_cost`, `league_reward`, `marketplace_sale`).
- **Actor**: The initiator of the change (`system`, `active`, or `passive`).
- **Currency Changes**: Negative or positive integer values reflecting the change amount.
- **Context**: A JSON object storing foreign references (e.g., `{"battle_id": 12}`, `{"listing_id": 34}`) for transaction traceability.


