# Spell System

Reference: [game-summary.md](../game-summary.md#29-spell-system), [screens/11-spell-management.md](../screens/11-spell-management.md)

Purpose: Document spell schools, mastery, learning, equip slots, and academy UI.

---

## Overview

- Spells belong to schools (`fire`, `water`, `air`, `earth`, `light`, `dark`) and have tiers.
- Learning requires school mastery tier ≥ spell `required_mastery_tier`.
- Cost is **gold only** (`learning_cost_gold`). There is no essence currency.
- Heroes equip spells into slots limited by magic capacity (expanded via training, max 5).
- Seeded catalog: `SpellService::seedDefaultSpells()` / `app:spells:seed`.

**Service:** `App\Service\Spell\SpellService`  
**Entity:** `App\Entity\Spell\Spell`, `HeroSpell`, `SchoolMastery`  
**Web:** `/app/academy` (`Web\SpellController`)  
**Combat:** L0 spell pick when `spell_priorities` is empty — see [combat-system.md](combat-system.md)

---

## APIs

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/spells` | Spell library |
| GET | `/api/v1/heroes/{id}/spells` | Hero spellbook |
| POST | `/api/v1/heroes/{id}/spells/learn` | Body: `{ "spell_id": N }` — deducts gold |
| POST | `/api/v1/heroes/{id}/spells/equip` | Assign to slot |
| POST | `/api/v1/heroes/{id}/spells/unequip` | Clear slot |

Ledger type for learn spend: `spell_learning_cost`. `spell_slot_cost` is unused (capacity via training).

`SpellService::seedDefaultSpells()` sets `requires_magical_weapon` to true for every `SpellType::Offensive` spell (false for defensive/utility). The Library HQ facility does **not** reduce learning cost or school-mastery XP; it only multiplies post-match hero XP. Seeded catalog is Tier 1–2 only; mastery can still reach Tier 5 from combat XP.

---

## Chronicle

Learning writes hero chronicle `spell_learned` and team chronicle `spell_learned`.
