# Spell System

Reference: [product/overview.md](../product/overview.md#29-spell-system), [screens/11-spell-management.md](../screens/11-spell-management.md)

Status: Live (catalog T1–T5 — [#16](../product/known-issues.md) Resolved / A7.11)  
Screens: [11-spell-management.md](../screens/11-spell-management.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none. Lock: [Locked: catalog T1–T5](#locked-catalog-t1t5).

Purpose: Document spell schools, mastery, learning, equip slots, and academy UI.

## Remaining work

- [x] Library, learn for gold, slot equip, seeded catalog T1–T5
- [x] No essence; `spell_slot_cost` reserved — do not write (A6.2)

---

## Overview

- Spells belong to schools (`fire`, `water`, `air`, `earth`, `light`, `dark`) and have tiers.
- Learning requires school mastery tier ≥ spell `required_mastery_tier`.
- Cost is **gold only** (`learning_cost_gold`). There is no essence currency.
- Heroes equip spells into slots limited by magic capacity (expanded via training, max 5).
- Seeded catalog: `SpellService::seedDefaultSpells()` / `app:spells:seed` (also on kingdom init). See [Locked: catalog T1–T5](#locked-catalog-t1t5).

**Service:** `App\Service\Spell\SpellService`  
**Entity:** `App\Entity\Spell\Spell`, `HeroSpell`, `SchoolMastery`  
**Web:** `/app/academy` (`Web\SpellController`)  
**Combat:** L0 spell pick when `spell_priorities` is empty — see [combat-system.md](combat-system.md)

---

## Locked: catalog T1–T5

**#16 Resolved (A7.11 seed).** The live library is **two spells per school per tier** for tiers **1–5** (60 spells). `required_mastery_tier` equals `tier`. Do not add T6+, resurrection, summon-creature, or new combat effect keys.

| Rule | Meaning |
|------|---------|
| Shape | 6 schools × 5 tiers × 2 spells (**60**). Fire is offensive; Light is defensive (heal / bless / cleanse; **no** revive); Dark is offensive / curse / drain / poison (**no** heal); Water heal + freeze; Air haste / shock / evasion; Earth shield / petrify / damage. |
| Mastery | Learn needs school mastery ≥ spell tier. Combat mastery XP still reaches T5. |
| Combat | Engine uses **school + type + `spell.tier`**, not a new formula. Damage `spellPower × (1 + tier × 0.15)`; heal `spellPower × (1 + tier × 0.10)`. Cast duration stays `1 + tier` (T5 = 6 rounds). `effects` JSON is catalog flavor (same keys as T1–T2). Offensive spells set `requires_magical_weapon`. |
| Gold | T1 ~100–150, T2 ~300–450, T3 ~750–1100, T4 ~1900–2500, T5 ~4800–6200 (~2.5× per tier). |
| UI | Academy tier filter **1–5**. Hero-detail store lists the same catalog (school then tier). Existing DBs: `app:spells:seed`. |

---

## APIs

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/spells` | Spell library |
| GET | `/api/v1/heroes/{id}/spells` | Hero spellbook |
| POST | `/api/v1/heroes/{id}/spells/learn` | Body: `{ "spell_id": N }` — deducts gold |
| POST | `/api/v1/heroes/{id}/spells/equip` | Assign to slot |
| POST | `/api/v1/heroes/{id}/spells/unequip` | Clear slot |

Ledger type for learn spend: `spell_learning_cost`. `spell_slot_cost` is **reserved / unused (A6.2)** (capacity via training; do not write).

`SpellService::seedDefaultSpells()` sets `requires_magical_weapon` to true for every `SpellType::Offensive` spell (false for defensive/utility). The Library HQ facility does **not** reduce learning cost or school-mastery XP; it only multiplies post-match hero XP. Seeded catalog is Tier 1–5.

---

## Chronicle

Learning writes hero chronicle `spell_learned` and team chronicle `spell_learned`.
