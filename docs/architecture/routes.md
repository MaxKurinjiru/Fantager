# Route Map

Purpose: Live Web + API routes as implemented in `src/Controller/`. Planned rows are marked **planned** and have no controller. Deferred Milestone 8 systems are a pointer only — not listed as if they exist.

Reference: [architecture/api.md](api.md), [architecture/screen-code-map.md](screen-code-map.md), [auth-system.md](../systems/auth-system.md)

---

## Conventions

- **Web routes** (e.g. `/`, `/app/heroes`, `/app/league`...): Twig-rendered pages, handled by `App\Controller\Web\*`. Authenticated routes are prefixed with `/app`.
- **API routes** (`/api/v1/*`): JSON responses, handled by `App\Controller\Api\V1\*`
- All authenticated routes require `ROLE_PLAYER` unless noted
- API follows REST principles defined in [architecture/api.md](api.md)
- **Redirect** in Purpose means the action exists but only forwards to another live screen
- Counts in [Summary](#summary) are method-level `#[Route]` attributes in code (GET+POST on one attribute = one route; two attributes on one action, e.g. ticket-price alias, = two routes)

---

## Public (unauthenticated)

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/` | Web\DefaultController | Homepage |
| GET | `/news` | Web\NewsController | News archive (each item displayed in full; no detail page) |
| GET | `/wiki` | — | Help/wiki section — **planned** (A8.5 stay Planned; later product decision) |

---

## Auth

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET, POST | `/login` | Web\AuthController | Render login form; POST is Symfony `form_login` (`check_path`) |
| GET, POST | `/register` | Web\RegisterController | Show/submit registration form (kingdom selection included) |
| GET | `/register/success` | Web\RegisterController | Post-registration landing (check your email) |
| POST | `/register/resend-verification` | Web\RegisterController | Resend verification email (rate-limited) |
| GET | `/verify-email` | Web\RegisterController | Verify email token, activate account, auto-login (NPC team was assigned at registration) |
| GET | `/logout` | Web\AuthController | Destroy session (Symfony logout firewall) |
| GET, POST | `/password-reset` | Web\PasswordResetController | Request password reset email |
| GET, POST | `/password-reset/confirm` | Web\PasswordResetController | Validate token + submit new password |
| GET | `/confirm-email-change/old` | Web\SettingsController | Confirm e-mail change from old address (token link sent via e-mail) |
| GET | `/confirm-email-change/new` | Web\SettingsController | Confirm e-mail change from new address (token link sent via e-mail) |
| GET | `/confirm-cancel-account` | Web\SettingsController | Confirm account cancellation (token link sent via e-mail) |
| GET | `/api/v1/me` | Api\V1\MeController | Current authenticated player info |

---

## Locale

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/change-locale/{locale}` | Web\LocaleController | Switch user locale (cs/en); persists to User entity if authenticated |

---

## Kingdom

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/api/v1/kingdoms` | Api\V1\KingdomController | List kingdoms |

---

## Team & Dashboard

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/dashboard` | Web\DashboardController | Team Dashboard (main game screen) |
| GET | `/app/chronicle` | Web\TeamChronicleController | Full team chronicle with category/type/period/sort filters |
| GET | `/api/v1/teams/{teamId}/dashboard` | Api\V1\TeamController | Aggregated dashboard data |
| GET | `/api/v1/teams/{teamId}/history` | Api\V1\TeamController | Last 30-day daily snapshots (morale, reputation, chemistry, fan_base) for team history chart |
| POST | `/api/v1/teams/{teamId}/settings` | Api\V1\TeamController | Update team name/emblem/colors |

---

## Hero

Training is not a hero-id POST. Live trainer assign/promote: [Training & Trainer Management](#training--trainer-management).

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/heroes` | Web\HeroController | Hero Roster page |
| GET | `/app/heroes/{id}` | Web\HeroController | Hero Detail page |
| GET | `/api/v1/heroes` | Api\V1\HeroController | Team list (no filter/sort query params; roster filter is client-side). Each hero includes `trait` (nullable string), `ratings.base_ovr` (0–100), and `ratings.complex_rating` (0–9999). |
| GET | `/api/v1/heroes/{id}` | Api\V1\HeroController | Hero full detail (`trait`, `ratings`, attributes, masteries, …) |
| PUT | `/api/v1/heroes/{id}` | Api\V1\HeroController | Update hero (rename) |
| POST | `/api/v1/heroes/{id}/dismiss` | Api\V1\HeroController | Dismiss hero for partial compensation (financial crisis recovery) |

---

## Training & Trainer Management

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/training` | Web\TrainingController | Training & Trainer Management page |
| GET | `/api/v1/training/trainers` | Api\V1\TrainingController | List team's trainers, their configured training focus, slots limit, assigned heroes, and lock status |
| POST | `/api/v1/training/trainers/promote` | Api\V1\TrainingController | Promote hero to trainer |
| POST | `/api/v1/training/trainers/{id}/configure` | Api\V1\TrainingController | Configure trainer focus (type, target attribute) |
| POST | `/api/v1/training/trainers/{id}/assign` | Api\V1\TrainingController | Assign hero to trainer |
| POST | `/api/v1/training/trainers/{id}/unassign` | Api\V1\TrainingController | Remove assignment of hero from trainer |
| POST | `/api/v1/training/trainers/{id}/dismiss` | Api\V1\TrainingController | Dismiss trainer for partial compensation (financial crisis recovery) |

---

## Formation

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/formation` | Web\FormationController | Formation Setup page (supports `?fixture_id=` for match prep) |
| GET | `/api/v1/formations` | Api\V1\FormationController | Get team saved formations (max 4) |
| PUT | `/api/v1/formations` | Api\V1\FormationController | Save/update saved formations (0–6 slots; duplicate `hero_id` → 422) |
| DELETE | `/api/v1/formations/{id}` | Api\V1\FormationController | Delete saved formation |
| GET | `/api/v1/fixtures/{id}/formation` | Api\V1\FixtureFormationController | Fixture formation assignment state |
| PUT | `/api/v1/fixtures/{id}/formation` | Api\V1\FixtureFormationController | Assign default / saved / custom lineup |
| POST | `/api/v1/fixtures/{id}/formation/promote` | Api\V1\FixtureFormationController | Promote temporary match lineup to saved |
| POST | `/api/v1/formations/simulate` | — | Simulation preview — **Cut** (no controller; [#9](../product/known-issues.md) / combat simulate lock) |

---

## Headquarters

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/hq` | Web\HeadquartersController | HQ hub (facility panels: training, medical, library, treasury, barracks, summoning chamber, arena) |
| GET | `/api/v1/hq` | Api\V1\HeadquartersController | Facility levels + live bonuses (`summon_stat_total_cap` omitted, A6.5) |
| POST | `/api/v1/hq/upgrade` | Api\V1\HeadquartersController | Upgrade facility |
| POST | `/api/v1/hq/downgrade` | Api\V1\HeadquartersController | Downgrade facility (financial crisis recovery) |
| POST | `/api/v1/hq/cancel-upgrade` | Api\V1\HeadquartersController | Cancel ongoing facility upgrade |
| POST | `/api/v1/hq/optimize` | Api\V1\HeadquartersController | Change arena adaptation |

> Legacy shortcuts `/app/arena` and `/app/summon` **redirect** into HQ (`?facility=arena` / `?facility=summoning_chamber`).

---

## Summoning

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/summon` | Web\SummoningController | **Redirect** → `/app/hq?facility=summoning_chamber` |
| GET | `/app/summon/history` | Web\SummoningController | **Redirect** → `/app/hq?facility=summoning_chamber&subtab=history` |
| GET | `/api/v1/summoning/status` | Api\V1\SummoningController | Cooldown/availability |
| POST | `/api/v1/summoning` | Api\V1\SummoningController | Summon new hero (response: full hero payload including nullable `trait`) |

---

## Item / Equipment

Equipment UI is the hero-detail tab, not a standalone inventory page.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/inventory` | Web\ItemController | **Redirect** — `?hero_id=` → `/app/heroes/{id}?tab=equipment`; otherwise `/app/heroes` |
| GET | `/api/v1/items` | Api\V1\ItemController | Inventory list |
| PUT | `/api/v1/heroes/{heroId}/equipment` | Api\V1\ItemController | Equip/unequip |
| POST | `/api/v1/items/dismantle` | Api\V1\ItemController | Dismantle for gold scrap |
| POST | `/api/v1/items/{id}/repair` | Api\V1\ItemController | Repair durability |

---

## Spell

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/spells` | Web\SpellController | **Redirect** — `?hero_id=` → `/app/heroes/{id}?tab=spells`; otherwise `/app/heroes` |
| GET | `/app/academy` | Web\SpellController | Academy Store page (library T1–T5, school/tier filters) |
| GET | `/api/v1/spells` | Api\V1\SpellController | Spell library (`school`, `tier` query) |
| GET | `/api/v1/heroes/{heroId}/spells` | Api\V1\SpellController | Hero's known spells |
| POST | `/api/v1/heroes/{heroId}/spells/learn` | Api\V1\SpellController | Learn spell |
| POST | `/api/v1/heroes/{heroId}/spells/equip` | Api\V1\SpellController | Equip to slot |
| POST | `/api/v1/heroes/{heroId}/spells/unequip` | Api\V1\SpellController | Unequip from slot |

---

## Combat

Implemented: `Web\BattleController` (`/app/battles/{id}`) and `Api\V1\BattleController` (`/api/v1/battles/{id}`, `/api/v1/battles/{id}/log`). Sandbox `POST /api/v1/combat/simulate` is **Cut** (no controller; [#9](../product/known-issues.md) Resolved).

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/battles/{id}` | Web\BattleController | Match report: final formations and round-by-round logs |
| GET | `/api/v1/battles/{id}` | Api\V1\BattleController | Battle result metadata (scores, teams, status, result) |
| GET | `/api/v1/battles/{id}/log` | Api\V1\BattleController | Detailed combat log / replay (JSON) |
| POST | `/api/v1/combat/simulate` | — | Sandbox combat simulation — **Cut** (no controller; A7.5 / [#9](../product/known-issues.md)) |

---

## League

Both the Web dashboard (`/app/league`) and the REST API (`/api/v1/league/*`) are implemented.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/league` | Web\LeagueController | League page showing group standings, fixtures, and global leaderboard |
| GET | `/api/v1/league/standings` | Api\V1\LeagueController | Current group standings and kingdom-wide leaderboard |
| GET | `/api/v1/league/fixtures` | Api\V1\LeagueController | Fixture schedule for group or team |
| GET | `/api/v1/league/seasons` | Api\V1\LeagueController | Kingdom season history |
| POST | `/api/v1/league/process-season` | Api\V1\LeagueController | Season transition trigger (Admin only) |

---

## Economy & Marketplace

Player-facing economy is split across **two Web screens** (sidebar links):

| Screen | Route | Purpose |
|--------|-------|---------|
| **Marketplace** | `/app/marketplace` | Browse, sell, my listings, transaction history, basic equipment merchant — [15-marketplace.md](../screens/15-marketplace.md) |
| **Finance ledger** | `/app/finance` | Full financial audit log with filters — [20-finance.md](../screens/20-finance.md) |

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/marketplace` | Web\MarketplaceController | Marketplace page (tabs: `browse`, `sell`, `mylistings`, `history`, `basic_equipment`) |
| POST | `/app/marketplace/buy-basic` | Web\MarketplaceController | Purchase fixed-price basic equipment (`item_key`, CSRF `buy_basic`); redirects to `basic_equipment` tab |
| GET | `/app/finance` | Web\FinanceController | Financial ledger (filters: type, actor, sort; summary via `FinanceSummaryService`) |
| GET | `/api/v1/finance/status` | Api\V1\FinanceController | Financial crisis status |
| GET | `/api/v1/finance/recent` | Api\V1\FinanceController | Recent financial ledger entries |
| GET | `/api/v1/marketplace` | Api\V1\MarketplaceController | Search listings. Query: `type`, `race`, `level_min`/`level_max`, `rating_min`/`rating_max`, `base_ovr_min`/`base_ovr_max`, `age_phase`, `price_min`/`price_max`, `seller_reputation_min`, `rarity`, `search`, `sort`. Hero entities include `trait` (nullable), `ratings`; `seller_team` includes `reputation`. |
| POST | `/api/v1/marketplace/listings` | Api\V1\MarketplaceController | Create listing. Price / start / buyout must be in the suggested band (−50% / +100%; [#15](../product/known-issues.md)). |
| DELETE | `/api/v1/marketplace/listings/{id}` | Api\V1\MarketplaceController | Cancel listing |
| POST | `/api/v1/marketplace/purchase` | Api\V1\MarketplaceController | Buy listing |
| POST | `/api/v1/marketplace/bid` | Api\V1\MarketplaceController | Place auction bid |
| GET | `/api/v1/marketplace/my-listings` | Api\V1\MarketplaceController | Own active listings |
| GET | `/api/v1/marketplace/history` | Api\V1\MarketplaceController | Transaction history |

---

## Calendar

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/calendar` | Web\CalendarController | Calendar page — kingdom schedule feed with filters |
| GET | `/api/v1/kingdom/{id}/calendar` | Api\V1\CalendarController | Full calendar feed for Kingdom (ticks, fixtures, training) |

---

## Community

The community hub web page and messaging/forum API are implemented. Kingdom-wide leaderboards are on the League screen (`/app/league` tab "Kingdom Leaderboard") via `GET /api/v1/league/standings`. There is no `GET /api/v1/leaderboards` route.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/teams/{id}` | Web\CommunityController | Public team card (same kingdom) |
| GET | `/app/community` | Web\CommunityController | Kingdom discussion board (thread list) |
| GET | `/app/community/threads/{id}` | Web\CommunityController | Thread detail with replies |
| GET | `/api/v1/players/{id}/profile` | Api\V1\CommunityController | Public player profile |
| GET | `/api/v1/teams/{id}/profile` | Api\V1\CommunityController | Public team profile |
| GET | `/api/v1/messages` | Api\V1\MessageController | Inbox |
| POST | `/api/v1/messages` | Api\V1\MessageController | Send message |
| GET | `/api/v1/messages/unread-count` | Api\V1\MessageController | Unread message count |
| GET | `/api/v1/messages/recipients` | Api\V1\MessageController | Valid message recipient teams |
| GET | `/api/v1/messages/{id}` | Api\V1\MessageController | Read message |
| DELETE | `/api/v1/messages/{id}` | Api\V1\MessageController | Delete message |
| GET | `/api/v1/forum/threads` | Api\V1\ForumController | Thread list |
| POST | `/api/v1/forum/threads` | Api\V1\ForumController | Create thread |
| GET | `/api/v1/forum/threads/{id}` | Api\V1\ForumController | Thread + posts |
| POST | `/api/v1/forum/threads/{id}/posts` | Api\V1\ForumController | Reply |
| POST | `/api/v1/forum/threads/{id}/lock` | Api\V1\ForumController | Lock/unlock thread (author only) |

There is **no** pin/unpin route. `ForumThread.is_pinned` is stored and shown if true, but **unsettable** in the app (A6.3 Cut).

---

## Graveyard

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/graveyard` | Web\GraveyardController | Graveyard memorial page (supports `?id=` for memorial detail modal view) |
| GET | `/api/v1/graveyard` | Api\V1\GraveyardController | List memorial records (filterable by role, cause, race, search) |
| GET | `/api/v1/graveyard/{id}` | Api\V1\GraveyardController | Memorial detail |

---

## Arena

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/arena` | Web\ArenaController | **Redirect** → `/app/hq?facility=arena` |
| GET | `/api/v1/arena` | Api\V1\ArenaController | Arena status (read-only; includes last 18 `arena_revenue` rows + analytics summary) |
| POST | `/api/v1/hq/arena/tickets/price` | Api\V1\ArenaController | Update team ticket price (1–50 gold) with demand elasticity |
| POST | `/api/v1/arena/tickets/price` | Api\V1\ArenaController | Alias of `/api/v1/hq/arena/tickets/price` |
| GET | `/app/friendly-matches` | Web\FriendlyMatchController | Kingdom-wide friendly match list + challenge UI |
| GET | `/api/v1/arena/friendly` | Api\V1\ArenaController | List kingdom friendly matches |
| GET | `/api/v1/arena/friendly/slots` | Api\V1\ArenaController | Available friendly kickoff slots vs opponent |
| POST | `/api/v1/arena/schedule-match` | Api\V1\ArenaController | Create friendly match invite |
| POST | `/api/v1/arena/friendly/{id}/accept` | Api\V1\ArenaController | Accept friendly invite |
| POST | `/api/v1/arena/friendly/{id}/decline` | Api\V1\ArenaController | Decline friendly invite |
| POST | `/api/v1/arena/friendly/{id}/cancel` | Api\V1\ArenaController | Cancel friendly (until T−1h) |
| GET | `/api/v1/friendly-matches/{id}/formation` | Api\V1\FriendlyMatchFormationController | Match formation assignment |
| PUT | `/api/v1/friendly-matches/{id}/formation` | Api\V1\FriendlyMatchFormationController | Save match formation assignment |

> Arena facility upgrades use `/app/hq`. Match-day ticket revenue is paid to the **home team** on the League Match tick via `ArenaRevenueService::processLeagueMatchTick()`. Friendlies: [friendly-match-system.md](../systems/friendly-match-system.md).

---

## Player Profile & Settings

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/settings` | Web\SettingsController | **Redirect** → `/app/dashboard` (settings UI is the account settings modal) |
| POST | `/app/settings/preferences` | Web\SettingsController | Update player UI preferences (`closeModalOnBackdrop`, …) |
| POST | `/app/settings/change-email` | Web\SettingsController | Change email (initiates token flow via `confirm-email-change/*`) |
| POST | `/app/settings/cancel-account` | Web\SettingsController | Cancel account (initiates token flow via `confirm-cancel-account`) |

> Account settings are Web/session routes on `Web\SettingsController` (JSON responses for the modal). There is no separate `Api\SettingsController`. Session password change / extra settings stay **Planned** (A8.6 keep Planned). Lock: [19-player-profile-settings.md](../screens/19-player-profile-settings.md#locked-password-change--extra-settings-stay-planned).

---

## Notifications

Query contract — `Api\V1\NotificationController::list()` is source of truth (see [notification-system.md](../systems/notification-system.md)):

| Query | Live rule |
|-------|-----------|
| `limit` | Default **10**, clamped to **1–100** |
| `page` | Default **1**, minimum **1** |
| `unread_only` | Optional boolean |

List JSON: `{ items, page, total_pages, total_items }`. Navbar requests `?limit=10&page=`. `read-all` has no 100-item cap.

Title/body in JSON are **read-time** translations of `title_key` / `body_key` (A6.4; same pattern as chronicle). Pre-A6.4 rows without keys keep frozen strings. Unused types (`league_update`, `event_started`, `spell_learned`): **reserved / unused (A6.2)** — no writers; do not hook them for completeness.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/api/v1/notifications` | Api\V1\NotificationController | List (`?unread_only=`, `?limit=` default 10 max 100, `?page=` default 1) |
| GET | `/api/v1/notifications/unread-count` | Api\V1\NotificationController | Unread count for navbar badge |
| PUT | `/api/v1/notifications/read-all` | Api\V1\NotificationController | Mark all as read (no 100-cap) |
| GET | `/api/v1/notifications/{id}` | Api\V1\NotificationController | Notification detail (marks read) |
| PUT | `/api/v1/notifications/{id}/read` | Api\V1\NotificationController | Mark one as read |

In-game UI: navbar dropdown → **Notifications** modal (`notifications_controller.js`).

---

## Infrastructure

Unauthenticated **liveness** only. JSON `{ "status": "ok" }`. No database ping, no env/version/DSN. Dedicated firewall (`security: false`) so probes do not start a session. Compose `web` healthchecks call this path.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/health` | HealthCheckController | Liveness JSON `{ "status": "ok" }` (A8.3) |

---

## Deferred (not in this list)

Dungeon, crafting, and quest HTTP APIs are **not** live and have no controllers. Design only: [future/dungeon-system.md](../future/dungeon-system.md), [future/crafting-system.md](../future/crafting-system.md), [future/quest-system.md](../future/quest-system.md). Do not add those paths to the tables above.

---

## Summary

| | Web | API | Infra | Total |
|--|-----|-----|-------|-------|
| Routes (implemented) | 41 | 84 | 1 | **126** |
| Routes (planned) | 1 | 2 | 0 | 3 |
| Controllers (Web, implemented) | 25 | — | — | — |
| Controllers (API, implemented) | — | 23 | — | — |
| Controllers (infra, implemented) | — | — | 1 | — |

Implemented counts are method-level `#[Route]` attributes in `src/Controller/` as of this alignment. Redirects (`/app/inventory`, `/app/spells`, `/app/arena`, `/app/summon`, `/app/settings`, …) are included because they exist in code. Infra is `GET /health` (`HealthCheckController`, not under `Web/` or `Api/V1`).

**Planned** (no controller, later product ask): `GET /wiki` (A8.5). **Cut** (no controller, do not ship): `POST /api/v1/combat/simulate`, `POST /api/v1/formations/simulate`.
