# Route Map

Purpose: Complete reference of all planned routes (Web + API). Derived from screen and system documentation.

Reference: [api-design.md](api-design.md), [screens-overview.md](screens-overview.md), [auth-system.md](systems/auth-system.md)

---

## Conventions

- **Web routes** (e.g. `/`, `/app/heroes`, `/app/league`...): Twig-rendered pages, handled by `App\Controller\Web\*`. Authenticated routes are prefixed with `/app`.
- **API routes** (`/api/v1/*`): JSON responses, handled by `App\Controller\Api\V1\*`
- All authenticated routes require `ROLE_PLAYER` unless noted
- API follows REST principles defined in [api-design.md](api-design.md)

---

## Public (unauthenticated)

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/` | Web\DefaultController | Homepage |
| GET | `/news` | Web\NewsController | News archive (each item displayed in full; no detail page) |
| GET | `/wiki` | Web\WikiController | Help/wiki section — **planned** |

---

## Auth

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/login` | Web\AuthController | Render login form |
| GET, POST | `/register` | Web\RegisterController | Show/submit registration form (kingdom selection included) |
| GET | `/register/success` | Web\RegisterController | Post-registration landing (check your email) |
| POST | `/register/resend-verification` | Web\RegisterController | Resend verification email (rate-limited) |
| GET | `/verify-email` | Web\RegisterController | Verify email token, activate account, assign NPC team, auto-login |
| POST | `/login` | (Symfony form_login) | Authenticate session |
| GET | `/logout` | (Symfony logout) | Destroy session |
| GET | `/password-reset` | Web\PasswordResetController | Request password reset email |
| POST | `/password-reset` | Web\PasswordResetController | Request password reset email |
| GET | `/password-reset/confirm` | Web\PasswordResetController | Validate token + submit new password |
| POST | `/password-reset/confirm` | Web\PasswordResetController | Validate token + submit new password |
| GET | `/confirm-email-change/old` | Web\SettingsController | Confirm e-mail change from old address (token link sent via e-mail) |
| GET | `/confirm-email-change/new` | Web\SettingsController | Confirm e-mail change from new address (token link sent via e-mail) |
| GET | `/confirm-cancel-account` | Web\SettingsController | Confirm account cancellation (token link sent via e-mail) |

---

## Locale

| Method | Path | Controller | Purpose |
|--------|------|-----------|------|
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
| GET | `/app/chronicle` | Web\TeamChronicleController | Full team chronicle with category/type/sort filters |
| GET | `/api/v1/teams/{id}/dashboard` | Api\V1\TeamController | Aggregated dashboard data |
| POST | `/api/v1/teams/{id}/settings` | Api\V1\TeamController | Update team name/emblem/colors |

---

## Hero

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/heroes` | Web\HeroController | Hero Roster page |
| GET | `/app/heroes/{id}` | Web\HeroController | Hero Detail page |
| GET | `/api/v1/heroes` | Api\V1\HeroController | List heroes (filterable) |
| GET | `/api/v1/heroes/{id}` | Api\V1\HeroController | Hero full detail |
| PUT | `/api/v1/heroes/{id}` | Api\V1\HeroController | Update hero (rename) |
| POST | `/api/v1/heroes/{id}/dismiss` | Api\V1\HeroController | Dismiss hero for partial compensation (financial crisis recovery) |
| POST | `/api/v1/heroes/{id}/train` | Api\V1\HeroController | Trigger training — **planned** |
| POST | `/api/v1/heroes/{id}/convert-trainer` | Api\V1\HeroController | Convert hero to trainer — **planned** (trainers are created separately today) |

---

## Training & Trainer Management

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/training` | Web\TrainingController | Training & Trainer Management page |
| GET | `/api/v1/training/trainers` | Api\V1\TrainingController | List team's trainers, their configured training focus, slots limit, assigned heroes, and lock status |
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
| PUT | `/api/v1/formations` | Api\V1\FormationController | Save/update saved formations |
| DELETE | `/api/v1/formations/{id}` | Api\V1\FormationController | Delete saved formation |
| GET | `/api/v1/fixtures/{id}/formation` | Api\V1\FixtureFormationController | Fixture formation assignment state |
| PUT | `/api/v1/fixtures/{id}/formation` | Api\V1\FixtureFormationController | Assign default / saved / custom lineup |
| POST | `/api/v1/fixtures/{id}/formation/promote` | Api\V1\FixtureFormationController | Promote temporary match lineup to saved |
| POST | `/api/v1/formations/simulate` | Api\V1\FormationController | Simulation preview — **planned** |

---

## Headquarters

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/hq` | Web\HeadquartersController | HQ hub (facility panels: training, medical, library, treasury, barracks, summoning chamber, arena) |
| GET | `/api/v1/hq` | Api\V1\HeadquartersController | Facility levels + bonuses |
| POST | `/api/v1/hq/upgrade` | Api\V1\HeadquartersController | Upgrade facility |
| POST | `/api/v1/hq/downgrade` | Api\V1\HeadquartersController | Downgrade facility (financial crisis recovery) |
| POST | `/api/v1/hq/cancel-upgrade` | Api\V1\HeadquartersController | Cancel ongoing facility upgrade |
| POST | `/api/v1/hq/optimize` | Api\V1\HeadquartersController | Change arena adaptation |

> Legacy shortcuts `/app/arena` and `/app/summon` redirect into HQ (`?facility=arena` / `?facility=summoning_chamber`).

---

## Summoning

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/summon` | Web\SummoningController | Redirect → `/app/hq?facility=summoning_chamber` |
| GET | `/app/summon/history` | Web\SummoningController | Redirect → `/app/hq?facility=summoning_chamber&subtab=history` |
| GET | `/api/v1/summoning/status` | Api\V1\SummoningController | Cooldown/availability |
| POST | `/api/v1/summoning` | Api\V1\SummoningController | Summon new hero |

---

## Item / Equipment

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/inventory` | Web\ItemController | Equipment page |
| GET | `/api/v1/items` | Api\V1\ItemController | Inventory list |
| PUT | `/api/v1/heroes/{heroId}/equipment` | Api\V1\ItemController | Equip/unequip |
| POST | `/api/v1/items/dismantle` | Api\V1\ItemController | Dismantle for essence |
| POST | `/api/v1/items/{id}/repair` | Api\V1\ItemController | Repair durability |

---

## Spell

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/spells` | Web\SpellController | Spell Management page |
| GET | `/api/v1/spells` | Api\V1\SpellController | Spell library |
| GET | `/api/v1/heroes/{heroId}/spells` | Api\V1\SpellController | Hero's known spells |
| POST | `/api/v1/heroes/{heroId}/spells/learn` | Api\V1\SpellController | Learn spell |
| POST | `/api/v1/heroes/{heroId}/spells/equip` | Api\V1\SpellController | Equip to slot |
| POST | `/api/v1/heroes/{heroId}/spells/unequip` | Api\V1\SpellController | Unequip from slot |

---

## Combat

> [!NOTE]
> Not yet implemented — planned for Phase 5.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/battles/{id}` | Web\CombatController | Battle viewer page |
| GET | `/api/v1/battles/{id}` | Api\V1\CombatController | Battle result |
| GET | `/api/v1/battles/{id}/log` | Api\V1\CombatController | Combat log/replay |
| POST | `/api/v1/combat/simulate` | Api\V1\CombatController | Combat simulation |

---

## League

> [!NOTE]
> The Web dashboard (`/app/league`) is fully complete. The `/api/v1/league/*` API endpoints are planned/deferred as the Web dashboard renders all standings and fixtures server-side via Twig, and match combat simulation is currently a stub.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/league` | Web\LeagueController | League page showing group standings, fixtures, and global leaderboard |
| GET | `/api/v1/league/standings` | Api\V1\LeagueController | Current standings (planned) |
| GET | `/api/v1/league/fixtures` | Api\V1\LeagueController | Fixture schedule (planned) |
| GET | `/api/v1/league/seasons` | Api\V1\LeagueController | Season history (planned) |
| POST | `/api/v1/league/rewards/claim` | Api\V1\LeagueController | Claim rewards (planned) |

---

## Economy & Marketplace

The player-facing **Economy hub** combines marketplace browsing, selling, transaction history, and the financial ledger in one page.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/economy` | Web\EconomyController | Economy hub (tabs: `browse`, `sell`, `mylistings`, `history`, `ledger`) |
| GET | `/app/marketplace` | Web\MarketplaceController | Redirect → `/app/economy?tab=browse` (legacy alias) |
| GET | `/app/finance` | Web\FinanceController | Redirect → `/app/economy?tab=ledger` (legacy alias) |
| GET | `/api/v1/finance/status` | Api\V1\FinanceController | Financial crisis status |
| GET | `/api/v1/finance/recent` | Api\V1\FinanceController | Recent financial ledger entries |
| GET | `/api/v1/marketplace` | Api\V1\MarketplaceController | Search listings |
| POST | `/api/v1/marketplace/listings` | Api\V1\MarketplaceController | Create listing |
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

## Dungeon (Deferred — design only)

> Design preserved in [future/dungeon-system.md](future/dungeon-system.md). No routes or controllers in the codebase.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/dungeons` | Web\DungeonController | Dungeon selection page |
| POST | `/api/v1/dungeons/enter` | Api\V1\DungeonController | Start dungeon run |
| GET | `/api/v1/dungeons/{runId}/result` | Api\V1\DungeonController | Run result + rewards |

---

## Crafting (Deferred — design only)

> Design preserved in [future/crafting-system.md](future/crafting-system.md). No routes or controllers in the codebase.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/crafting` | Web\CraftingController | Crafting page |
| GET | `/api/v1/crafting/recipes` | Api\V1\CraftingController | Recipe list |
| POST | `/api/v1/crafting` | Api\V1\CraftingController | Start crafting |
| GET | `/api/v1/crafting/queue` | Api\V1\CraftingController | Active jobs |
| DELETE | `/api/v1/crafting/queue/{id}` | Api\V1\CraftingController | Cancel job |

---

## Quests (Deferred — design only)

> Design preserved in [future/quest-system.md](future/quest-system.md). No routes or controllers in the codebase.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/api/v1/quests` | Api\V1\QuestController | Available and active quests |
| POST | `/api/v1/quests/{id}/accept` | Api\V1\QuestController | Accept a quest |
| POST | `/api/v1/quests/{id}/claim` | Api\V1\QuestController | Claim quest rewards |


---

## Community

> [!NOTE]
> The community hub web page and messaging/forum API endpoints are implemented. Kingdom-wide leaderboards are available via the League screen (`/app/league` tab "Kingdom Leaderboard"); dedicated leaderboard API is not required.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/community` | Web\CommunityController | Kingdom discussion board (thread list) |
| GET | `/app/community/threads/{id}` | Web\CommunityController | Thread detail with replies |
| GET | `/api/v1/leaderboards` | Api\V1\CommunityController | Leaderboard rankings — **not needed** (see League UI) |
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

---

## Graveyard

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/graveyard` | Web\GraveyardController | Graveyard memorial page |
| GET | `/api/v1/graveyard` | Api\V1\GraveyardController | List memorial records (filterable by role, cause, race, search) |
| GET | `/api/v1/graveyard/{id}` | Api\V1\GraveyardController | Memorial detail |

---

## Arena

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/arena` | Web\ArenaController | Redirect → `/app/hq?facility=arena` (legacy alias; panel shows capacity, fan appeal, revenue projection) |
| GET | `/api/v1/arena` | Api\V1\ArenaController | Arena status (read-only) |
| POST | `/api/v1/arena/schedule-match` | Api\V1\ArenaController | Schedule friendly match — **planned** (requires combat engine) |

> Arena facility upgrades use `/app/hq`. Match-day ticket revenue is paid to the **home team** on the League Match tick via `ArenaRevenueService::processLeagueMatchTick()`.

---

## Player Profile & Settings

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/settings` | Web\SettingsController | Redirects to dashboard (settings UI is the account settings modal) |
| POST | `/app/settings/preferences` | Web\SettingsController | Update player UI preferences (`closeModalOnBackdrop`, …) |
| GET | `/api/v1/settings` | Api\SettingsController | Get settings — **planned** |
| PUT | `/api/v1/settings` | Api\SettingsController | Update settings — **planned** |
| POST | `/app/settings/change-email` | Web\SettingsController | Change email (initiates token flow via `confirm-email-change/*`) |
| POST | `/api/v1/settings/change-password` | Api\SettingsController | Change password — **planned** |
| POST | `/app/settings/cancel-account` | Web\SettingsController | Cancel account (initiates token flow via `confirm-cancel-account`) |

---

## Notifications

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/api/v1/notifications` | Api\V1\NotificationController | List notifications (`?unread_only=1`, `?limit=50`) |
| GET | `/api/v1/notifications/unread-count` | Api\V1\NotificationController | Unread count for navbar badge |
| PUT | `/api/v1/notifications/read-all` | Api\V1\NotificationController | Mark all as read |
| GET | `/api/v1/notifications/{id}` | Api\V1\NotificationController | Notification detail (marks read) |
| PUT | `/api/v1/notifications/{id}/read` | Api\V1\NotificationController | Mark one as read |

In-game UI: navbar dropdown → **Notifications** modal (`notifications_controller.js`). See [notification-system.md](systems/notification-system.md).

---

## Infrastructure

> [!NOTE]
> Not yet implemented.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/health` | Web\HealthController | Health check — **planned** |

---

## Summary

| | Web | API | Total |
|--|-----|-----|-------|
| Routes (implemented) | 26 | 65 | **91** |
| Routes (planned) | 7 | 32+ | — |
| Controllers (Web, implemented) | 24 | — | — |
| Controllers (API, implemented) | — | 18 | — |

> Routes marked **planned** have no controller implementation yet. Route counts reflect the state of the codebase; the original total of 116 includes all planned future routes.
