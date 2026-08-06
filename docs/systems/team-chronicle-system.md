# Team Chronicle System

Reference: [entity-reference.md](../entity-reference.md#20-team-chronicle-append-only-event-log), [team-system.md](team-system.md), [screens/02-team-dashboard.md](../screens/02-team-dashboard.md)

Purpose: Document the **team chronicle** — an append-only event log bound to the `Team` entity, shown on the dashboard and on a dedicated full-history screen.

---

## Overview

The chronicle answers: **“What happened to this team over time?”** — not “what happened to the current player account?”

| Concern | Storage | Scope |
|---------|---------|--------|
| **Team chronicle** | `team_chronicle` | Per **team** (`team_id`); persists across managers and NPC periods |
| **Player notifications** | `notification` | Per **user**; unread state, immediate alerts |
| **Financial ledger** | `team_financial_record` | Per **team**; gold/essence audit trail |

A single `Team` row can be owned by many players over its lifetime (`user_id` set/cleared, `is_npc` toggled). Chronicle entries from previous managers remain visible to the next manager unless filtered in UI (no ownership-period filter yet).

---

## Data Model

Table: `team_chronicle` — entity `App\Entity\Team\TeamChronicle`.

| Field | Role |
|-------|------|
| `team_id` | Which team the event belongs to |
| `type` | `ChronicleEventType` — filter/group in UI |
| `subject_key` | Symfony translation key (e.g. `activity.player_joined`) |
| `subject_params` | JSON parameters for the translation string |
| `data` | Machine-readable context (`user_id`, `hero_id`, `gold`, `reason`, …) |
| `created_at` | Event timestamp (UTC) |

Entries are **append-only**. Individual rows are not updated; bulk pruning after a configurable retention period is planned but not implemented yet.

Rendering uses the **viewer's locale** at display time (`TeamChroniclePresenter` + `messages.*` keys under `activity.*`).

---

## Event Types

### Implemented

| `ChronicleEventType` | Trigger | Service / Method |
|----------------------|---------|-----------------|
| `team_established` | NPC team created during kingdom init | `KingdomInitializationService` |
| `player_joined` | Player assigned to NPC team (registration or test user) | `RegistrationService`, `TestUserService` |
| `player_released` | Team returned to NPC pool | See release reasons below |
| `team_renamed` | Team name changed in settings | `TeamService` |
| `season_ended` | League season transition rewards applied | `SeasonTransitionService` |
| `battle_win` | League match won | `TeamChronicleService::recordBattleOutcome()` |
| `battle_loss` | League match lost | `TeamChronicleService::recordBattleOutcome()` |
| `battle_draw` | League match drawn | `TeamChronicleService::recordBattleOutcome()` |
| `starting_roster` | Hero summoned to initial kingdom roster | `KingdomInitializationService` |
| `summon_completed` | Hero successfully summoned | `SummoningService` |
| `hero_levelup` | Hero reached a new level | `TeamChronicleService::recordHeroLevelup()` |
| `hero_dismissed` | Hero dismissed from roster | `HeroDismissalService` |
| `trainer_dismissed` | Trainer dismissed from roster | `TrainerDismissalService` |
| `trainer_promoted` | Hero promoted to trainer role — **player and NPC path** | `TrainingService::applyTrainerPromotion()` |
| `hero_died` | Hero died in combat | `TeamChronicleService::recordHeroDied()` |
| `training_completed` | Weekly training tick processed | `TrainingService` (tick handler) |
| `spell_learned` | Hero learned a spell | `SpellService` |
| `item_purchased` | Item bought on marketplace or from merchant | `TeamChronicleService::recordItemPurchased()` |
| `item_sold` | Item sold on marketplace | `TeamChronicleService::recordItemSold()` |
| `item_dismantled` | Item dismantled for essences | `ItemService` |
| `hero_purchased` | Hero bought on marketplace | `TeamChronicleService::recordHeroPurchased()` |
| `hero_sold` | Hero sold on marketplace | `TeamChronicleService::recordHeroSold()` |
| `trainer_purchased` | Trainer bought on marketplace | `TeamChronicleService::recordTrainerPurchased()` |
| `trainer_sold` | Trainer sold on marketplace | `TeamChronicleService::recordTrainerSold()` |
| `facility_upgraded` | HQ facility upgrade completed | `HeadquartersService` |
| `facility_downgraded` | HQ facility downgrade completed | `HeadquartersService` |
| `facility_upgrade_cancelled` | HQ facility upgrade cancelled | `HeadquartersService` |
| `race_optimization_changed` | Arena race adaptation changed — player or NPC | `HeadquartersService`, `NpcEconomySimulator` |
| `financial_crisis_state` | Financial crisis level changed | `FinancialCrisisService` |
| `kingdom_reward_granted` | Royal Treasury distribution received | `RoyalTreasuryService` |

> Note: `hero_retired` is present in `ChronicleEventType` enum and UI category filters for potential future hero retirement mechanics, but is not currently triggered by active services.

### `player_released` reasons (`ChronicleReleaseReason`)

| Reason | Trigger |
|--------|---------| 
| `inactivity` | 28-day inactivity release | `PlayerInactivityService::executeInactivityRelease()` |
| `bankruptcy` | Financial bankruptcy | `FinancialCrisisService::executeBankruptcy()` |
| `unverified_registration` | Unverified account deleted after 24 h | `ProcessKingdomTicksHandler::cleanupInactiveRegistrations()` |
| `account_deleted` | Player confirms account deletion | `SettingsController::confirmCancelAccount()` |

Translation keys: `activity.player_released.{reason}` with `%player%` param.

### `item_purchased` data shape

```json
{ "item_id": 123, "seller_team_id": 456, "price": 350 }
```

`seller_team_id` is `null` when purchased from the in-game merchant (`ItemService::buyFromMerchant`). Subject params: `%item%`, `%seller%`, `%price%`.

### `item_sold` data shape

```json
{ "item_id": 123, "buyer_team_id": 789, "price": 350 }
```

Subject params: `%item%`, `%buyer%`, `%price%`.

---

## Categories (UI filters)

`ChronicleCategory` groups types for the full chronicle page:

| Category | Types |
|----------|-------|
| `ownership` | `team_established`, `player_joined`, `player_released`, `team_renamed` |
| `competition` | `battle_win`, `battle_loss`, `battle_draw`, `season_ended` |
| `roster` | `starting_roster`, `hero_levelup`, `hero_died`, `hero_retired`, `training_completed`, `summon_completed`, `hero_dismissed`, `trainer_dismissed`, `spell_learned`, `trainer_promoted` |
| `economy` | `item_purchased`, `item_sold`, `hero_purchased`, `hero_sold`, `trainer_purchased`, `trainer_sold`, `facility_upgraded`, `facility_downgraded`, `facility_upgrade_cancelled`, `race_optimization_changed`, `item_dismantled`, `financial_crisis_state`, `kingdom_reward_granted` |
| `all` | no type restriction |

---

## Service Layer

| Class | Responsibility |
|-------|----------------|
| `TeamChronicleService` | Create and persist chronicle entries (single write entry point). Depends on `EntityManagerInterface`, `TickClock`, and `UserMessageTranslator`. |
| `TeamChroniclePresenter` | Load entries, translate messages, attach icons and type labels |
| `TeamChronicleRepository` | `findRecentByTeam()`, `findByTeamFiltered()` |

Do **not** instantiate `TeamChronicle` directly in feature code — call `TeamChronicleService` so keys and `data` shape stay consistent.

---

## Presentation

### Dashboard widget

- Template: `templates/components/dashboard/recent_chronicle.html.twig`
- Data: last **5** entries via `TeamChroniclePresenter::presentRecentForTeam()`
- Link: **Full chronicle** → `/app/chronicle`

### Full chronicle page

| Item | Value |
|------|--------|
| Route | `GET /app/chronicle` (`app_team_chronicle`) |
| Controller | `App\Controller\Web\TeamChronicleController` |
| Template | `templates/team_chronicle/index.html.twig` |
| Sidebar | Team & Base → **Kronika týmu** / **Team Chronicle** |

Query parameters:

| Param | Values |
|-------|--------|
| `category` | `all`, `ownership`, `competition`, `roster`, `economy` |
| `type` | any `ChronicleEventType` value, or empty = all |
| `sort` | `date-desc` (default), `date-asc` |

Filters auto-submit via `form-auto-submit` Stimulus controller.

Styles: `assets/styles/components/_chronicle.scss`.

---

## Related Systems

- [Auth System](auth-system.md) — `player_joined` at registration; `player_released` / account_deleted
- [Kingdom System](kingdom-system.md) — `team_established` and `starting_roster` for every NPC slot at init
- [Player Inactivity System](player-inactivity-system.md) — `player_released` / inactivity
- [Financial Crisis System](financial-crisis-system.md) — `player_released` / bankruptcy; `financial_crisis_state` on crisis level change; `kingdom_reward_granted` on treasury distribution
- [League System](league-system.md) — `season_ended`; `battle_win` / `battle_loss` / `battle_draw` on match resolution
- [Summoning System](summoning-system.md) — `summon_completed` (parallel detail in `TeamSummonHistory` per hero)
- [Training System](training-system.md) — `trainer_promoted` via `TrainingService::applyTrainerPromotion()` (player and NPC path); `training_completed` on weekly tick
- [Headquarters System](headquarters-system.md) — `facility_upgraded`, `facility_downgraded`, `facility_upgrade_cancelled`, `race_optimization_changed`
- [Marketplace System](marketplace-system.md) — `item_purchased` / `item_sold` / `hero_purchased` / `hero_sold` / `trainer_purchased` / `trainer_sold` on buy-now and auction settlement
- [Item System](item-system.md) — `item_purchased` on merchant purchase; `item_dismantled` on dismantle
- [Spell System](spell-system.md) — `spell_learned` on hero learning a spell
- [Hero System](hero-system.md) — `hero_dismissed`, `trainer_dismissed`, `hero_levelup`, `hero_died`
- [Team System](team-system.md) — `team_renamed`

---

## Notes

- **Existing teams** in databases initialized before this feature have **no backfilled** chronicle; only events after deployment are recorded.
- **Hero “Recent activity”** on the hero detail page still uses `hero_training_history` + `TeamSummonHistory`, not `team_chronicle`.
- **Notifications** remain the channel for “you need to know now”; chronicle is historical context on the team timeline.
