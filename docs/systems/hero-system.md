# Hero System

Reference: [game-summary.md](../game-summary.md#24-hero-system)

Purpose: Document hero lifecycle, attributes, aging, relationships, and persistence.

Sections to fill:
- Hero data model
- Attribute calculations and derived stats
- Age, mortality, and death mechanics
- Relationship matrix and effects
- Equipment and magic capacity integration
- API endpoints and DTOs
- Tests and edge cases
- Implementation notes

Summary:
- Heroes are defined by race, age, primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK), and secondary stats (form, fatigue, morale).
- No rarity or class: value derives from training and equipment.

Mechanics & APIs:
- Hero generation: Summoning Chamber produces junior heroes with randomized base stats within race ranges.
- GET /api/heroes — list for a player; GET /api/heroes/{id} — detail; POST/PUT for updates (rename, equipment changes)

Edge cases:
- Ensure death is final; move to Graveyard with immutable record
- Aging and mortality processing must be idempotent per tick


