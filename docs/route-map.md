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
| GET | `/wiki` | Web\WikiController | Help/wiki section |

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
| GET, POST | `/password-reset` | Web\PasswordResetController | Request password reset email |
| GET, POST | `/password-reset/confirm` | Web\PasswordResetController | Validate token + submit new password |

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
| POST | `/api/v1/heroes/{id}/train` | Api\V1\HeroController | Trigger training |
| POST | `/api/v1/heroes/{id}/learn-spell` | Api\V1\HeroController | Learn spell |
| POST | `/api/v1/heroes/{id}/convert-trainer` | Api\V1\HeroController | Convert to trainer |

---

## Training

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/training` | Web\TrainingController | Training page |
| GET | `/api/v1/training` | Api\V1\TrainingController | Training options (costs, rates) |
| GET | `/api/v1/training-queue` | Api\V1\TrainingController | Queued training jobs |
| POST | `/api/v1/training-queue` | Api\V1\TrainingController | Add to queue |
| DELETE | `/api/v1/training-queue/{id}` | Api\V1\TrainingController | Cancel training |

---

## Trainer

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/trainers` | Web\TrainerController | Trainer Management page |
| GET | `/api/v1/trainers` | Api\V1\TrainerController | List trainers |
| POST | `/api/v1/trainers/{id}/assign` | Api\V1\TrainerController | Assign to hero |
| POST | `/api/v1/trainers/{id}/unassign` | Api\V1\TrainerController | Remove assignment |

---

## Formation

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/formation` | Web\FormationController | Formation Setup page |
| GET | `/api/v1/formations` | Api\V1\FormationController | Get team formations |
| PUT | `/api/v1/formations` | Api\V1\FormationController | Save/update formations |
| DELETE | `/api/v1/formations/{id}` | Api\V1\FormationController | Delete formation |
| POST | `/api/v1/formations/simulate` | Api\V1\FormationController | Simulation preview |

---

## Headquarters

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/hq` | Web\HeadquartersController | HQ page |
| GET | `/api/v1/hq` | Api\V1\HeadquartersController | Facility levels + bonuses |
| POST | `/api/v1/hq/upgrade` | Api\V1\HeadquartersController | Upgrade facility |
| POST | `/api/v1/hq/race-optimization` | Api\V1\HeadquartersController | Change race optimization |

---

## Summoning

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/summon` | Web\SummoningController | Summoning Chamber page |
| GET | `/api/v1/summoning/status` | Api\V1\SummoningController | Cooldown/availability |
| POST | `/api/v1/summoning` | Api\V1\SummoningController | Summon new hero |

---

## Item / Equipment

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/inventory` | Web\ItemController | Equipment page |
| GET | `/api/v1/items` | Api\V1\ItemController | Inventory list |
| PUT | `/api/v1/heroes/{id}/equipment` | Api\V1\ItemController | Equip/unequip |
| POST | `/api/v1/items/dismantle` | Api\V1\ItemController | Dismantle for essence |
| POST | `/api/v1/items/{id}/repair` | Api\V1\ItemController | Repair durability |

---

## Spell

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/spells` | Web\SpellController | Spell Management page |
| GET | `/api/v1/spells` | Api\V1\SpellController | Spell library |
| GET | `/api/v1/heroes/{id}/spells` | Api\V1\SpellController | Hero's known spells |
| POST | `/api/v1/heroes/{id}/spells/equip` | Api\V1\SpellController | Equip to slot |
| POST | `/api/v1/heroes/{id}/spells/unequip` | Api\V1\SpellController | Unequip from slot |

---

## Combat

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/battles/{id}` | Web\CombatController | Battle viewer page |
| GET | `/api/v1/battles/{id}` | Api\CombatController | Battle result |
| GET | `/api/v1/battles/{id}/log` | Api\CombatController | Combat log/replay |
| POST | `/api/v1/combat/simulate` | Api\CombatController | Combat simulation |

---

## League

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/league` | Web\LeagueController | League page |
| GET | `/api/v1/league/standings` | Api\LeagueController | Current standings |
| GET | `/api/v1/league/fixtures` | Api\LeagueController | Fixture schedule |
| GET | `/api/v1/league/seasons` | Api\LeagueController | Season history |
| POST | `/api/v1/league/rewards/claim` | Api\LeagueController | Claim rewards |

---

## Marketplace

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/marketplace` | Web\MarketplaceController | Marketplace page |
| GET | `/api/v1/marketplace` | Api\MarketplaceController | Search listings |
| POST | `/api/v1/marketplace/listings` | Api\MarketplaceController | Create listing |
| DELETE | `/api/v1/marketplace/listings/{id}` | Api\MarketplaceController | Cancel listing |
| POST | `/api/v1/marketplace/purchase` | Api\MarketplaceController | Buy listing |
| POST | `/api/v1/marketplace/bid` | Api\MarketplaceController | Place auction bid |
| GET | `/api/v1/marketplace/my-listings` | Api\MarketplaceController | Own active listings |
| GET | `/api/v1/marketplace/history` | Api\MarketplaceController | Transaction history |

---

## Event / Calendar

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/calendar` | Web\EventController | Calendar page |
| GET | `/api/v1/events` | Api\EventController | Active/upcoming events |
| GET | `/api/v1/events/calendar` | Api\EventController | Full calendar feed |
| GET | `/api/v1/kingdom/{id}/calendar` | Api\V1\CalendarController | Full calendar feed for Kingdom |
| POST | `/api/v1/events/{id}/participate` | Api\EventController | Join event |

---

## Dungeon (Future Feature - Planned)

> [!NOTE]
> These routes are planned for Fáze 7 and are not currently implemented.

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/dungeons` | Web\DungeonController | Dungeon selection page |
| POST | `/api/v1/dungeons/enter` | Api\DungeonController | Start dungeon run |
| GET | `/api/v1/dungeons/{runId}/result` | Api\DungeonController | Run result + rewards |

---

## Quest

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/quests` | Web\QuestController | Quest page |
| GET | `/api/v1/quests` | Api\QuestController | Available quests |
| POST | `/api/v1/quests/{id}/accept` | Api\QuestController | Accept quest |
| POST | `/api/v1/quests/{id}/claim` | Api\QuestController | Claim rewards |

---

## Crafting

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/crafting` | Web\CraftingController | Crafting page |
| GET | `/api/v1/crafting/recipes` | Api\CraftingController | Recipe list |
| POST | `/api/v1/crafting` | Api\CraftingController | Start crafting |
| GET | `/api/v1/crafting/queue` | Api\CraftingController | Active jobs |
| DELETE | `/api/v1/crafting/queue/{id}` | Api\CraftingController | Cancel job |

---

## Community

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/community` | Web\CommunityController | Community hub page |
| GET | `/api/v1/leaderboards` | Api\CommunityController | Leaderboard rankings |
| GET | `/api/v1/players/{id}/profile` | Api\CommunityController | Public player profile |
| GET | `/api/v1/messages` | Api\MessageController | Inbox |
| POST | `/api/v1/messages` | Api\MessageController | Send message |
| GET | `/api/v1/messages/{id}` | Api\MessageController | Read message |
| DELETE | `/api/v1/messages/{id}` | Api\MessageController | Delete message |
| GET | `/api/v1/forum/threads` | Api\ForumController | Thread list |
| POST | `/api/v1/forum/threads` | Api\ForumController | Create thread |
| GET | `/api/v1/forum/threads/{id}` | Api\ForumController | Thread + posts |
| POST | `/api/v1/forum/threads/{id}/posts` | Api\ForumController | Reply |

---

## Graveyard

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/graveyard` | Web\GraveyardController | Graveyard memorial page |
| GET | `/api/v1/graveyard` | Api\GraveyardController | Fallen heroes list |
| GET | `/api/v1/graveyard/{id}` | Api\GraveyardController | Fallen hero detail |

---

## Arena

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/arena` | Web\ArenaController | Arena Management page |
| GET | `/api/v1/arena` | Api\ArenaController | Arena status/revenue |
| POST | `/api/v1/arena/upgrade` | Api\ArenaController | Upgrade arena |
| PUT | `/api/v1/arena/ticket-price` | Api\ArenaController | Set ticket price |
| POST | `/api/v1/arena/schedule-match` | Api\ArenaController | Schedule friendly match |

---

## Player Profile & Settings

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/app/settings` | Web\SettingsController | Profile & Settings page |
| GET | `/api/v1/settings` | Api\SettingsController | Get settings (TBD) |
| PUT | `/api/v1/settings` | Api\SettingsController | Update settings (TBD) |
| POST | `/app/settings/change-email` | Web\SettingsController | Change email |
| POST | `/api/v1/settings/change-password` | Api\SettingsController | Change password (TBD) |
| POST | `/app/settings/cancel-account` | Web\SettingsController | Cancel account (formerly delete-account) |

---

## Notifications

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/api/v1/notifications` | Api\NotificationController | Get notifications |
| PUT | `/api/v1/notifications/{id}/read` | Api\NotificationController | Mark as read |

---

## Infrastructure

| Method | Path | Controller | Purpose |
|--------|------|-----------|---------|
| GET | `/health` | Web\HealthController | Health check |

---

## Summary

| | Web | API | Total |
|--|-----|-----|-------|
| Routes | 27 | 89 | **116** |
| Controllers (Web) | ~20 | — | — |
| Controllers (API) | — | ~22 | — |
