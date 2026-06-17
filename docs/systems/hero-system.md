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
- Heroes are defined by race, age, primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) in the **1–20** range, and secondary stats (form, fatigue, morale).
- No rarity or class: value derives from training and equipment.

## Attribute calculations and derived stats

### Hero Generation & Stat Capping
When a hero is generated (either during starting roster initialization or when summoned from the Summoning Chamber), their primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) are rolled using the following rules:

**Inputs:** `HeroGenerator::createForTeam()` receives a `$chamberBonuses` array with the following keys:
- `summon_base_stat_bonus` (float) — added to the base stat value (default fallback: `BASE_STAT_VALUE = 1`)
- `summon_stat_random_bonus` (float) — added to the random roll ceiling (default fallback: `STAT_RANDOM_MAX = 2`)
- `summon_stat_total_cap` (float) — reserved for future total-cap overrides

For NPC teams (kingdom initialization), an empty array is passed — the built-in fallback constants are used.

1. **Base Value & Random Roll:**
   - For each attribute, the generator computes:
     - `effectiveBase` = `BASE_STAT_VALUE (1)` + round(`summon_base_stat_bonus`)
     - `effectiveRandomMax` = `STAT_RANDOM_MAX (2)` + round(`summon_stat_random_bonus`)
   - Each attribute is rolled three times, and one of the three rolls is randomly chosen to increase variance:
     $$\text{roll} = \text{random\_int}(0, \text{effectiveRandomMax})$$
     $$\text{base\_stat} = \text{effectiveBase} + \text{raceBonus} + \text{roll}$$

2. **Single-Stat Cap:**
   - A dynamic ceiling is applied to each individual attribute to prevent excessively high starting stats:
     $$\text{level} = \text{round}(\text{summon\_stat\_random\_bonus})$$
     $$\text{reduction} = 2 + \text{round}((\text{level} - 1) \times 0.11)$$
     $$\text{naturalMax} = \text{effectiveBase} + \text{effectiveRandomMax} + \text{raceBonus}$$
     $$\text{maxStatLimit} = \max(1, \text{naturalMax} - \text{reduction})$$
     $$\text{final\_stat} = \min(\text{maxStatLimit}, \text{base\_stat})$$
   - *Scaling targets (max race bonus = +3):*
     - Chamber level 1: max single stat **5** (with +3 race), total cap 12–14
     - Chamber level 5: max single stat **11**, total cap 36–43
     - Chamber level 10: max single stat **17**, total cap 68–72
   - *NPC teams (no chamber bonuses):* stats range **1–3** per attribute (before race bonuses).

3. **Dynamic Overall Cap ($maxTotal$):**
   - The total sum of all 8 attributes is dynamically limited to **60% of the sum of the individual max limits** for the selected race, with a safety floor of 8:
     $$maxTotal = \max(8, \text{round}(\sum \text{maxStatLimit} \times 0.6))$$
   - This ensures that heroes are specialized: they can have one or two very high stats, but the other stats will be reduced to fit the budget.

4. **Organic Random Trimming:**
   - If the sum of the rolled stats exceeds $maxTotal$, the excess points are trimmed step-by-step.
   - In each step, an eligible stat (whose value is currently greater than its minimum limit) is decremented by 1 at random.
   - The minimum floor for each stat is protected using the race bonus:
     $$\text{minLimit} = \max(1, \text{raceBonus})$$
   - This prevents a hero's primary racial strengths from being wiped out during trimming (e.g., an Orc's Strength will never drop below 3).

Starting roster:
- Every new team receives **10 heroes** at creation (6 match lineup + 4 reserves). See [team-system.md](team-system.md#starting-roster).
- Each hero starts at **level 1** with age random within **[Min Age, Max Junior Age]** for their race.

## Age Milestones & Terminology

Race age data lives in `config/game/races.yaml` under each race's `age` block.

| Milestone (player-facing) | Config key | Meaning |
|---------------------------|------------|---------|
| Minimum Age | `min` | Lowest recruitment age |
| Max Junior Age | `max_junior` | Upper bound for junior phase |
| Prime Age Limit | `prime_limit` | Last age with full training efficiency |
| **Mortality Threshold** | `mortality_threshold` | Age from which permanent death risk escalates after combat deaths |

**Implementation:** `RaceConfig::getMortalityThreshold()`, `resolveAgePhase()`, `isAtOrAboveMortalityThreshold()`. Twig helpers: `hero_age_phase()`, `hero_mortality_threshold()`, `hero_at_mortality_threshold()`.

Mechanics & APIs:
- Hero generation: Summoning Chamber produces junior heroes with randomized base stats within race ranges.
- GET /api/heroes — list for a player; GET /api/heroes/{id} — detail; POST/PUT for updates (rename, equipment changes)

Edge cases:
- Ensure death is final; move to Graveyard with immutable record
- Aging and mortality processing must be idempotent per tick


