# Combat Formulas

Reference: [combat-system.md](combat-system.md) (live contract), `CombatStatCalculator`, `DerivedCombatStats`

Status: Live  
Screens: [12-combat-battle.md](../screens/12-combat-battle.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none. In-combat morale change / race synergy Cut ([combat-system.md](combat-system.md#locked-no-race-synergy--no-simulate)).

Purpose: Derived combat stats, durability scaling, mastery bonuses, and trait modifiers. Match rules, waves, and `combat_log` stay in [combat-system.md](combat-system.md).

Do not edit formulas here without matching `CombatStatCalculator` / `CombatEngine`. Do not invent synergy tables.

---

## Item Influence on Combat Stats

Equipped items affect combat calculations in two stages, scaled by **durability**:

### Durability scaling

`Durability_Factor = Durability / 100`

- **100%:** full stat bonuses
- **50%:** stats halved
- **0% (broken):** zero bonuses

### Indirect modifiers (primary attributes)

Added to base attributes **before** derived stats:

```
Effective_STR = Hero_Base_STR + Sum(Item_STR_Bonus × Durability_Factor)
Effective_KON = (Hero_Base_KON + Sum(Item_KON_Bonus × Durability_Factor)) × (100 − Fatigue) / 100
```

Fatigue is clamped to 0–100. It scales KON before HP and armor. Only the **Equipped** profile applies it. Fatigue gained by walking during the match stays on the run state and does not recalculate KON until the next snapshot.

### Direct modifiers (derived stats)

Added **after** primary contribution:

```
Total_Armor_Value = (Effective_KON × 1.5) + Sum(Item_Armor_Bonus × Durability_Factor)
Total_Spell_Power = (Effective_INT × 3) + Sum(Item_Spell_Accelerator_Bonus × Durability_Factor)
```

---

## Derived Combat Stats & Formulas

Combat stats derive from primary attributes (1–20), equipped items, race, form, fatigue, and morale. No character classes.

### Health Points (HP)

- **Max HP:** `Max_HP = (Level × 30) + (Effective_KON × 12)`
- **Form / Fatigue:** starting HP is `Max_HP × Form / 100`. Fatigue scales effective KON by `(100 − Fatigue) / 100` before HP and armor. `HumanNeutral` and `FullIntrinsic` ignore fatigue (and form stays 100)
- **Race:** Ents +20% to Constitution-based calculations

### Physical Attack (ATK)

- **Unarmed:** `Base_ATK = Effective_STR × 2`
- **Melee weapon:** `Physical_ATK = Weapon_Damage × (1 + Effective_STR / 15)`
- **Ranged weapon:** `Physical_ATK = Weapon_Damage × (1 + Effective_DEX / 15)`
- **Weapon_Damage:** sum of durability-scaled `damage` bonuses on **all** equipped items (dual-wield stacks). Occupancy prevents a two-hander plus an off-hand item ([item-system.md](item-system.md#locked-item-occupancy)). Reach / `weaponSubType` is the **main_hand** weapon, else off-hand
- **Race:** Orcs deal ×1.20 melee damage against a non-Orc target when the hit resolves (bows, crossbows, staves, and wands do not). There is no penalty for hitting an Orc. Giants +10% main-hand damage; Ents cannot equip weapons
- **Morale (Equipped profile):** multiplier on outgoing physical and offensive-spell damage, stored as `outgoingDamageMultiplier`. Heals are unchanged

| Morale | Damage |
|--------|--------|
| 80–100 | ×1.20 |
| 60–79 | ×1.10 |
| 40–59 | ×1.00 |
| 20–39 | ×0.90 |
| 0–19 | ×0.80 |

### Spell Power (SP)

```
Spell_Power = Effective_INT × 3 + Equipped_Spell_Accelerator_Bonus
Final_Spell_Effect = Spell_Base_Effect × (1 + Spell_Power / 100)
```

- **Race:** Genies +15% spell effectiveness

### Physical Defense / Armor (DEF)

```
Armor_Value = Equipped_Armor_Defense_Sum + (Effective_KON × 1.5)
Damage_Reduction_Percent = Armor_Value / (Armor_Value + 100)
```

- **Race:** Dwarves +15% armor effectiveness on the whole armor value. Ents apply +20% to the KON term of both HP and armor (`KON × 1.5 × 1.2`) and cannot equip armor

### Magic Resistance (RES)

```
Magic_Resistance = Equipped_Accessory_Resistance_Sum + (Effective_WIL × 2)
Magic_Reduction_Percent = Magic_Resistance / (Magic_Resistance + 100)
```

### Speed / Initiative (INIT)

Implemented in `CombatStatCalculator` and the per-round sort in `CombatEngine`:

```
baseInitiative = round(Effective_SPD × 2)
```

Ents apply ×0.80 to that product before rounding. That product is stored as `derived.baseInitiative` and is the AP input.

Each round, after status ticks (expired effects are gone), the turn sort uses:

```
initiative = round(baseInitiative × (1 + speed_bonus_percent/100 − speed_reduction_percent/100)) + random_int(-3, 3)
```

Percents come from remaining statuses via `status_effects.yaml` (`StatusEffectConfig`). Haste is +30, Shock is −30. Both at once cancel (additive). The modifier is clamped at 0. Equal initiative breaks by ascending `heroId`.

**AP does not use this modifier** — movement AP stays `4 + floor(baseInitiative / 5)`. A Haste applied during a round affects the **next** round's sort (ticks run before the sort). Duration 1 remaining at round start expires in the tick and does not affect that round.

Lock: do not also feed this modifier into AP, stored `baseInitiative`, or a replay HUD queue ([12-combat-battle.md](../screens/12-combat-battle.md#locked-replay-hud)). Grid AP cost and reach: [combat-system.md](combat-system.md#action-points-ap--movement).

### Accuracy (ACC)

```
Accuracy_Percent = 80 + Effective_DEX × 1 + Effective_LCK × 0.5
```

- **Race:** Elves +10% accuracy
- **Status:** Blind −40%

### Dodge (EVA)

```
Dodge_Chance_Percent = (Effective_DEX + Effective_SPD) × 0.75 + Effective_LCK × 0.25  (cap 50%)
```

- **Race:** Elves +10% dodge
- **Status:** Shadow Cloak +40% dodge

### Critical Hit Chance (CRT)

```
Crit_Chance_Percent = 5 + Effective_LCK × 1 + Effective_DEX × 0.25  (cap 50%)
```

- **Race:** Genies add **+15% spell crit chance** when an **offensive spell** resolves (`CombatEngine::executeSpell`). The shared `critPercent` on `DerivedCombatStats` is physical-only and does **not** include the Genie bonus.
- Default crit damage multiplier: **1.5×** (Berserker trait overrides to 2.0×). Offensive spells use the same multiplier when the spell crit roll succeeds.

---

## Technical Implementation (Derived Stats)

| Component | Path |
|-----------|------|
| Value object | `src/ValueObject/Combat/DerivedCombatStats.php` |
| Calculator | `src/Service/Combat/CombatStatCalculator.php` |
| Profiles | `CombatStatProfile::Equipped`, `HumanNeutral`, `FullIntrinsic` |
| Tests | `tests/Service/Combat/CombatStatCalculatorTest.php` |

`calculate(Hero)` uses the **Equipped** profile (items, trait, form). Rating system uses **HumanNeutral** and **FullIntrinsic** — see [hero-rating-system.md](hero-rating-system.md).

---

## Mastery Combat Bonuses

When equipped gear sub-types reach **100% attunement**, passive bonuses apply per mastery tier above 1:

### Weapon mastery (examples)

| Style | Bonus per tier above 1 |
|-------|----------------------|
| One-handed sword/axe/mace | Physical ATK +5% |
| Two-handed sword/axe/mace | Physical ATK +6% (+ crit or armor for some styles) |
| Dagger | Physical ATK +4%, Crit +2% |
| Bow / crossbow | Physical ATK +5–6%, Accuracy +1–2% |
| Wand / staff / spell accelerator | Spell Power +4–5%, Initiative +1–2% |
| Shield | Armor +5%, Dodge/Block +2% |

### Magic school mastery

Each equipped spell's school: **Spell Power +5% per tier above 1** (stacks across schools).

---

## Trait Modifiers in Combat

### Static (in `DerivedCombatStats`)

| Trait | Effect |
|-------|--------|
| Fragile | Max HP × 0.90 |
| Glasscannon | Spell Power × 1.15, Armor × 0.90 |
| Overconfident | Physical ATK × 1.10, Accuracy −8% |
| Berserker | Crit +15%, Accuracy −8%, crit damage 2.0× |
| Reckless | Crit +15%, Dodge −10% |

### Conditional (engine metadata on `DerivedCombatStats`)

| Trait | Trigger |
|-------|---------|
| Clutch | HP ≤ 30% → Accuracy +15%, Armor × 1.10 |
| Glass Jaw | HP ≤ 50% → incoming physical × 1.10 |
| Perfectionist | Consistent damage (no RNG variance) |
| Loner | **No in-engine effect** (#9 Cut). Chemistry is flavor for matches ([#7](../product/known-issues.md) Resolved). `ignoresRaceSynergy` is serialized but unused. Player copy matches. |
| Volatile / Battle Hardened | **No in-combat effect** (#9 Cut). `moraleDecayMultiplier` is serialized but unused. Player copy matches. Kickoff damage band and post-match team morale are unchanged by these traits. No `morale_change` event |
| Audience Favorite | +5% arena revenue when fielded (handled in `ArenaRevenueService`, not combat engine) |
