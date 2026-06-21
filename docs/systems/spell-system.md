# Spell System

Reference: [game-summary.md](../game-summary.md#29-spell-system)

Purpose: Document spell schools, mastery, learning, and spell-slot mechanics.

Sections to fill:
- Spell data model and effects
- School mastery progression
- Learning and equip rules
- Casting constraints and cooldowns
- Integration with combat engine
- Implementation notes



Summary:
- Spells are grouped by schools (Fire, Water, Air, Earth, Light, Dark) and require mastery tiers to learn higher-tier spells.
- Heroes have limited spell slots (magic capacity) that can be expanded via training.

APIs:
- GET /api/spells — library of spells and requirements
- POST /api/heroes/{id}/learn-spell — learn a spell (cost: Gold + Essence)

