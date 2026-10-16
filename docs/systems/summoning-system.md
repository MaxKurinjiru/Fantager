# Summoning System

Reference: [product/overview.md](../product/overview.md), [screens/09-summoning-chamber.md](../screens/09-summoning-chamber.md)

Status: Live — Genie adaptation uses relationship ≥ 80 ([#14](../product/known-issues.md) Resolved / A7.9)  
Screens: [09-summoning-chamber.md](../screens/09-summoning-chamber.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none for the compatible-race pool. Lock: [Locked: Genie summon pool](#locked-genie-summon-pool).

Purpose: Document summoning mechanics, costs, cooldowns, race selection, and hero generation.

## Remaining work

- [x] Compatible-race summon, cooldowns, `TeamSummonHistory`, trait reveal
- [x] Shared pool: relationship ≥ 50, Genie adaptation ≥ 80 (A7.9 / [#14](../product/known-issues.md))
- [x] `summon_stat_total_cap` reserved / unread (A6.5) — do not apply or show on HQ

---

## Overview

Teams can summon new heroes through the **Summoning Chamber** — a facility in their Headquarters. Each summon:
1. Checks availability (roster limit, cycle limit, gold).
2. Selects a race based on the team's arena adaptation and race relationships.
3. Generates a level-1 hero using `HeroGenerator` with bonuses from the Summoning Chamber facility.
4. Deducts gold and records the event in `TeamSummonHistory` and the team chronicle (`summon_completed` in `team_chronicle`).

See [team-chronicle-system.md](team-chronicle-system.md).

---

## Availability & Limits

### Roster Capacity
Summoning is blocked if `hero_count >= roster_limit`.
- Base roster limit: **10 heroes**.
- Scales with Barracks facility level (see [Headquarters System](headquarters-system.md#roster-capacity-formula-barracks)).
- The check uses `HeroRepository::countActiveCombatantsByTeam` — only **active combatants** (role = `Combatant`, status not in `Dead`/`Retired`) count toward the limit. Trainers and dead heroes are excluded.

### Cycle Limit
Each team may only summon a limited number of heroes per **weekly cycle**. The limit resets every Sunday at 23:59 as part of the `weekly_reset` tick.

```
Max Summons per Cycle = round(1 × game_speed)
                      minimum: 1
```

At default game speed (`1.0`): **1 summon per cycle**.

---

## Summon Cost (Gold)

The Gold cost scales with the **Summoning Chamber facility level** and with **how many times the team has already summoned this cycle** (inflation).

### Formula

```
Base Cost  = 500 × 1.2^(chamber_level - 1)
Final Cost = Base Cost × (1.0 + 0.5 × summons_this_cycle)
```

Both results are rounded to the nearest integer.

### Cost Table (default chamber, various cycle positions)

| Summons this cycle | Chamber Lv 1 | Chamber Lv 3 | Chamber Lv 5 |
|--------------------|--------------|--------------|--------------|
| 0 (1st summon) | 500 | 720 | 1 036 |
| 1 (2nd summon) | 750 | 1 080 | 1 555 |
| 2 (3rd summon) | 1 000 | 1 440 | 2 073 |

> Cost increases by **+50%** for each additional summon in the same cycle. Higher chamber levels increase the base cost but also improve the quality of summoned heroes.

---

## Race Selection

The race of a summoned hero is **not freely chosen** by the player. It is drawn randomly from a pool of **compatible races** determined by the team's arena adaptation (`Headquarters.race_optimization`).

### Compatible Race Pool

Player summons, NPC daily summons, NPC marketplace hero buys, and kingdom starting rosters share `RaceConfig::getCompatibleRacesForAdaptation()` (summoning via `SummoningService::getCompatibleRacesForTeam()`):

1. The active `race_optimization` value from `Headquarters` is the **adapted race**. Kingdom init uses the team's assigned race (the same value written to HQ).
2. All 8 races are evaluated against the adapted race using the **relationship matrix** (`config/game/race_relations.yaml`).
3. Races with a relationship score `>= 50` to the adapted race are included in the pool.
4. **Genie exception:** if the adapted race is Genie, the bar is `>= 80` (live pool: **Genie, Human, Elf**).
5. A race always has a self-relationship of `100` and is always included.
6. If no adaptation is set (`race_optimization = null`), **all 8 races** are in the pool.
7. One race is drawn uniformly at random from the pool.

See [Locked: Genie summon pool](#locked-genie-summon-pool).

## Locked: Genie summon pool

**#14 Resolved (A7.9 Implement).** One compatible-race path. Do not restore a Genie-only NPC buy filter or a Genie-only starting roster.

| Rule | Meaning |
|------|---------|
| Shared pool | `RaceConfig::getCompatibleRacesForAdaptation()` is the live pool for player summons, NPC daily summons, NPC marketplace **hero** buys, and kingdom starting roster. |
| Default bar | Relationship **≥ 50** to the adapted race (self always included). Unset adaptation → all 8 races. |
| Genie bar | Adapted race **Genie** uses **≥ 80**. Live matrix pool: **Genie, Human, Elf**. Undead (70) and the 60-rated races stay out. |
| Not Genie-only | Do not hardcode `[Genie]` in `NpcEconomySimulator` or kingdom init. Player marketplace buys still have **no** race filter. |
| Training / chemistry | Unchanged. Training group / arena bonus still uses ≥ 70; hostile chemistry still ≤ 20. |
| Existing rosters | Heroes already on a Genie-adapted team stay. No refresh job. New summons, NPC buys, and new kingdoms use the ≥ 80 pool. |

---

## Hero Generation

New heroes are created by `HeroGenerator::createForTeam()`. The following process applies:

### Age
Age is rolled uniformly between `min_age` and `max_junior_age` for the hero's race (defined in `config/game/races.yaml`). The raw age value is stored as `age_years × 10 + random_offset(0–9)`.

### Stats
Primary attributes (STR, DEX, KON, SPD, INT, WIL, CHA, LCK) are generated using:

```
Effective Base      = BASE_STAT_VALUE + round(summon_base_stat_bonus)
Effective Random    = STAT_RANDOM_MAX  + round(summon_stat_random_bonus)

Built-in fallbacks (no chamber bonuses):
  BASE_STAT_VALUE = 1
  STAT_RANDOM_MAX = 2
```

For each stat, three rolls are made; one is selected at random. Race bonuses are added on top:
```
Roll = Effective Base + race_stat_bonus[stat] + random_int(0, Effective Random)
```

### Stat Caps
To prevent both hyper-specialized and perfectly balanced heroes:
- Each stat is individually capped at `max(1, natural_max - reduction)` where `natural_max = Effective Base + Effective Random + race_bonus` and `reduction = 2 + round((chamber_level - 1) × 0.11)`.
- The total stat sum is capped at **60% of the sum of all individual ceilings** (minimum 8). Excess is distributed randomly across stats while respecting per-stat minimums. Chamber `summon_stat_total_cap` is computed but **unread** (A6.5); HQ does not show it as a live bonus.

### Raw Internal Scaling
All stats are stored in the database **scaled by ×10** (internal range `10–200`). The generator rolls in the `1–20` external scale and stores `stat_value × 10 + random_int(0, 9)`. Displayed values are `floor(raw / 10)`.

### Hero Name
A random first name and surname are drawn from race-specific name lists defined in `HeroGenerator` (hardcoded; each list contains 30 entries per race).

### Personality Trait
After generation, the hero has a **60% chance** of receiving a random `HeroTrait` (see [hero-system.md](hero-system.md) § Hero Traits). The summon reveal card shows a trait badge when assigned; the full `POST /api/v1/summoning` response includes nullable `trait`.

### Scaling Reference

| Chamber Level | Approx. max single stat (with +3 race bonus) | Approx. total stat sum range |
|---------------|----------------------------------------------|------------------------------|
| 1 | 5 | 12–14 |
| 5 | 11 | 36–43 |
| 10 | 17 | 68–72 |

---

## Summon History

Every summon is recorded in the `TeamSummonHistory` entity (table `team_summon_history`):

| Field | Description |
|-------|-------------|
| `team_id` | Team that summoned |
| `race_selected` | Race drawn for the hero |
| `hero_id` | The resulting `Hero` entity |
| `gold_cost` | Actual gold paid |
| `summoned_at` | Timestamp |

The summon history is displayed in the **Summoning Chamber panel** on the HQ page (`/app/hq?facility=summoning_chamber&subtab=history`). Legacy route `/app/summon/history` redirects there. The same event also appears on the **team chronicle** (dashboard widget and `/app/chronicle`).

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/summon` | Redirect → `/app/hq?facility=summoning_chamber` |
| GET | `/app/summon/history` | Redirect → `/app/hq?facility=summoning_chamber&subtab=history` |
| GET | `/api/v1/summoning/status` | Returns availability, cost, cycle usage, and compatible races |
| POST | `/api/v1/summoning` | Perform a summon (deducts gold, creates hero) |

### `GET /api/v1/summoning/status` — Response Shape

```json
{
  "available": true,
  "reason": null,
  "reason_parameters": {},
  "gold_cost": 500,
  "summons_used": 0,
  "summons_max": 1
}
```

When unavailable, `reason` is a translation key (roster full, cycle limit, insufficient gold, crisis spending block). `reason_parameters` holds placeholders for that key when the block comes from a `UserFacingException`.
