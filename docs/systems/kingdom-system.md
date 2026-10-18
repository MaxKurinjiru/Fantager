# Kingdom System

Reference: [product/overview.md](../product/overview.md#kingdom)

Status: Live  
Screens: [01-kingdom-selection.md](../screens/01-kingdom-selection.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none

Purpose: Document the live multi-tenant kingdom model — settings, initialization, query isolation, game-speed multipliers, and the public list API.

Do not invent a separate process or database per kingdom.

---

## Overview

A **Kingdom** is one shared game world inside a single application. Isolation is **`kingdom_id` on rows**, not a separate server instance.

Each player (`User` + `Team`) belongs to exactly one Kingdom, chosen once on the **registration form** (permanent; no post-login switcher). A Kingdom is not owned by a player — it is a shared environment with its own economy, league, marketplace, and ticks.

- Relationship: `Kingdom (1) → Users (N)`, `Kingdom (1) → Teams (N)`, `User (1) → Team (1)`
- During registration, the player is assigned a random available NPC team in that Kingdom (see [auth-system.md](auth-system.md)).
- Repositories, background ticks, and API queries scope by `kingdom_id`. League, marketplace, mail, and forum do not cross kingdoms.

---

## Data model

Canonical columns: [entities.md](../architecture/entities.md) § Kingdom Domain.

| Entity | Table | Role |
|--------|-------|------|
| **Kingdom** | `kingdom` | World settings and Royal Treasury balance |
| **KingdomTickLog** | `kingdom_tick_log` | Idempotent tick executions (see [calendar-system.md](calendar-system.md)) |

**Kingdom fields (live):** `name`, `language`, `timezone`, `pace_preset`, `training_speed`, `recovery_speed`, `aging_speed`, `construction_speed`, `economy_scale`, `fan_pace`, `summon_cap`, `xp_modifier` (copied from the pace preset), `season_length`, `league_tiers_config` (JSON), `royal_treasury_gold`.

**Live:** Hero level cap is `HeroProgressionConfig::LEVEL_CAP` (**20**). Marketplace tax is `MarketplaceService::TAX_RATE_PERCENT` (**10**). Neither is a kingdom column. See [hero-system.md](hero-system.md#locked-hero-level-invariant).

Player **capacity** is derived from `league_tiers_config`: `sum(tier.groups) × teams_per_group`. There is no separate `max_players` column.

`season_length` is **77** days (11 weeks). The entity default, the database column, bootstrap JSON, and the season end date all use that value — [calendar-system.md](calendar-system.md).

---

## Kingdom settings

| Setting | Live? | Effect |
|---------|-------|--------|
| Language | Yes | Default UI locale for new users in this kingdom |
| Timezone | Yes | Tick wall-clock in kingdom-local time |
| Pace preset | Yes | Required. `classic`, `fast_season`, `short_careers`, `rich_world`, or `test`. Copies the pace numbers below at creation. Does not move the tick clock |
| Marketplace tax | Constant | `MarketplaceService::TAX_RATE_PERCENT` is **10** everywhere. Not stored on the kingdom |
| Season length | Yes | **77** days (11 weeks). Sets the season end date at creation and at the next-season handoff |
| `league_tiers_config` | Yes | Bracket shape + derived player capacity |
| XP modifier | Yes | Copied from the pace preset. Multiplies match XP at award, together with the Library bonus ([#6](../product/known-issues.md)). Independent of training speed |
| Pace values | Yes | Required decimals and summon cap, copied from the preset. See [Pace presets](#pace-presets) |

Crafting boost is **deferred** with the crafting system (`future/`).

---

## Isolation (not a server split)

- **One shared MariaDB** with `kingdom_id` on world-scoped tables. There is no per-kingdom database or process.
- **No player transfer** between kingdoms after registration.
- **No cross-kingdom** league fixtures, marketplace listings, mail, or forum threads.
- Tick workers iterate kingdoms independently (`app:ticks:run` / `KingdomTickRunnerService`) so each world’s timezone applies. Pace values scale magnitudes, not the clock.

“Server provisioning” and cross-kingdom migration are **not implemented** and are not planned in this alignment wave.

---

## Pace presets

There is no `game_speed`. Every kingdom stores `pace_preset` plus the seven pace values. Creation (`--pace`, default `classic`) copies the numbers from `config/kingdom/pace-presets.json`. A kingdom cannot be initialized without a known preset. Scheduled ticks still fire at the calendar times in [calendar-system.md](calendar-system.md). Pace scales **magnitudes**. A non-positive multiplier is treated as `1` when read. Resolution lives in `KingdomPace`.

| Preset | Training | Recovery | Aging | Construction | Economy | Fans | Summons |
|--------|----------|----------|-------|--------------|---------|------|---------|
| `classic` | 1.00 | 1.00 | 1.00 | 1.00 | 1.00 | 1.00 | 1 |
| `fast_season` | 1.50 | 1.50 | 1.00 | 2.00 | 1.00 | 1.00 | 1 |
| `short_careers` | 1.00 | 1.00 | 2.00 | 1.00 | 1.00 | 1.00 | 1 |
| `rich_world` | 1.00 | 1.00 | 1.00 | 1.00 | 1.50 | 1.25 | 1 |
| `test` | 3.00 | 3.00 | 0.50 | 5.00 | 1.00 | 1.00 | 2 |

| Pace | Column | Effect |
|------|--------|--------|
| Training | `training_speed` | Attribute gains and their cap, magic-capacity gain, magic school XP, form gained from rest training |
| Recovery | `recovery_speed` | Daily fatigue drop (`round(10 × pace)`) and form gain (`round(5 × pace)`), before Medical Wing bonuses |
| Aging | `aging_speed` | Daily hero age `round(1 × pace)` on raw tenths (Undead excluded). Trainer age on the training tick scales the race span `(mortality_threshold - min_age) / 22` by this pace |
| Construction | `construction_speed` | HQ upgrade duration `(target_level × 24 h) / pace` and downgrade duration `(current_level × 12 h) / pace` |
| Economy | `economy_scale` | Arena ticket gold and weekly HQ maintenance, same multiplier |
| Fans | `fan_pace` | Daily fan-base drift and the win / loss / draw fan delta |
| Summons | `summon_cap` | Integer cap per weekly cycle, minimum 1 |
| Match XP | `xp_modifier` | Multiplies match XP after the Library bonus. Every current preset stores `1.00` |

The registration card shows the preset name and season length. It does not show pace numbers, XP, or tax. Marketplace tax is `MarketplaceService::TAX_RATE_PERCENT`. Hero level cap is `HeroProgressionConfig::LEVEL_CAP`. Season length is not part of the preset.

Full tick list: [calendar-system.md](calendar-system.md#weekly-server-tick-schedule). Dungeon and crafting ticks are deferred, not in the runner.

---

## Kingdom initialization

Admin/console: `php bin/console app:kingdom:initialize "Name" --pace=classic` (`InitializeKingdomCommand` → `KingdomInitializationService`). World settings come from `config/kingdom/*.defaults.json`. Pace numbers come from `config/kingdom/pace-presets.json`.

When a new Kingdom is created, in order:

1. **Kingdom is persisted** with language, timezone, the chosen pace preset and its pace values, tax, season length, `league_tiers_config`, etc.
2. **First `LeagueSeason`** is created (`season_number = 1`). `LeagueTier` and `LeagueGroup` rows are built from `league_tiers_config` — the bracket skeleton exists but is empty until teams are placed.
3. **NPC teams** fill every group (`is_npc = true`, `user_id = NULL`): generated name/emblem, `LeagueStanding`, chronicle `team_established`.
4. **Heroes** — **10 per team**, generated with Summoning Chamber bonuses from the new HQ (`KingdomInitializationService::extractChamberBonuses()`). Each team also gets a default formation.

When a real player registers, one randomly selected unclaimed NPC team (`user_id IS NULL`) in that Kingdom is assigned **during registration** (`player_joined` chronicle). `user_id` is set and `is_npc` is set to `false`. See [auth-system.md](auth-system.md) and [team-chronicle-system.md](team-chronicle-system.md).

Optional flags on the command (`--test`, `--start-offset-days`, `--catch-up-ticks`) shift season start and can run missed ticks synchronously — local/dev helpers, not a second architecture.

---

## APIs

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/kingdoms` | Public. `KingdomService::listWithCapacity()` |

Kingdoms **without an active season are omitted** from the list (not a `has_active_season` field). Each item:

```json
{
  "id": 1,
  "name": "Main Kingdom",
  "language": "en",
  "timezone": "Europe/Prague",
  "pace_preset": "classic",
  "training_speed": "1.00",
  "recovery_speed": "1.00",
  "aging_speed": "1.00",
  "construction_speed": "1.00",
  "economy_scale": "1.00",
  "fan_pace": "1.00",
  "summon_cap": 1,
  "xp_modifier": "1.00",
  "season_length": 77,
  "capacity": 60,
  "player_count": 12
}
```

Kingdom is chosen **on the registration form**, not via a separate POST. Register Twig uses server-rendered `auth_kingdoms()`; the JSON list is the same data for other clients. Registration is blocked when the kingdom has no active season (`register.kingdom.no_active_season`) or is at capacity (`KingdomService::hasCapacity()`).

Related (other systems): `GET /api/v1/kingdom/{id}/calendar` — [calendar-system.md](calendar-system.md). Canonical list: [architecture/routes.md](../architecture/routes.md).

---

## Implementation notes

| Piece | Location |
|-------|----------|
| Entity | `src/Entity/Kingdom/Kingdom.php`, `KingdomTickLog.php` |
| List / capacity / active season | `App\Service\Kingdom\KingdomService` |
| Bootstrap | `App\Service\Kingdom\KingdomInitializationService`, `app:kingdom:initialize` |
| Pace resolution | `App\Service\Kingdom\KingdomPace` |
| Tick runner | `KingdomTickRunnerService`, `ExecuteSingleTickHandler` |
| Config | `config/kingdom/*.defaults.json` |
| API | `Api\V1\KingdomController` |
| Register pick | `RegistrationService` + [01-kingdom-selection.md](../screens/01-kingdom-selection.md) |

**Tests:** pace resolution — `tests/Service/Kingdom/KingdomPaceTest.php`. Tick runner/log — `tests/Service/Calendar/ProcessKingdomTicksHandlerTest.php`, `tests/Repository/KingdomTickLogRepositoryTest.php`. No dedicated `KingdomService` unit test file yet.
