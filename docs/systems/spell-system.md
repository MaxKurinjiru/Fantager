# Spell System

Reference: [product/overview.md](../product/overview.md#spells), [screens/11-spell-management.md](../screens/11-spell-management.md)

Status: Live (catalog T1–T5)  
Screens: [11-spell-management.md](../screens/11-spell-management.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none  
Lock: [Locked: catalog T1–T5](#locked-catalog-t1t5)

Purpose: Document spell schools, mastery, learning, equip slots, academy UI, match mana, and combat payloads.


## Overview

- Spells belong to schools (`fire`, `water`, `air`, `earth`, `light`, `dark`) and have tiers.
- Learning requires school mastery tier ≥ spell `required_mastery_tier`.
- Cost is **gold only** (`learning_cost_gold`). There is no essence currency.
- Heroes equip spells into slots limited by magic capacity (default **1** at generation; expanded via training, max 5). Trainers have capacity **0**.
- Seeded catalog: `SpellService::seedDefaultSpells()` / `app:spells:seed` (also on kingdom init). Display uses `name_key`; `name` is the fallback. See [Locked: catalog T1–T5](#locked-catalog-t1t5).
- Combat spends **mana** when a spell is queued. Formulas: [combat-formulas.md](combat-formulas.md#mana-pool).
- Combat identity is **per-row payloads** (`effects`), not school × type. Intent (`SpellType`) is for AI and UI.

**Service:** `App\Service\Spell\SpellService`  
**Entity:** `App\Entity\Spell\Spell`, `HeroSpell`, `SchoolMastery`  
**Web:** `/app/academy` (`Web\SpellController`)  
**Combat:** L0 spell pick when `spell_priorities` is empty — see [combat-system.md](combat-system.md)

---

## Locked: catalog T1–T5

**#16 envelope + #17 payloads.** The live library is **two spells per school per tier** for tiers **1–5** (60 spells). `required_mastery_tier` equals `tier`. Do not add T6+, resurrection, summon-creature, or new combat effect keys.

| Rule | Meaning |
|------|---------|
| Shape | 6 schools × 5 tiers × 2 spells (**60**). Two spells of the same school + tier **must differ in payload shape** (`intent` + `instant_hp` + `apply_effect`). School has a signature status, not a forced map. |
| Intent | `SpellType`: `harm` / `restore` / `enhance` / `hinder`. L0 and Healer / Nuker / CC templates read intent. |
| Payloads | `effects` is only `{ "instant_hp": "damage"\|"heal" }` and/or `{ "apply_effect": "<status>" }`. At least one key. Status applies at **100%**. Engine reads this JSON (`SpellPayload`), not school × type. Damage `spellPower × (1 + tier × 0.15)`; heal `spellPower × (1 + tier × 0.10)`. Cast duration stays `1 + tier` (T5 = 6 rounds). Do not add unused keys (lifesteal, cleanse, holy, poison, evasion, chance). `requires_magical_weapon` when `instant_hp` is `damage`. Mana spend is live ([Mana pool](#mana-pool)). |
| Gold | T1 ~100–150, T2 ~300–450, T3 ~750–1100, T4 ~1900–2500, T5 ~4800–6200 (~2.5× per tier). |
| UI | Academy tier filter **1–5**. Hero-detail store lists the same catalog (school then tier). Existing DBs: `app:spells:seed`. |

Light has **no** revive. Dark has **no** heal. Drain names (Vysátí života, Vysátí duše) are flavor only.

### Catalog matrix

`instant_hp` blank = status-only. `status` blank = plain hit or heal.

| School | Name | Tier | Intent | instant_hp | status |
|--------|------|------|--------|------------|--------|
| Fire | Jiskra | 1 | harm | damage | — |
| Fire | Vzplanutí | 1 | harm | damage | burn |
| Fire | Ohnivá koule | 2 | harm | damage | — |
| Fire | Spalování | 2 | harm | — | burn |
| Fire | Plamenomet | 3 | harm | damage | burn |
| Fire | Ohnivá bouře | 3 | harm | damage | — |
| Fire | Pekelný oheň | 4 | harm | damage | burn |
| Fire | Spalující spirála | 4 | enhance | — | fury |
| Fire | Ohnivý meteor | 5 | harm | damage | — |
| Fire | Fénixí hněv | 5 | harm | damage | burn |
| Water | Vodní proud | 1 | harm | damage | — |
| Water | Laskavý dotek | 1 | restore | heal | — |
| Water | Léčivá vlna | 2 | restore | heal | regeneration |
| Water | Ledový osten | 2 | hinder | — | freeze |
| Water | Příliv | 3 | restore | heal | — |
| Water | Ledová klec | 3 | hinder | — | freeze |
| Water | Živá voda | 4 | restore | — | regeneration |
| Water | Sněhová bouře | 4 | harm | damage | freeze |
| Water | Mořská milost | 5 | restore | heal | regeneration |
| Water | Věčný mráz | 5 | hinder | — | freeze |
| Air | Vánek | 1 | enhance | — | haste |
| Air | Statický náboj | 1 | harm | damage | shock |
| Air | Úder blesku | 2 | harm | damage | — |
| Air | Větrná zeď | 2 | enhance | — | haste |
| Air | Vír | 3 | hinder | — | stun |
| Air | Bouřkový šíp | 3 | harm | damage | shock |
| Air | Vzdušný štít | 4 | enhance | — | haste |
| Air | Hromová pěst | 4 | harm | damage | shock |
| Air | Oko cyklónu | 5 | enhance | — | haste |
| Air | Nebeský blesk | 5 | harm | damage | — |
| Earth | Kamenná kůže | 1 | enhance | — | shield |
| Earth | Hod kamenem | 1 | harm | damage | — |
| Earth | Zemětřesení | 2 | hinder | — | petrify |
| Earth | Štěrková smršť | 2 | harm | damage | petrify |
| Earth | Balvan | 3 | harm | damage | — |
| Earth | Kamenná pevnost | 3 | enhance | — | shield |
| Earth | Horská pěst | 4 | harm | damage | petrify |
| Earth | Rozštěpení země | 4 | hinder | — | petrify |
| Earth | Zřícení hor | 5 | harm | damage | — |
| Earth | Titanova kůže | 5 | enhance | — | shield |
| Light | Svaté světlo | 1 | restore | heal | — |
| Light | Požehnání | 1 | enhance | — | bless |
| Light | Očištění | 2 | restore | heal | — |
| Light | Paprsek naděje | 2 | restore | heal | bless |
| Light | Božská záře | 3 | hinder | — | blind |
| Light | Posvěcení | 3 | restore | heal | — |
| Light | Zázrak | 4 | restore | heal | bless |
| Light | Světlo naděje | 4 | enhance | — | bless |
| Light | Nebeská milost | 5 | restore | heal | — |
| Light | Aura světla | 5 | enhance | — | bless |
| Dark | Stínový šíp | 1 | harm | damage | — |
| Dark | Slabost | 1 | hinder | — | curse |
| Dark | Vysátí života | 2 | harm | damage | curse |
| Dark | Zkaženost | 2 | harm | damage | — |
| Dark | Temný paprsek | 3 | harm | damage | curse |
| Dark | Prokletí | 3 | hinder | — | curse |
| Dark | Krvavý obřad | 4 | harm | damage | curse |
| Dark | Mor | 4 | hinder | — | taunt |
| Dark | Vysátí duše | 5 | harm | damage | — |
| Dark | Věčná tma | 5 | enhance | — | shadow_cloak |

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

`SpellService::seedDefaultSpells()` writes `effects` via `SpellPayload::catalog()`, sets `requires_magical_weapon` when `instant_hp` is damage, and backfills `name_key`. Display names stay flavor; Academy cards use intent + payload copy plus `status_effects.*`. The Library HQ facility does **not** reduce learning cost or school-mastery XP; it only multiplies post-match hero XP. Seeded catalog is Tier 1–5. Existing DBs: `app:spells:seed`.

---

## Mana pool

Match-only (`derived.maxMana`, combatant `currentMana`). Not a `Hero` column.

Ready spells need `currentMana >= manaCost`, cooldown 0, and the wand/staff gate for damage payloads (Ents excepted). Deduct on `plan_action`; silence and fumble do not refund. Regen runs each turn after cooldown decrement, including skipped turns. Kickoff fills the pool. Replay accordion shows mana on `turn_start` / `plan_action` — no HUD bar ([12-combat-battle.md](../screens/12-combat-battle.md#locked-replay-hud)).

Academy and spellbook show live mana cost, the selected hero’s max mana, intent and payload copy, and a wand/staff warning when a damage spell cannot fire.

---

## L0 utility and L2 templates

Empty `spell_priorities`: strongest ready **restore** under the approach HP threshold, else highest-tier **harm**, else highest-tier **hinder**. Do not auto-cast **enhance** (L2 only). Formation tactics panel can write Healer (`restore`) / CC (`hinder`) / Nuker (`harm`) lists from equipped spells (opt-in). Sanitizer unchanged. No L3.

---

## Chronicle

Learning writes hero chronicle `spell_learned` and team chronicle `spell_learned`.
