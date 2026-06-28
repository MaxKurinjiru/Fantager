# Combat Formulas & Derived Stats (Draft)

This document contains a draft proposal for the derived combat attributes of heroes in Fantager, mapping their primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) into combat-ready stats (HP, Attack, Defense, Spell Power, Resistance, Initiative, Accuracy, Dodge, Critical Chance).

## 1. Item Influence on Combat Stats (When & How)

Equipped items affect combat calculations in two distinct stages, with their contribution scaling based on current **durability**:

### A. Durability Scaling (Wear and Tear)
Items have a durability range of 0 to 100. As items lose durability, their performance deteriorates:
`Durability_Factor = Durability / 100`
- **100% Durability:** Full stat bonuses.
- **50% Durability:** Stats are halved.
- **0% Durability (Broken):** Item provides zero bonuses or effects.

### B. Indirect Modifiers (Primary Attribute Bonuses)
- **Applied to:** Primary stats (e.g., `bonuses: { str: 2, kon: 1 }` on a ring or accessory).
- **Timing:** Added to the hero's base primary attributes **first**, before derived combat stats are computed.
- **Formula:**
  `Effective_STR = Hero_Base_STR + Sum(Item_STR_Bonus * Durability_Factor)`
  `Effective_KON = Hero_Base_KON + Sum(Item_KON_Bonus * Durability_Factor)`
- *Example: If a hero has base KON 12, and equips an amulet with +3 KON at 80% durability, their Effective KON becomes 12 + (3 * 0.8) = 14.4 (rounded or floored for calculations).*

### C. Direct Modifiers (Derived Combat Stat Bonuses)
- **Applied to:** Direct combat stats (e.g., weapon damage, armor value, spell power, resistance).
- **Timing:** Added to the derived stats **after** the base primary attribute contribution has been evaluated.
- **Formula:**
  `Total_Armor_Value = (Effective_KON * 1.5) + Sum(Item_Armor_Bonus * Durability_Factor)`
  `Total_Spell_Power = (Effective_INT * 3) + Sum(Item_Spell_Accelerator_Bonus * Durability_Factor)`

---

## 2. Derived Combat Stats & Formulas

In Fantager, there are no character classes. Instead, combat stats are derived dynamically from a hero's primary attributes (which range from 1 to 20), equipped items, race, form, fatigue, and morale.

### A. Health Points (HP)
- **Max HP:** `Max_HP = (Level * 30) + (Effective_KON * 12)`
- **Adjustments:**
  - **Form / Fatigue:** HP in combat is scaled by `Form / 100`. Fatigue reduces effective Constitution: `Effective_KON = (Base_KON + Item_KON_Bonus) * (1 - Fatigue_Percentage)`.
  - **Race:** Ents receive a +20% bonus to Constitution-based calculations.

### B. Physical Attack (ATK)
- **Base rating for physical damage:**
  - **Unarmed:** `Base_ATK = Effective_STR * 2`
  - **With weapon (Melee):** `Physical_ATK = Weapon_Damage * (1 + Effective_STR / 15)`
  - **With weapon (Ranged):** `Physical_ATK = Weapon_Damage * (1 + Effective_DEX / 15)`
- **Adjustments:**
  - **Race:** Orcs receive +20% melee damage against non-Orcs. Giants receive +10% damage with main-hand weapons (but cannot use off-hand). Ents cannot equip weapons.
  - **Morale:** High morale (+10-20% damage), Low morale (-10-20% damage).

### C. Spell Power (SP)
- **Scales spell effects (damage/healing):**
  `Spell_Power = Effective_INT * 3 + Equipped_Spell_Accelerator_Bonus`
- **Spell Effect:**
  `Final_Spell_Effect = Spell_Base_Effect * (1 + Spell_Power / 100)`
- **Adjustments:**
  - **Race:** Genies receive +15% spell effectiveness.

### D. Physical Defense / Armor (DEF)
- **Reduces incoming physical damage:**
  `Armor_Value = Equipped_Armor_Defense_Sum + (Effective_KON * 1.5)`
- **Damage Mitigation (Diminishing Returns):**
  `Damage_Reduction_Percent = Armor_Value / (Armor_Value + 100)`
  - *Example: 50 Armor = 33.3% damage reduction. 100 Armor = 50% reduction. 200 Armor = 66.7% reduction.*
- **Adjustments:**
  - **Race:** Dwarves receive +15% armor effectiveness. Ents cannot equip armor but receive +20% defensive calculations.

### E. Magic Resistance (RES)
- **Reduces incoming magical damage:**
  `Magic_Resistance = Equipped_Accessory_Resistance_Sum + (Effective_WIL * 2)`
- **Damage Mitigation (Diminishing Returns):**
  `Magic_Reduction_Percent = Magic_Resistance / (Magic_Resistance + 100)`

### F. Speed / Initiative (INIT)
- **Determines turn resolution order in a combat round:**
  `Initiative = Effective_SPD + random_int(-3, 3)`
- **Adjustments:**
  - **Race:** Ents receive a -20% penalty.
  - **Status Effects:** Haste (+30% speed), Shock (-30% speed).

### G. Accuracy / Hit Chance (ACC)
- **Probability of landing an attack or spell:**
  `Accuracy_Percent = 80 + Effective_DEX * 1 + Effective_LCK * 0.5`
- **Adjustments:**
  - **Race:** Elves receive +10% accuracy.
  - **Status Effects:** Blind (-40% accuracy).

### H. Dodge / Evasion (EVA)
- **Probability of completely dodging a physical attack:**
  `Dodge_Chance_Percent = (Effective_DEX + Effective_SPD) * 0.75 + Effective_LCK * 0.25` *(Capped at 50%)*
- **Adjustments:**
  - **Race:** Elves receive +10% dodge chance.
  - **Status Effects:** Shadow Cloak (+40% dodge).

### I. Critical Hit Chance (CRT)
- **Probability of landing a critical hit (1.5x damage/effect):**
  `Crit_Chance_Percent = 5 + Effective_LCK * 1 + Effective_DEX * 0.25` *(Capped at 50%)*
- **Adjustments:**
  - **Race:** Genies receive +15% spell critical hit chance.

---

## 3. Proposed Technical Implementation

We recommend implementing these stats in a read-only Value Object `DerivedCombatStats` alongside a stateless calculator service `CombatStatCalculator`.

### A. Value Object: `DerivedCombatStats`
Path: `src/ValueObject/Combat/DerivedCombatStats.php`

Holds the dynamically computed combat stats of a hero for simulation, UI views, or API responses.

### B. Service: `CombatStatCalculator`
Path: `src/Service/Combat/CombatStatCalculator.php`

Queries equipped items for a hero (using `ItemRepository` via `equippedHero`), applies primary attributes, integrates race-specific passive bonuses (from `races.yaml`), evaluates item durability wear-and-tear factor, and returns the hydrated Value Object.

---

## 4. Mastery Combat Bonuses (Weapon & Magic School)

When a hero has equipped gear sub-types that are **fully attuned (100% attunement progress)**, they receive the following passive combat stat bonuses based on their mastery tier level (Tier ranges 1-5):

### A. Weapon Mastery Bonuses
For each tier above 1, the following multipliers/adders are applied to the calculated combat stats:

- **Jednoruční meč (`one_handed_sword`):**
  - Physical Attack: +5% per tier above 1.
- **Obouruční meč (`two_handed_sword`):**
  - Physical Attack: +6% per tier above 1.
- **Jednoruční sekera (`one_handed_axe`):**
  - Physical Attack: +5% per tier above 1.
  - Critical Chance: +0.5% per tier above 1.
- **Obouruční sekera (`two_handed_axe`):**
  - Physical Attack: +6% per tier above 1.
  - Critical Chance: +1.0% per tier above 1.
- **Jednoruční palcát (`one_handed_mace`):**
  - Physical Attack: +5% per tier above 1.
  - Armor Value: +1% per tier above 1.
- **Obouruční kladivo (`two_handed_mace`):**
  - Physical Attack: +6% per tier above 1.
  - Armor Value: +2% per tier above 1.
- **Dýka (`dagger`):**
  - Physical Attack: +4% per tier above 1.
  - Critical Chance: +2.0% per tier above 1.
- **Luk (`bow`):**
  - Physical Attack: +5% per tier above 1.
  - Accuracy: +2.0% per tier above 1.
- **Kuše (`crossbow`):**
  - Physical Attack: +6% per tier above 1.
  - Accuracy: +1.0% per tier above 1.
- **Hůlka (`wand`):**
  - Spell Power: +4% per tier above 1.
  - Initiative: +1% per tier above 1.
- **Hůl (`staff`):**
  - Spell Power: +5% per tier above 1.
  - Initiative: +2% per tier above 1.
- **Štít (`shield`):**
  - Armor Value: +5% per tier above 1.
  - Dodge/Block Chance: +2.0% per tier above 1.
- **Magický urychlovač (`spell_accelerator`):**
  - Spell Power: +5% per tier above 1.
  - Initiative: +1% per tier above 1.

### B. Magic School Mastery Bonuses
For each unique magic school of equipped spells, the hero receives:
- **School Mastery (Fire, Water, etc.):**
  - Spell Power: +5% per tier above 1.
  - *Example: A hero casting a Fire spell with Fire Mastery Tier 3 gets a +10% boost to Spell Power. If they dual-cast Fire (T3) and Water (T2) spells, they get (+10% + 5% = +15%) Spell Power.*

---

## 5. Trait Modifiers in Combat

Hero Traits introduce multipliers or flat adjustments to combat stats. Some are evaluated statically at computation time, while others serve as conditional metadata triggers for the combat engine.

### A. Static/Immediate Trait Modifiers
These are factored directly into `DerivedCombatStats` calculations:

- **Fragile:** Max HP * 0.90
- **Glasscannon:** Spell Power * 1.15, Armor Value * 0.90
- **Overconfident:** Physical Attack * 1.10, Accuracy -8.0%
- **Berserker:** Crit Chance +15.0%, Accuracy -8.0%, Crit Damage multiplier set to 2.0x (instead of 1.5x)
- **Reckless:** Crit Chance +15.0%, Dodge Chance -10.0%

### B. Conditional/Combat Engine Metadata
These values are carried in `DerivedCombatStats` to be processed dynamically during rounds:

- **Clutch:** Evaluated when current HP falls below 30% (less than or equal to 0.30). Triggers:
  - Accuracy +15.0%
  - Armor Value * 1.10
- **Glass Jaw:** Evaluated when current HP falls below 50% (less than or equal to 0.50). Triggers:
  - Incoming physical damage * 1.10 (takes 10% more damage)
- **Perfectionist:** `isConsistentDamage = true` signals the engine to collapse damage variance to the absolute mid-point.
- **Loner:** `ignoresRaceSynergy = true` signals the engine to ignore team race relationship modifiers.
- **Volatile / Battle Hardened:** Set `moraleDecayMultiplier` to 2.0 or 0.5 respectively, modifying morale penalty when an ally dies.


