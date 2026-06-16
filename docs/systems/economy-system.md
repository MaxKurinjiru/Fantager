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
- Gold: primary, earned from matches, marketplace sales; spent on training, upgrades, marketplace purchases.
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

To ensure absolute economic tracking and player balance auditability, the system records every currency modification (Gold and Essences) in the `FinancialRecord` entity.

### Financial Ledger Properties
- **Team**: The owner team whose wallet changed.
- **Type**: The action reason (e.g., `training_cost`, `league_reward`, `marketplace_sale`).
- **Actor**: The initiator of the change (`system`, `active`, or `passive`).
- **Currency Changes**: Negative or positive integer values reflecting the change amount.
- **Context**: A JSON object storing foreign references (e.g., `{"battle_id": 12}`, `{"listing_id": 34}`) for transaction traceability.

## Financial Crisis

When weekly HQ maintenance cannot be fully paid, the unpaid portion is recorded as **`unpaid_debt`** on the team. Gold never goes negative.

See [financial-crisis-system.md](financial-crisis-system.md) for the full escalation model (warning → restricted → bankruptcy), recovery actions, and API endpoints.

### Key invariants
- Partial maintenance payment is allowed; remainder becomes debt
- Debt is repaid automatically from gold during the weekly crisis tick
- Restricted teams cannot upgrade HQ, summon, or buy on marketplace
- Bankruptcy releases the team back to the NPC pool after prolonged insolvency

### New ledger types
- `debt_repayment` — gold applied to outstanding debt
- `hero_dismissal_compensation` — hero dismissed for partial value
- `hq_downgrade_refund` — facility downgrade completed


