# Screen → Code Map (Fantager)

Quick reference: which files implement each game screen.  
**Specs:** `docs/screens/*.md` · **Routes:** [route-map.md](route-map.md) · **Agents:** [AGENTS.md](../AGENTS.md) task routing

---

## How to use

1. Open the screen doc in `docs/screens/`.
2. Read the matching `docs/systems/*.md` domain doc.
3. Edit the files in the table below — do not invent parallel patterns.
4. After route changes, update [route-map.md](route-map.md).

---

## Public & auth

| Screen doc | Web route(s) | Web controller | API | Twig | Stimulus |
|------------|--------------|----------------|-----|------|----------|
| [00-public-pages.md](screens/00-public-pages.md) | `/`, `/news` | `Web\DefaultController`, `Web\NewsController` | — | `templates/home/`, `templates/news/` | `auth_modal` (in layout) |
| [00-auth-screens.md](screens/00-auth-screens.md) | `/login`, `/register`, `/password-reset/*` | `Web\AuthController`, `Web\RegisterController`, `Web\PasswordResetController` | — | `templates/auth/`, `templates/components/auth/` | `auth_modal`, `modal` |
| [01-kingdom-selection.md](screens/01-kingdom-selection.md) | `/register` (kingdom step) | `Web\RegisterController` | `GET /api/v1/kingdoms` → `Api\V1\KingdomController` | `templates/components/auth/register_panel.html.twig` | `auth_modal` |

**Locale:** `GET /change-locale/{locale}` → `Web\LocaleController` (layout switcher).

---

## Core game screens (`/app/*`)

| Screen doc | Web route(s) | Web controller | Primary API controller(s) | Twig | Stimulus |
|------------|--------------|----------------|---------------------------|------|----------|
| [02-team-dashboard.md](screens/02-team-dashboard.md) | `/app/dashboard` | `Web\DashboardController` | `Api\V1\TeamController` (`/dashboard`, `/settings`) | `templates/dashboard/` | `dashboard_banner`, `modal` (team profile) |
| [02a-team-chronicle.md](screens/02a-team-chronicle.md) | `/app/chronicle` | `Web\TeamChronicleController` | — (server-rendered) | `templates/team_chronicle/` | — |
| [03-hero-roster.md](screens/03-hero-roster.md) | `/app/heroes` | `Web\HeroController` | `Api\V1\HeroController` | `templates/hero/roster.html.twig` | `roster_filter` |
| [04-hero-detail.md](screens/04-hero-detail.md) | `/app/heroes/{id}` | `Web\HeroController` | `Api\V1\HeroController`, `Api\V1\ItemController`, `Api\V1\SpellController` | `templates/hero/detail.html.twig`, `templates/components/hero/` | `hero_rename`, `hero_dismiss`, `hero_sell`, `equipment`, `spellbook` |
| [05-training.md](screens/05-training.md) | `/app/training` | `Web\TrainingController` | `Api\V1\TrainingController` | `templates/training/` | `training` |
| [06-trainer-management.md](screens/06-trainer-management.md) | `/app/training` (same page) | `Web\TrainingController` | `Api\V1\TrainingController` | `templates/components/training/` | `training` |
| [07-formation-setup.md](screens/07-formation-setup.md) | `/app/formation` | `Web\FormationController` | `Api\V1\FormationController`, `Api\V1\FixtureFormationController` | `templates/formation/`, `templates/components/formation/` | `formation` |
| [08-headquarters.md](screens/08-headquarters.md) | `/app/hq` | `Web\HeadquartersController` | `Api\V1\HeadquartersController` | `templates/hq/`, `templates/components/hq/` | `hq` |
| [09-summoning-chamber.md](screens/09-summoning-chamber.md) | `/app/summon`, `/app/summon/history` | `Web\SummoningController` | `Api\V1\SummoningController` | `templates/summoning/` | `summoning` |
| [10-item-equipment.md](screens/10-item-equipment.md) | `/app/inventory` | `Web\ItemController` | `Api\V1\ItemController` | `templates/item/` | `equipment` |
| [11-spell-management.md](screens/11-spell-management.md) | `/app/spells` | `Web\SpellController` | `Api\V1\SpellController` | `templates/spell/` | `spellbook` |
| [12-combat-battle.md](screens/12-combat-battle.md) | — | — *(not implemented)* | — *(planned)* | — | — |
| [13-league.md](screens/13-league.md) | `/app/league` | `Web\LeagueController` | — (fixtures/standings server-rendered) | `templates/league/`, `templates/components/league/` | `formation` (match prep) |
| [14-calendar.md](screens/14-calendar.md) | `/app/calendar` | `Web\CalendarController` | `Api\V1\CalendarController` | `templates/calendar/` | `calendar` |
| [15-marketplace.md](screens/15-marketplace.md) | `/app/marketplace` | `Web\MarketplaceController` | `Api\V1\MarketplaceController` | `templates/marketplace/` | `marketplace` |
| [16-graveyard.md](screens/16-graveyard.md) | `/app/graveyard` | `Web\GraveyardController` | `Api\V1\GraveyardController` | `templates/graveyard/` | — |
| [17-community.md](screens/17-community.md) | `/app/community`, `/app/community/threads/{id}` | `Web\CommunityController` | `Api\V1\ForumController`, `Api\V1\MessageController`, `Api\V1\CommunityController` | `templates/community/` | `community_forum`, `community_thread`, `mail`, `player-profile` |
| [18-arena-management.md](screens/18-arena-management.md) | `/app/arena`, `/app/hq?facility=arena` | `Web\ArenaController`, `Web\HeadquartersController` | `Api\V1\ArenaController` | `templates/arena/`, `templates/components/hq/facility_panel/_arena.html.twig` | `hq` |
| [19-player-profile-settings.md](screens/19-player-profile-settings.md) | `/app/settings`, account modal | `Web\SettingsController` | `POST /app/settings/*` (JSON from modal) | `templates/components/layout/_account_settings_modal.html.twig` | `account_settings`, `profile_settings`, `modal` |

---

## Finance & notifications (cross-cutting)

| Feature | Web route | Web controller | API | Twig / Stimulus |
|---------|-----------|----------------|-----|-----------------|
| Finance ledger | `/app/finance` | `Web\FinanceController` | `Api\V1\FinanceController` | `templates/finance/`, `ledger` |
| Notifications modal | (navbar) | — | `Api\V1\NotificationController` | `templates/components/notifications/` | `notifications` |
| Player mail | (navbar / community) | — | `Api\V1\MessageController` | `templates/components/mail/` | `mail` |

---

## Service layer (by screen domain)

| Domain | Services | Tests |
|--------|----------|-------|
| Team / dashboard | `TeamService`, `TeamRosterService`, `FanClubService` | `tests/Service/Team/` |
| Chronicle | `TeamChronicleService`, `TeamChroniclePresenter` | `tests/Service/TeamChronicle/` |
| Heroes | `HeroService`, `HeroGenerator`, `HeroDismissalService` | `tests/Service/Hero/` *(add as needed)* |
| Training | `TrainingService`, `TrainerDismissalService` | `tests/Service/Training/` |
| Formation | `FormationService`, `FixtureFormationService` | `tests/Service/Formation/` |
| HQ | `HeadquartersService`, `ArenaService`, `HqMaintenanceCalculator` | `tests/Service/Headquarters/` |
| Summoning | `SummoningService` | `tests/Service/Summoning/` |
| Items | `ItemService` | `tests/Service/Item/` |
| Spells | `SpellService` | `tests/Service/Spell/` |
| Marketplace | `MarketplaceService` | `tests/Service/Marketplace/` |
| Community | `CommunityService`, `ForumThreadHelper`, `PlayerProfileService` | `tests/Service/Community/` |
| Graveyard | `GraveyardService`, `GraveyardPresenter` | `tests/Service/Graveyard/` |
| Calendar / ticks | `CalendarService`, `KingdomTickRunnerService`, `ProcessKingdomTicksHandler` | `tests/Service/Calendar/` |
| League | `LeagueService`, `LeagueFixtureScheduler`, `SeasonTransitionService` | `tests/Service/League/` |
| Economy | `EconomyService`, `FinancialCrisisService`, `ArenaRevenueService` | `tests/Service/Economy/` |
| Auth | `RegistrationService`, `UserSettingsService` | `tests/Service/Auth/` |

---

## Shared layout & UI shell

| Concern | Files |
|---------|-------|
| Game layout | `templates/layouts/game.html.twig`, `templates/base.html.twig` |
| Navbar / sidebar | `templates/components/layout/navbar.html.twig`, `sidebar.html.twig`, `resource_bar.html.twig` |
| Modals | `templates/components/layout/*_modal.html.twig`, `assets/controllers/modal_controller.js` |
| SCSS domains | See [fantager-ui/reference.md](../.cursor/skills/fantager-ui/reference.md) |

---

## Planned / out of scope

| Screen / feature | Status |
|------------------|--------|
| Combat replay ([12-combat-battle.md](screens/12-combat-battle.md)) | Blocked — [known-issues.md](known-issues.md) #1 |
| Public wiki (`/wiki`) | Planned — [route-map.md](route-map.md) |
| Alliances (Milestone 7) | Not started — [roadmap.md](roadmap.md) |
| Dungeons, quests, crafting | Deferred — [future/](future/) |

---

## Agent resources

| Resource | Purpose |
|----------|---------|
| This file | Screen → code file map |
| [backend-agent-cheatsheet.md](backend-agent-cheatsheet.md) | PHP/API workflow |
| [ui-agent-cheatsheet.md](ui-agent-cheatsheet.md) | Twig/SCSS workflow |
| `bash scripts/check-backend-docs.sh` | Routes, entities, enums, i18n keys |
