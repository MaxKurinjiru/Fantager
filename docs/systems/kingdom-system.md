# Kingdom System

Reference: [product/overview.md](../product/overview.md#kingdom)

Status: Live  
Screens: [01-kingdom-selection.md](../screens/01-kingdom-selection.md) · Code: [screen-code-map.md](../architecture/screen-code-map.md)  
Open design: none for XP — [known-issues.md](../product/known-issues.md) #6 Resolved (A7.1).

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

**Kingdom fields (live):** `name`, `language`, `timezone`, `game_speed`, `marketplace_tax_rate`, `season_length`, `league_tiers_config` (JSON), `royal_treasury_gold`.

**Live:** `level_cap` (default **20**), `xp_modifier` (default `1.00`, applied at XP award). See [hero-system.md](hero-system.md#locked-hero-level-invariant).

Player **capacity** is derived from `league_tiers_config`: `sum(tier.groups) × teams_per_group`. There is no separate `max_players` column.

`season_length` defaults to **28** in the entity; live seasons are **77 days** (11 weeks) via calendar/league scheduling — [calendar-system.md](calendar-system.md).

---

## Kingdom settings

| Setting | Live? | Effect |
|---------|-------|--------|
| Language | Yes | Default UI locale for new users in this kingdom |
| Timezone | Yes | Tick wall-clock in kingdom-local time |
| Game speed | Yes | Multiplier on some tick/economy effects (not the tick clock itself) — see below |
| Marketplace tax | Yes | Sale fee → Royal Treasury |
| Season length | Yes | Stored; season timeline is 77 days in calendar/league |
| `league_tiers_config` | Yes | Bracket shape + derived player capacity |
| Level cap / XP modifier | Yes | Cap **20**; `xp_modifier` multiplies match XP at award ([#6](../product/known-issues.md)) |

Crafting boost is **deferred** with the crafting system (`future/`).

---

## Isolation (not a server split)

- **One shared MariaDB** with `kingdom_id` on world-scoped tables. There is no per-kingdom database or process.
- **No player transfer** between kingdoms after registration.
- **No cross-kingdom** league fixtures, marketplace listings, mail, or forum threads.
- Tick workers iterate kingdoms independently (`app:ticks:run` / `KingdomTickRunnerService`) so each world’s timezone and `game_speed` apply.

“Server provisioning” and cross-kingdom migration are **not implemented** and are not planned in this alignment wave.

---

## Game speed

`Kingdom.game_speed` is a decimal (default `1.00`). Scheduled ticks still fire at the calendar times in [calendar-system.md](calendar-system.md). Speed scales **magnitudes**, for example:

- Daily **aging** increment (`round(1 × game_speed)` on `Hero.age` raw tenths; Undead excluded)
- **Summon** cap per weekly cycle (`max(1, round(1 × game_speed))`)
- HQ **upgrade duration** (shorter at higher speed)
- HQ **maintenance**, training gains, **fan-club** drift / match deltas, arena-related scaling

Full tick list: [calendar-system.md](calendar-system.md#weekly-server-tick-schedule). Dungeon and crafting ticks are deferred, not in the runner.

---

## Kingdom initialization

Admin/console: `php bin/console app:kingdom:initialize "Name"` (`InitializeKingdomCommand` → `KingdomInitializationService`). Settings come from `config/kingdom/*.defaults.json`.

When a new Kingdom is created, in order:

1. **Kingdom is persisted** with language, timezone, game speed, tax, season length, `league_tiers_config`, etc.
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
  "game_speed": "1.00",
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
| Tick runner | `KingdomTickRunnerService`, `ExecuteSingleTickHandler` |
| Config | `config/kingdom/*.defaults.json` |
| API | `Api\V1\KingdomController` |
| Register pick | `RegistrationService` + [01-kingdom-selection.md](../screens/01-kingdom-selection.md) |

**Tests:** tick runner/log — `tests/Service/Calendar/ProcessKingdomTicksHandlerTest.php`, `tests/Repository/KingdomTickLogRepositoryTest.php`. No dedicated `KingdomService` unit test file yet.
