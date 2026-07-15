# Combat System

Reference: [game-summary.md](../game-summary.md#210-combat-system)

Purpose: Document combat simulation, match eligibility, scoring, derived combat stats, turn order, status effects, and result processing.

---

## Implementation Status

| Component | Status | Notes |
|-----------|--------|-------|
| Match eligibility & forfeit rules | ✅ Implemented | `LeagueMatchResolutionService::resolveForfeitOutcome()` |
| Kill-based scoring & standings | ✅ Implemented | `StubRandomMatchSimulator` until full engine ships |
| `Battle` entity persistence | ✅ Implemented | Scores, formations, result enum |
| Post-match side effects | ✅ Implemented | Standings, fan club, morale, hero/team chronicle, mastery XP |
| `CombatStatCalculator` + `DerivedCombatStats` | ✅ Implemented | Profile-aware (`Equipped`, `HumanNeutral`, `FullIntrinsic`) |
| Deterministic turn engine | ⏳ Pending | Milestone 6 Step 6.1 |
| `combat_log` JSON + replay UI | ⏳ Pending | [screens/12-combat-battle.md](../screens/12-combat-battle.md) |
| Combat death → graveyard | ⏳ Pending | Blocked on full engine |

Status effect reference config: [config/game/status_effects.yaml](../../config/game/status_effects.yaml)

---

## Match Eligibility

Before any match is simulated, each team is checked for roster readiness:

| Condition | Result |
|-----------|--------|
| Both teams have ≥ 6 **combat-ready** heroes | Proceed to simulation |
| Exactly one team has < 6 combat-ready heroes | **Automatic forfeit** — eligible team wins **3–0** (kill score); no simulation |
| Both teams have < 6 combat-ready heroes | **Automatic draw** — **0–0** kill score; no simulation |

**Combat-ready hero:** active status, not a Trainer, not permanently dead (Graveyard).

A team needs 6 combat-ready heroes to **enter** a match, independent of formation lineup configuration. Formation still requires all 6 slots filled when simulating.

---

## Match Scoring

- Each **kill** (enemy hero removed from the opposing lineup during the match) = **1 point** for the scoring team
- Maximum score per team = **6** (one per enemy lineup slot)
- Forfeit win = **3–0** (half of maximum 6)
- Double forfeit = **0–0**

**League table points** (Win 3 / Draw 1 / Loss 0) are derived from the match winner/loser/draw, not from kill totals directly.

---

## Combat Flow

1. Formation selection (6 heroes per team, 3 front / 3 back)
2. Roster eligibility check (see above)
3. Queue match for simulation (Redis) — skipped on forfeit/draw
4. PHP worker runs deterministic turn-based simulation *(pending — stub random scores today)*
5. Apply post-match updates (XP, form, fatigue, morale, aging)
6. Store result in `Battle` entity

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
Effective_KON = Hero_Base_KON + Sum(Item_KON_Bonus × Durability_Factor)
```

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
- **Form / Fatigue:** HP scaled by `Form / 100`; fatigue reduces effective KON
- **Race:** Ents +20% to Constitution-based calculations

### Physical Attack (ATK)

- **Unarmed:** `Base_ATK = Effective_STR × 2`
- **Melee weapon:** `Physical_ATK = Weapon_Damage × (1 + Effective_STR / 15)`
- **Ranged weapon:** `Physical_ATK = Weapon_Damage × (1 + Effective_DEX / 15)`
- **Race:** Orcs +20% melee vs non-Orcs; Giants +10% main-hand damage; Ents cannot equip weapons
- **Morale:** high +10–20% damage; low −10–20%

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

- **Race:** Dwarves +15% armor effectiveness; Ents +20% defensive calculations (no armor slots)

### Magic Resistance (RES)

```
Magic_Resistance = Equipped_Accessory_Resistance_Sum + (Effective_WIL × 2)
Magic_Reduction_Percent = Magic_Resistance / (Magic_Resistance + 100)
```

### Speed / Initiative (INIT)

```
Initiative = Effective_SPD + random_int(-3, 3)
```

- **Race:** Ents −20% speed penalty
- **Status:** Haste +30%, Shock −30%

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

- **Race:** Genies +15% spell crit chance
- Default crit damage multiplier: **1.5×** (Berserker trait overrides to 2.0×)

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
| Loner | Ignores race synergy matrix |
| Volatile / Battle Hardened | Morale decay × 2.0 / × 0.5 on ally death |
| Audience Favorite | +5% arena revenue when fielded (handled in `ArenaRevenueService`, not combat engine) |

---

## Turn Engine (Pending)

Sections still to implement for Milestone 6:

- Combat engine architecture (worker/service replacing `StubRandomMatchSimulator`)
- Turn resolution and speed order within a round
- Damage, healing, and status effect application per tick
- Logging and replay format (`combat_log` JSON structure)
- Performance and scaling considerations

---

## Summary

Combat runs in a deterministic simulation engine (server-side worker) producing event logs and final results. Turn order is determined by speed (SPD); actions resolve per-turn with spell and status interactions. Kill-based scoring determines the displayed match result; understaffed teams forfeit without simulation. **Today**, league fixtures use random kill scores via `StubRandomMatchSimulator` while derived stats and post-match processing are production-ready.

---

## API Endpoints (Planned)

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/v1/combat/simulate` | Practice/sandbox match |
| GET | `/api/v1/battles/{id}` | Battle result |
| GET | `/api/v1/battles/{id}/log` | Combat log / replay |

See [route-map.md](../route-map.md#combat).
