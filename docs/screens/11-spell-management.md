# Spell Management Screen & Academy Store

Reference: [screens-overview.md](../screens-overview.md#11-spell-management-screen)

Purpose: Per-screen API, events, UI data requirements, and implementation notes for Spell Management and the Spells Academy.

## Hero Spell Management (Hero Detail Page)
Accessed via the Spells tab on the Hero Detail screen (`/app/heroes/{id}?tab=spells`).
- **Equipped Slots Panel:** View equipped spells in slots 1–5 (limited by hero's magic capacity). Supports equipping and unequipping.
- **School Mastery Panel:** View current mastery levels across the 6 magic schools (Fire, Water, Air, Earth, Light, Dark) and training progression.
- **Learned Spells Tab:** Browse all spells already learned by this specific hero.

## Academy Store Page
Accessed via `/app/academy` (under Marketplace in the sidebar).
- **Hero Selector:** Choose which hero of the team is the "Trainee" to view spell learning eligibility, mastery requirements, and learn new spells directly for them.
- **School Filter:** Filter available spells by magic school.
- **Tier Filter:** Filter available spells by Tier (1 and 2).
- **Available Spells Grid:** Shows the spell cards with description, cooldown, mana cost, and Gold learning cost.

## Backend & API Actions
- **Get Spells Library:** `GET /api/v1/spells` (returns list of all spells)
- **Learn Spell:** `POST /api/v1/heroes/{id}/spells/learn` (learns spell using team Gold)
- **Equip/Unequip:** `POST /api/v1/heroes/{id}/spells/equip` and `POST /api/v1/heroes/{id}/spells/unequip`

