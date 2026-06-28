# Economy System

Reference: [game-summary.md](../game-summary.md#23-economy-system)

Purpose: Document currencies, sinks/sources, anti-exploit measures and economic invariants.

---

## Currencies

| Currency | Role |
|----------|------|
| **Gold** | Primary currency — earned from arena revenue, marketplace sales, league rewards, kingdom treasury distribution; spent on HQ upgrades, summoning, marketplace purchases, spell learning, maintenance |
| **Essence** (common → mythic tiers) | Obtained from dismantling items and events; used for spell learning and (future) crafting/enchanting |

---

## Economic Controls

- Marketplace tax percentage per kingdom (`Kingdom.marketplace_tax_rate`)
- Diminishing returns on repeated activities (planned)
- Time-gated income and scaling HQ upgrade costs
- All gold changes recorded in `FinancialRecord` audit log

---

## Audit & Financial Ledger

Every currency modification (gold and essence tiers) is recorded in the `FinancialRecord` entity.

### Ledger properties

- **Team**: Owner team whose wallet changed
- **Type**: `FinancialRecordType` enum (e.g. `marketplace_sale`, `hq_maintenance_fee`, `kingdom_reward`)
- **Actor**: `system`, `active`, or `passive`
- **Currency changes**: Signed integer deltas per currency column
- **Context**: JSON with foreign keys for traceability (e.g. `listing_id`, `fixture_id`)

### Implemented `FinancialRecordType` values

`league_reward`, `arena_revenue`, `summon_fee`, `marketplace_sale`, `marketplace_purchase`, `marketplace_fee`, `dungeon_reward` *(reserved)*, `dismantle_gain`, `item_repair`, `spell_learning_cost`, `spell_slot_cost`, `hq_upgrade_cost`, `hq_maintenance_fee`, `morale_restoration`, `debt_repayment`, `hero_dismissal_compensation`, `trainer_dismissal_compensation`, `hq_downgrade_refund`, `kingdom_reward`, **`hero_salary`**, **`trainer_salary`**

---

## Royal Treasury

Kingdom-level gold pool that collects fees and redistributes a capped share to active teams each week. Balance and allocation weights are **not exposed to players**.

**Service:** `App\Service\Economy\RoyalTreasuryService`

**Kingdom field:** `royal_treasury_gold` on `Kingdom`

### Fee collection (`RoyalTreasuryContributionSource`)

| Source | When collected |
|--------|----------------|
| `marketplace_tax` | Marketplace sale completes (tax portion) |
| `hq_upgrade_cost` | HQ facility upgrade starts |
| `hq_maintenance_fee` | Weekly maintenance charged (portion routed to treasury) |
| `summon_fee` | Hero summoned |

### Weekly distribution (`weekly_reset` tick)

1. Compute distributable pool: `floor(royal_treasury_gold × 0.50)` (max 50% per week — tunable constant)
2. Split among all non-NPC teams in the kingdom, weighted by league tier standing
3. Credit each team via `EconomyService`; ledger type **`kingdom_reward`**
4. Deduct distributed amount from `royal_treasury_gold`

See [calendar-system.md](calendar-system.md) for tick schedule.

---

## Fan Club & Arena Attendance

**Service:** `App\Service\Team\FanClubService`

> **Not a player-facing feature.** Fan club is a **background mechanic** only — there is no dedicated screen, management UI, or player actions. It affects arena attendance and ticket revenue; size is surfaced on the **dashboard banner** and in the **HQ arena panel** (read-only).

Each team has a **`fan_base`** (default 350, range 0–10 000) that drives arena match attendance and therefore ticket revenue. **`last_fan_base_delta`** stores the most recent change (daily drift or match result) for dashboard display.

| Mechanism | Tick | Effect |
|-----------|------|--------|
| **Daily evolution** | `daily_reset` | `fan_base` drifts 3% per day toward a target derived from reputation, morale, chemistry |
| **Match result delta** | League match | Win +12, loss −10, draw +2 — applied in `LeagueMatchResolutionService` |
| **Show-up rate** | On demand | Short-term multiplier from reputation, morale, chemistry — used by `ArenaRevenueService` |

Attendance formula (home match): home fans × show-up rate + away fans × 35% travel rate, capped by arena seating capacity.

---

## Financial Crisis

When weekly HQ maintenance or payroll cannot be fully paid, the unpaid portion is recorded as **`unpaid_debt`** on the team. Gold never goes negative.

See [financial-crisis-system.md](financial-crisis-system.md) for escalation (warning → restricted → bankruptcy), recovery actions, and API endpoints.

---

## Weekly Payroll

Hero and trainer salaries are charged on the **`weekly_reset` tick** (Sunday 23:59), after HQ maintenance.

**Services:** `App\Service\Hero\HeroSalaryService` (per-hero calculation), `App\Service\Economy\TeamPayrollService` (team tick)

### Formula

```
weekly_salary = complex_rating × gold_per_complex_point × role_factor
```

Role factors (`hero_salary_factor`, `trainer_salary_factor`) live in `config/game/hero-rating.yaml`.

### Eligible roster

All team heroes whose status is **not** `dead` or `retired` (includes heroes listed on the marketplace).

### Unpaid salaries

If the team lacks sufficient gold after HQ maintenance, only the available balance is deducted. The remainder is added to `team.unpaid_debt`. Ledger types: **`hero_salary`**, **`trainer_salary`**.

---

### Key invariants

- Partial maintenance and payroll payments are allowed; remainder becomes debt
- Debt is repaid automatically from gold during the weekly crisis tick
- Restricted teams cannot upgrade HQ, summon, or buy on marketplace
- Bankruptcy releases the team back to the NPC pool after prolonged insolvency

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/economy` | Economy hub (marketplace + ledger tabs) |
| GET | `/api/v1/finance/status` | Financial crisis status |
| GET | `/api/v1/finance/recent` | Recent ledger entries |
| GET | `/api/v1/marketplace` | Search listings |
| POST | `/api/v1/marketplace/listings` | Create listing |
| POST | `/api/v1/marketplace/purchase` | Buy listing |
| POST | `/api/v1/marketplace/bid` | Place auction bid |

Full route list: [route-map.md](../route-map.md#economy--marketplace).
