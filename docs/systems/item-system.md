# Item System

Reference: [game-summary.md](../game-summary.md#28-item-system)

Purpose: Document items, equipment slots, crafting, dismantling, and durability.

Sections to fill:
- Item schema and rarity tiers
- Equipment compatibility rules
- Crafting recipes and essence costs
- Dismantle rules and essence returns
- Durability and repair mechanics
- Marketplace interactions
- Implementation notes



Summary:
- Items have slots, attribute bonuses, rarity, and optional durability. Dismantling yields Essence (rarity-specific).
- Crafting consumes Essence and materials; success rates may apply.

APIs:
- GET /api/items — inventory list
- POST /api/items/dismantle — dismantle item for essence
- POST /api/crafting — start crafting job

