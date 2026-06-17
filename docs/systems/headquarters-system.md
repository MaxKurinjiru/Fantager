# Headquarters System

Reference: [game-summary.md](../game-summary.md#27-headquarters-system)

Purpose: Document HQ facilities, upgrades, passive bonuses, arena adaptation, and related APIs.

---

## Overview

Every team has exactly one `Headquarters` entity (1:1 with `Team`). The headquarters contains **7 facilities**, each independently upgradeable. All facilities start at **level 1** when the HQ is initialized for a new team.

> **Note:** The Forge/Workshop facility was removed from the codebase when crafting was deferred. Crafting design is preserved in [future/crafting-system.md](../future/crafting-system.md).

The `Headquarters` entity also tracks:
- The currently active **arena adaptation** (which race the team's arena is adapted for, affecting summoning).
- A **pending arena adaptation change** (applied on the next `race_optimization` tick on Sundays at 09:30).
- The **currently upgrading facility** and its scheduled completion time (only one upgrade at a time).
- The **total facility level** (sum of all facility levels; used as an aggregate metric).

---

## Facilities

| Facility | `FacilityType` value | Purpose |
|----------|----------------------|---------|
| Training | `training` | Speeds up hero attribute training |
| Medical | `medical` | Improves fatigue reduction and hero recovery |
| Library | `library` | Increases XP gains for heroes |
| Treasury | `treasury` | Increases passive gold income |
| Barracks | `barracks` | Increases the team's hero roster capacity |
| Summoning Chamber | `summoning_chamber` | Improves stats of summoned heroes |
| Arena | `arena` | Increases arena ticket revenue and seating capacity |

---

## Passive Bonuses

Passive bonuses scale **linearly** with the facility level. The bonus value is `bonus_per_level × level`.

| Facility | Bonus Key | Per-Level Value | Example at Level 5 |
|----------|-----------|-----------------|---------------------|
| Training | `training_efficiency_pct` | +5.0% | +25% training efficiency |
| Medical | `fatigue_reduction_pct` | +8.0% | +40% fatigue reduction |
| Medical | `recovery_speed_pct` | +5.0% | +25% recovery speed |
| Library | `xp_gain_pct` | +4.0% | +20% XP gain |
| Treasury | `gold_income_pct` | +4.0% | +20% gold income |
| Barracks | `roster_capacity` | +2 heroes | +10 heroes (base 10 → 20) |
| Summoning Chamber | `summon_base_stat_bonus` | +0.4 | +2.0 base stat bonus on summon |
| Summoning Chamber | `summon_stat_random_bonus` | +1.0 | +5.0 random stat bonus on summon |
| Summoning Chamber | `summon_stat_total_cap` | +7.0 | +35.0 total stat cap on summon |
| Arena | `ticket_revenue_pct` | +6.0% | +30% ticket revenue |
| Arena | `arena_capacity` | +10.0% | +50% seating capacity |

> The `Facility` entity stores a `metadata` (JSON) field. The `getPassiveBonuses()` method on `FacilityType` combines `metadata` with the static per-level values to compute the effective bonuses.

### Roster Capacity Formula (Barracks)

The base roster capacity is **10 heroes**. Barracks bonuses add on top:

```
Roster Limit = 10 + round(roster_capacity_bonus)
             = 10 + round(2.0 × barracks_level)
```

Example: Barracks level 3 → `10 + round(6.0) = 16` heroes.

---

## Upgrading Facilities

Only **one facility can be upgraded at a time**. Attempting a second upgrade while one is in progress throws a `DomainException`.

### Upgrade Cost Formula

The Gold cost scales exponentially with the current level:

```
Upgrade Cost = base_cost × 1.5^(current_level - 1)
```

### Base Upgrade Costs (Gold)

| Facility | Base Cost |
|----------|-----------|
| Training | 500 |
| Medical | 400 |
| Library | 600 |
| Treasury | 450 |
| Barracks | 350 |
| Summoning Chamber | 800 |
| Arena | 900 |

**Example costs:**

| Level upgrade | Training (base 500) | Arena (base 900) |
|---------------|---------------------|------------------|
| 1 → 2 | 500 | 900 |
| 2 → 3 | 750 | 1 350 |
| 3 → 4 | 1 125 | 2 025 |
| 5 → 6 | 2 531 | 4 556 |

### Upgrade Duration Formula

The time for an upgrade is proportional to the **target level** and inversely proportional to the kingdom's **game speed**:

```
Duration (seconds) = (target_level × 24 × 3600) / game_speed
```

This means upgrading to level 2 takes **2 real days** at game speed 1.0, upgrading to level 5 takes **5 days**, etc.

### Upgrade Completion

Facility upgrades are processed by the **`daily_reset` tick** (daily at 00:00 server time). When `now >= change_completed_at`, the facility level is incremented (upgrade) or decremented (downgrade), `changing_facility` is cleared, and `total_level` on the HQ is recalculated.

---

## Weekly Maintenance

HQ maintenance is charged on the **`weekly_reset` tick** (Sunday 23:59).

### Formula

```
HQ fee     = 50 + (total_level × 3)
Facilities = Σ (base_fee[type] × facility_level)
Total      = HQ fee + Facilities
```

### Base facility fees (gold per level / week)

| Facility | Fee |
|----------|-----|
| Training | 25 |
| Medical | 20 |
| Library | 30 |
| Treasury | 22 |
| Barracks | 18 |
| Summoning Chamber | 40 |
| Arena | 45 |

**Example:** All facilities at level 1 → total **271 gold/week**.

### Unpaid maintenance

If the team lacks sufficient gold, only the available balance is deducted. The remainder is added to `team.unpaid_debt`. See [financial-crisis-system.md](financial-crisis-system.md).

---

## Downgrading Facilities

Teams in financial difficulty can downgrade facilities to reduce weekly maintenance.

### Rules

- Only **one facility change** (upgrade or downgrade) at a time
- Minimum level = **1**
- Duration: `current_level × 12 hours / game_speed` (shorter than upgrades)
- **No upfront cost**; on completion, a **25% refund** of the last level's upgrade cost is paid
- A **lock cycle** prevents another downgrade until the next `weekly_reset` tick
- Downgrade is always allowed, including during financial restrictions

### Downgrade completion

Processed on **`daily_reset`** tick, same as upgrades. Sets `facility_downgrade_lock_cycle = true` until next weekly reset.

---

## Arena Adaptation

Arena adaptation controls which race the team's arena is configured for. This affects which races are offered in the **Summoning Chamber** (see [Summoning System](summoning-system.md)).

### Rules

- The active adaptation is stored in `Headquarters.race_optimization` (string, nullable — `null` = no adaptation).
- Changes are **not instant**. Requesting a change sets `pending_race_optimization` and flips `has_pending_race_optimization_change = true`.
- A lock cycle is enforced: after the change is applied, `race_optimization_lock_cycle = true` for one full cycle so teams cannot switch every week.
- Changes are applied during the **`race_optimization` tick** (Sundays at 09:30).
- Changing is blocked if `has_pending_race_optimization_change = true` or `race_optimization_lock_cycle = true`.

### Tick Processing

During the `race_optimization` tick:
1. If `has_pending_race_optimization_change = true`: copy `pending_race_optimization` → `race_optimization`, clear pending flag, set `race_optimization_lock_cycle = true`.
2. If `race_optimization_lock_cycle = true` (and no new pending change): clear the lock for the next cycle.

---

## Services

| Service | Namespace | Responsibility |
| ------- | --------- | -------------- |
| `HeadquartersService` | `App\Service\Headquarters` | Facility upgrades/downgrades, arena adaptation requests, passive bonus aggregation |
| `HqMaintenanceCalculator` | `App\Service\Headquarters` | Weekly HQ maintenance fee calculation |
| `ArenaService` | `App\Service\Headquarters` | Arena facility status (capacity, fan appeal, projected home-match revenue) — used by HQ panel and `/api/v1/arena` |

Ticket payout on match day is handled by `App\Service\Economy\ArenaRevenueService` (economy domain). Combat simulation is a separate domain — see [Combat System](combat-system.md) and the **Domain Boundaries** table in [entity-reference.md](../entity-reference.md#domain-boundaries-combat-vs-arena).

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/hq` | HQ hub (Twig-rendered; facility panels via `?facility=` query) |
| GET | `/api/v1/hq` | Facility levels, passive bonuses, upgrade/downgrade status |
| POST | `/api/v1/hq/upgrade` | Start a facility upgrade |
| POST | `/api/v1/hq/downgrade` | Start a facility downgrade |
| POST | `/api/v1/hq/optimize` | Request an arena adaptation change |
