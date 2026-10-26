# Economy System

Reference: [product/overview.md](../product/overview.md#economy)

Status: Live  
Screens: [20-finance.md](../screens/20-finance.md), [02-team-dashboard.md](../screens/02-team-dashboard.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none

Purpose: Document currencies, sinks/sources, anti-exploit measures and economic invariants. Later product decisions (diminishing returns): [known-issues.md](../product/known-issues.md) § Later product decisions.

---

## Currencies

| Currency | Role |
|----------|------|
| **Gold** | Sole currency — earned from arena revenue, marketplace sales, league rewards, kingdom treasury distribution, item dismantle scrap; spent on HQ upgrades, summoning, marketplace purchases, spell learning, maintenance, repairs |

---

## Economic Controls

- Marketplace tax is `MarketplaceService::TAX_RATE_PERCENT` (**10**), the same in every kingdom
- Diminishing returns on repeated activities (planned)
- Time-gated income and scaling HQ upgrade costs
- All gold changes recorded in `FinancialRecord` audit log

---

## Audit & Financial Ledger

Every gold modification is recorded in the `FinancialRecord` entity.

### Ledger properties

- **Team**: Owner team whose wallet changed
- **Type**: `FinancialRecordType` enum (e.g. `marketplace_sale`, `hq_maintenance_fee`, `kingdom_reward`)
- **Actor**: `system`, `active`, or `passive`
- **Currency changes**: Signed integer `gold_change`
- **Context**: JSON with foreign keys for traceability (e.g. `listing_id`, `fixture_id`)

### Implemented `FinancialRecordType` values

`league_reward`, `arena_revenue`, `summon_fee`, `marketplace_sale`, `marketplace_purchase`, `marketplace_fee`, `dismantle_gain`, `item_repair`, `spell_learning_cost`, `hq_upgrade_cost`, `hq_maintenance_fee`, `debt_repayment`, `hero_dismissal_compensation`, `trainer_dismissal_compensation`, `hq_downgrade_refund`, `hq_upgrade_refund`, `kingdom_reward`, **`hero_salary`**, **`trainer_salary`**

**Reserved / unused (A6.2):** `spell_slot_cost` (magic capacity expands via training) and `morale_restoration` (no gold sink). Keep the enum cases and column; do not write them. `/app/finance` type filters use `FinancialRecordType::liveCases()`.

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
2. Split among **all teams** in the kingdom (including NPC). Weight per team is `tierWeight × reputationFactor × leagueFactor`, where `tierWeight` is 3 / 2 / 1 for T1 / T2 / T3, `reputationFactor = 1 + min(reputation, 500) / 500`, and `leagueFactor = 1 + leaguePoints / 100` (`RoyalTreasuryService::calculateTeamWeight`).
3. Credit each team via `EconomyService`; ledger type **`kingdom_reward`**
4. Deduct distributed amount from `royal_treasury_gold`

Runs as the **kingdom-scoped** WeeklyReset log **before** all team-scoped WeeklyReset ticks (maintenance, payroll, crisis) at the same timestamp — see [calendar-system.md](calendar-system.md). HQ maintenance collected later in this tick goes into next week's pool.

**Arena ticket revenue** is multiplied by the kingdom's stored `economy_scale` and also applies home-lineup **Audience Favorite** bonuses (`+5%` per fielded hero with the trait) in `ArenaRevenueService`. Weekly HQ maintenance uses the same `economy_scale`.

---

## Fan Club & Arena Attendance

**Service:** `App\Service\Team\FanClubService`

> **Not a player-facing feature.** Fan club is a **background mechanic** only — there is no dedicated screen, management UI, or player actions. It affects arena attendance and ticket revenue; size is surfaced on the **dashboard banner** and in the **HQ arena panel** (read-only).

Each team has a **`fan_base`** (default 350, range 0–10 000) that drives arena match attendance and therefore ticket revenue. **`last_fan_base_delta`** stores the most recent change (daily drift or match result) for dashboard display.

| Mechanism | Tick | Effect |
|-----------|------|--------|
| **Daily evolution** | `daily_reset` | `fan_base` drifts toward a target from reputation, morale, chemistry; drift magnitude scales with stored `fan_pace` |
| **Match result delta** | League match | Win +12, loss −10, draw +2, each multiplied by `fan_pace` — applied in `LeagueMatchResolutionService` |
| **Show-up rate** | On demand | Short-term multiplier from reputation, morale, chemistry — used by `ArenaRevenueService` |

Attendance formula (home match): home fans × show-up rate + away fans × 35% travel rate, capped by arena seating capacity.

Payout context (`attendance`, `capacity`, `ticket_price`, opponent) is stored on `FinancialRecord` (`arena_revenue`). The HQ arena panel charts the last **18** of those rows ([#4](../product/known-issues.md) Resolved / A7.8). See [18-arena-management.md](../screens/18-arena-management.md#locked-attendance-analytics).

---

## Financial Crisis

When weekly HQ maintenance or payroll cannot be fully paid, the unpaid portion is recorded as **`unpaid_debt`** on the team. Gold never goes negative.

See [financial-crisis-system.md](financial-crisis-system.md) for escalation (warning → restricted → bankruptcy), recovery actions, and API endpoints.

---

## Weekly Payroll

Hero and trainer salaries are charged on the **`weekly_reset` tick** (Sunday 23:59), after kingdom treasury distribution and HQ maintenance.

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

## Upcoming weekly cashflow

**Service:** `App\Service\Economy\CashflowForecastService` (Finance screen via `FinanceSummaryService::buildOverview($team, true)`)

Conservative forecast through the next **Sunday 23:59** weekly reset in the kingdom timezone:

| Line | Source |
|------|--------|
| Remaining home tickets | Scheduled home league fixtures with kickoff at or before that reset (including overdue unpaid kickoffs). Gold from `ArenaRevenueService::calculateMatchRevenue` |
| Sunday payroll / HQ maintenance | Same figures as the crisis status (`TeamPayrollService`, `HqMaintenanceCalculator`) |
| Estimated net | Ticket gold minus those Sunday costs |
| Kingdom share | Live tick pays `kingdom_reward` before costs; amount omitted |
| After Sunday tick | Gold and debt after costs and automatic debt repayment. Assumes remaining tickets land before the reset. Does not add kingdom share |

**Not included:** kingdom treasury share (paid **before** payroll in the live tick; pool is not player-facing, so the forecast stays conservative), marketplace, summoning, and other player actions. Gold never goes negative in the projection; unpaid Sunday costs add to `unpaid_debt`.

---

## Player-Facing Web Screens

Economy features are **not** merged into a single page. The sidebar exposes two routes:

| Route | Controller | Purpose |
|-------|------------|---------|
| `GET /app/marketplace` | `Web\MarketplaceController` | Marketplace tabs: browse, sell, my listings, transaction history, basic equipment |
| `GET /app/finance` | `Web\FinanceController` | Financial ledger with type/actor filters, overview summary, and upcoming weekly cashflow |

See [screens/20-finance.md](../screens/20-finance.md), [screens/15-marketplace.md](../screens/15-marketplace.md), and [architecture/routes.md](../architecture/routes.md#economy--marketplace).

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/finance/status` | Financial crisis status |
| GET | `/api/v1/finance/recent` | Recent ledger entries |
| GET | `/api/v1/marketplace` | Search listings |
| POST | `/api/v1/marketplace/listings` | Create listing |
| POST | `/api/v1/marketplace/purchase` | Buy listing |
| POST | `/api/v1/marketplace/bid` | Place auction bid |

Full route list: [architecture/routes.md](../architecture/routes.md#economy--marketplace).
