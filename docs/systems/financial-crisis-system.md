# Financial Crisis System

Reference: [economy-system.md](economy-system.md), [headquarters-system.md](headquarters-system.md), [team-system.md](team-system.md)

Purpose: Handle long-term team insolvency — debt tracking, escalating restrictions, recovery paths, and bankruptcy.

---

## Overview

When a team's weekly expenses exceed its income, unpaid HQ maintenance and payroll accumulate as **`unpaid_debt`** on the `Team` entity. Gold never goes negative; instead debt grows until the team recovers or faces bankruptcy.

The system uses four crisis levels:

| Level | Trigger | Player impact |
|-------|---------|---------------|
| `none` | Debt = 0 | Normal play |
| `warning` | Debt > 0 | Dashboard warning, full gameplay |
| `restricted` | Debt > 0 for ≥ 2 consecutive crisis weeks | HQ passive bonuses disabled; upgrades, summoning, marketplace purchases blocked |
| `bankruptcy_pending` | Debt ≥ 4× weekly fixed costs (maintenance + payroll), gold = 0, ≥ 6 crisis weeks, no recent recovery | Team released to NPC pool on next weekly tick |

---

## Team Fields

| Field | Type | Description |
|-------|------|-------------|
| `unpaid_debt` | int | Cumulative unpaid maintenance and payroll |
| `crisis_weeks` | int | Consecutive weeks in financial instability |
| `last_recovery_action_at` | datetime? | Last dismiss / downgrade / marketplace sale / debt payoff |

`auth_user.team_reassignment_available_at` — cooldown before a bankrupt player can claim a new NPC team (7 days).

---

## Weekly Tick Flow (`weekly_reset`)

1. Reset summon cycle counters
2. `HeadquartersService::processMaintenanceTick()` — deduct available gold; remainder → `unpaid_debt`; portion may route to Royal Treasury
3. `TeamPayrollService::processPayrollTick()` — deduct hero/trainer salaries; remainder → `unpaid_debt`
4. `RoyalTreasuryService::processWeeklyDistribution()` — redistribute up to 50% of kingdom pool to teams (`kingdom_reward`)
5. `HeadquartersService::processFacilityDowngradeLockTick()` — clear downgrade lock cycle
6. `FinancialCrisisService::processWeeklyCrisisTick()` — auto-pay debt from gold, evaluate crisis weeks, execute bankruptcy if needed

---

## Recovery Actions

| Action | Service / Endpoint | Notes |
|--------|-------------------|-------|
| Sell on marketplace | `MarketplaceService` | Always allowed; records recovery on sale |
| Sell on marketplace | `MarketplaceService` | Always allowed; records recovery on sale; min 6 combat-ready heroes for hero listings |
| Dismiss hero | `POST /api/v1/heroes/{id}/dismiss` | 40% of `complex_rating`-based gold value ([hero-rating-system.md](hero-rating-system.md)); min 6 combat-ready heroes kept; memorial record on Graveyard |
| Dismiss trainer | `POST /api/v1/training/trainers/{id}/dismiss` | 30% of `complex_rating`-based gold value; trainees auto-unassigned; memorial record on Graveyard |
| Downgrade facility | `POST /api/v1/hq/downgrade` | Timed like upgrade; 25% refund of last level's upgrade cost on completion |
| Earn income | Arena, league | Gold auto-applies to debt each weekly tick |

---

## Facility Downgrade Rules

- Only **one facility change** (upgrade or downgrade) at a time
- Minimum facility level = **1**
- Duration: `current_level × 12 hours / game_speed`
- **No upfront cost**; partial refund paid on completion
- **Lock cycle** for 1 week after downgrade (prevents upgrade/downgrade cycling)
- Downgrade always allowed (even during restrictions)

---

## Blocked Actions (Restricted / Bankruptcy Pending)

- HQ upgrade (`POST /api/v1/hq/upgrade`)
- Arena adaptation change (`POST /api/v1/hq/optimize`)
- Summoning (`POST /api/v1/summoning`)
- Marketplace purchase / bid

---

## HQ Bonus Suspension

During `restricted` or `bankruptcy_pending` with `unpaid_debt > 0`, passive HQ bonuses (Arena capacity/revenue, Treasury income, Barracks roster, etc.) are **not applied**. See `FinancialCrisisService::areHqBonusesActive()`.

---

## Bankruptcy

When bankruptcy triggers:

1. Team `user_id` → NULL, `is_npc` → true
2. Team chronicle entry: `player_released` / `bankruptcy` (`FinancialCrisisService::executeBankruptcy()`)
3. Team gold, debt, and crisis counters reset
4. User `team` → NULL; `team_reassignment_available_at` set (+7 days)
5. System notification sent to player
6. Heroes, HQ, and roster remain on the released NPC team

---

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/finance/status` | Full financial crisis status |
| POST | `/api/v1/heroes/{id}/dismiss` | Dismiss hero for partial compensation |
| POST | `/api/v1/hq/downgrade` | Start facility downgrade |

Dashboard (`GET /api/v1/teams/{id}/dashboard`) includes `financial_crisis` object.

### Web UI

| Screen | Component | Behavior |
|--------|-----------|----------|
| Dashboard | `financial_crisis_banner.html.twig` | Warning/restricted/bankruptcy banner with recovery links |
| Dashboard stats | `stats_card.html.twig` | Shows unpaid debt row when debt > 0 |
| HQ | `financial_crisis_banner` + `facility_card` downgrade button | Downgrade with confirm dialog; upgrade disabled during restrictions |
| Hero detail | `sell_panel.html.twig` + `dismiss_panel.html.twig` | Sell or dismiss available heroes (unassigned from trainer); blocked at min roster |

Twig helpers: `team_financial_crisis(team)`, `hq_downgrade_refund(type, level, totalLevel)`.

---

## Financial Ledger Types

| Type | When |
|------|------|
| `debt_repayment` | Gold applied to outstanding debt |
| `hero_dismissal_compensation` | Hero dismissed |
| `hero_salary` | Weekly hero payroll tick |
| `trainer_salary` | Weekly trainer payroll tick |
| `hq_downgrade_refund` | Facility downgrade completed |

---

## Constants (`FinancialCrisisService`)

| Constant | Value |
|----------|-------|
| `RESTRICTED_WEEKS` | 2 |
| `BANKRUPTCY_WEEKS` | 6 |
| `BANKRUPTCY_DEBT_MULTIPLIER` | 4 |
| `REASSIGNMENT_COOLDOWN_DAYS` | 7 |
| `RECOVERY_STALE_WEEKS` | 2 |

---

## Implementation Notes

- **Service:** `App\Service\Economy\FinancialCrisisService`
- **Payroll:** `App\Service\Economy\TeamPayrollService`
- **Hero dismissal:** `App\Service\Hero\HeroDismissalService`
- **Roster minimum:** `App\Service\Team\TeamRosterService` (6 combat-ready heroes)
- **Maintenance calc:** `App\Service\Headquarters\HqMaintenanceCalculator`
- **Tick handler:** `ProcessKingdomTicksHandler` (`weekly_reset` case)
