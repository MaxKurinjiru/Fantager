# Implementation Roadmap

Purpose: Define the implementation order so agents and developers know what to build first and why.

---

## Phase 0 — Design Decisions Required (Blocked on User Input)

These items must be resolved before implementation begins. Each is an open question or gap in the design docs.
See [known-issues.md](known-issues.md) for the full list. The ones below directly block Phase 1+ implementation.

| # | Area | Decision needed | Known-issue ref |
|--:|------|----------------|-----------------|
| 1 | **Onboarding** | ~~How many starting heroes? At what level/age?~~ — **RESOLVED**: 10 heroes, **level 1**, age **[Min Age, Max Junior Age]** per race. | ~~[#4](known-issues.md)~~ |
| 2 | **Roster** | ~~Starting Barracks capacity?~~ — **RESOLVED**: 10 (matches starting hero count). | ~~[#3](known-issues.md)~~ |
| 3 | **Morale** | What value does morale reset to on hero transfer — 50, 100, or race-specific? | ~~[#2](known-issues.md)~~ — resolved: default = 50 |
| 4 | **Combat formulas** | Document HP, damage, defense, accuracy, dodge, crit and status-effect formulas | [#1](known-issues.md) |
| 5 | **Item durability & enchanting** | Define durability degradation rate, repair cost, and enchanting mechanic (Essence costs exist but rules don't) | [#2](known-issues.md) |
| 6 | **Race balance** | ~~Confirm Genie stat-budget difference (+10.6% vs Human) is intentional; add equipment restrictions if not~~ — **RESOLVED**: All races use flat integer bonuses in `races.yaml`; balance tunable per config. | ~~[#9](known-issues.md)~~ |
| 7 | **Data consistency** | ~~Confirm whether the asymmetric Giant↔Genie relationship values (60 vs 70) are intentional or a typo~~ — **RESOLVED**: Giant↔Genie = 60 (Neutral) in all sources. | ~~[#10](known-issues.md)~~ |
| 8 | **Friendly Matches** | Document rules and purpose of Friendly Matches listed in the tick schedule | [#7](known-issues.md) |
| 9 | **Arena Match mechanics** | Document standalone Arena Match rules separate from the HQ arena facility | [#8](known-issues.md) |
| 10 | **Review next phases** | Walk through Phases 1–6 with maintainers and confirm scope, entity names, and acceptance criteria before any code is written | — |

**Exit criteria**: All rows above are resolved and the answers are recorded in the relevant design docs. Phase 0 is never "done" until every row has a decision written down and the known-issues entry removed or updated.

---

## Guiding Principles

- Each phase should produce a runnable, testable increment.
- Later phases depend on earlier ones — don't skip ahead.
- Within a phase, items can be parallelized unless noted.

---

## Phase 1 — Foundation (skeleton done, fill implementation)

| # | Task | Depends on | Notes |
|--:|------|-----------|-------|
| 1 | **Auth System** — User entity, registration, login, sessions | — | ✅ Complete — login, register, verify email, password reset, rate limiting, CSRF, NPC team assignment |
| 2 | **Kingdom System** — Kingdom entity, list endpoint, selection at registration | Auth | ✅ Complete — `/api/v1/kingdoms` endpoint, capacity calculation, KingdomService |
| 3 | **Team System** — Team entity, auto-create on kingdom join | Auth, Kingdom | ✅ Complete — NPC team claimed on email verification, VerificationService::assignNpcTeam |
| 4 | **Basic DB migrations** — Schema for User, Kingdom, Team | 1–3 | ✅ Complete — `Version20260608160305` generated; covers full schema across all phases (not just Phase 1 entities) |

**Exit criteria**: A player can register, choose a kingdom, and land on an empty dashboard.

---

## Phase 2 — Heroes & Core Loop

| # | Task | Depends on | Notes |
|--:|------|-----------|-------|
| 5 | **Hero System** — Hero entity, CRUD, summoning chamber | Team | Entity + repository scaffolded ✅; `HeroService` + `HeroGenerator` done ✅; `Api\HeroController` done ✅; `SummoningService` + `Api\SummoningController` done ✅ |
| 6 | **Training System** — Training queue, tick processing, stat increases | Hero | Entity + repository scaffolded ✅; `TrainingService` + `Api\TrainingController` done ✅; tick processor (stat application) command and service done ✅ |
| 7 | **Headquarters System** — Facilities, upgrades, effects | Team | Entity + repository scaffolded ✅; `HeadquartersService` + `Api\HeadquartersController` done ✅ |
| 8 | **Economy System** — Gold transactions, cost deductions, income stubs | Training, HQ | Team wallet columns done ✅; `EconomyService` (deduct/add gold) done ✅; transaction logging & weekly arena ticket revenue distribution command done ✅ |

**Exit criteria**: A player can summon heroes, train them, upgrade HQ facilities, and see gold change.

---

## Phase 3 — Equipment & Combat Preparation

| # | Task | Depends on | Notes |
|--:|------|-----------|-------|
| 9 | **Item System** — Items, equipment slots, inventory | Hero | Entity + repository scaffolded ✅; `ItemService` + `Api\ItemController` done ✅ |
| 10 | **Spell System** — Spells, schools, mastery, equipping | Hero | Entity + repository scaffolded ✅; `SpellService` + `Api\SpellController` done ✅ |
| 11 | **Formation System** — Formation entity, lineup assignment | Team, Hero | Entity + repository scaffolded ✅; `FormationService` + `Api\FormationController` done ✅ |

**Exit criteria**: Heroes can be equipped, have spells, and be placed in formations.

---

## Phase 4 — Frontend Core

| # | Task | Depends on | Notes |
|--:|------|-----------|-------|
| 12 | **Kingdom Selection UI** — Kingdom list, filters, and selection during registration | Kingdom | ✅ Complete — kingdom list rendered in registration flow via `templates/auth/` + `RegisterController`; `/api/v1/kingdoms` wired |
| 13 | **Main Dashboard UI** — Full team panel, stats, recent activity feed, and settings | Team, Economy | ✅ Complete — `Web\DashboardController` + `templates/dashboard/index.html.twig`; `Web\SettingsController` + `templates/settings/index.html.twig`; `profile_settings_controller.js` |
| 14 | **Hero Management UI** — Hero list/cards, filter/sort, and detailed hero info view | Hero | ✅ Complete — `Web\HeroController` + `templates/hero/roster.html.twig` + `detail.html.twig`; `hero_rename_controller.js` + `roster_filter_controller.js` |
| 15 | **Summoning Chamber UI** — Summoning screen, race selection, cooldowns, and animations | Summoning | ✅ Complete — `Web\SummoningController` + `templates/summoning/index.html.twig`; `summoning_controller.js` (AJAX summon, reveal animation, cooldown timer) |
| 16 | **Training Center UI** — Frontends for attribute training, trainer selection, and magic training | Training | ✅ Complete — `Web\TrainingController` + `templates/training/index.html.twig`; `training_controller.js` (cost calc, queue countdown) |
| 17 | **Inventory & Equipment UI** — Interactive inventory grid, detail cards, and paperdoll equipping | Item | ✅ Complete — `Web\ItemController` + `templates/item/index.html.twig`; `equipment_controller.js` |
| 18 | **Spellbook UI** — UI for learning spells, expanding slots, and equipping spells | Spell | ✅ Complete — `Web\SpellController` + `templates/spell/index.html.twig`; `spellbook_controller.js` |
| 19 | **Formation & Strategy UI** — Drag-and-drop grid (Front/Back line), targets priority, action sequence, and synergy | Formation | ✅ Complete — `Web\FormationController` + `templates/formation/index.html.twig`; `formation_controller.js` |
| 20 | **Headquarters Facilities UI** — HQ dashboard, facility levels, upgrades, and race optimization | Headquarters | ✅ Complete — `Web\HeadquartersController` + `templates/hq/index.html.twig`; `hq_controller.js` (upgrade, race optimization toggle) |

**Exit criteria**: Complete playable frontend skeleton for all core management loops (auth, dashboard, summoning, training, equipment, formations).

---

## Phase 5 — Combat & Competition

> **Prerequisites (Phase 0)**: Before starting this phase, the following open design decisions must be resolved — see [known-issues.md](known-issues.md):
> - **#1** Combat formulas (HP, damage, defense, accuracy, dodge, crits, status effects)
> - **#7** Friendly Match rules and purpose
> - **#8** Arena Match mechanics (standalone mode, separate from HQ facility)

| # | Task | Depends on | Notes |
|--:|------|-----------|-------|
| 21 | **Combat System** — Simulation engine, turn resolution, logging | Formation, Item, Spell | Entity + repository scaffolded ✅; simulation engine pending |
| 22 | **League System** — Seasons, matchmaking, standings | Combat | All 5 entities + repositories scaffolded ✅; `LeagueFixtureScheduler` (fixture generation) partially implemented ✅; full matchmaking/processing logic pending |
| 23 | **Event System** — Calendar ticks, event triggers | Combat, Economy | Entity + repository scaffolded ✅; tick processing/triggers pending |

**Exit criteria**: Automated league matches run, results are logged, standings update.

---

## Phase 6 — Economy & Social

| # | Task | Depends on | Notes |
|--:|------|-----------|-------|
| 24 | **Marketplace System** — Listings, buying, selling, fees | Economy, Item, Hero | All 3 entities + repositories scaffolded ✅; service/controller pending |
| 25 | **Community System** — Alliances, messaging, social | Auth, Team | All 6 entities + repositories scaffolded ✅; service/controller pending |
| 26 | **Graveyard System** — Permanent death records, memorial | Hero, Combat | Entity + repository scaffolded ✅; service/controller pending |
| 27 | **Settings Alignment** — Refactor Settings routes to API/Web spec | Auth | Refactor web-JSON settings methods to standard `Api\SettingsController` later when settings expand |

**Exit criteria**: Full economic loop with player trading, social interaction, and hero mortality.

---

## Phase 7 — Extended Content

| # | Task | Depends on | Notes |
|--:|------|-----------|-------|
| 28 | **Dungeon System** — PvE encounters, rewards | Combat, Economy | Entity + repository scaffolded ✅; encounter/reward logic pending |
| 29 | **Quest System** — Daily/weekly quests, reward claiming | Economy, Hero | Entity + repository scaffolded ✅; generation/reward logic pending |
| 30 | **Crafting System** — Recipes, materials, queue | Item, Economy | Entity + repository scaffolded ✅; service/controller pending |
| 31 | **Arena Management** — Ticket revenue, capacity upgrades | HQ, Economy | `FacilityType::Arena` defined ✅; revenue/capacity upgrade service pending |

**Exit criteria**: All documented systems are implemented and integrated.

---

## Cross-Cutting (ongoing, any phase)

- CI/CD pipeline setup (GitHub Actions — not yet configured)
- Code quality tooling: PHPStan ✅ configured; PHP-CS-Fixer ✅ configured (`.php-cs-fixer.dist.php`); PHPUnit ✅ passing (coverage expansion pending); Playwright E2E ❌ not yet installed
- Frontend templates and Stimulus components: ✅ Complete through Phase 4 — all 10 Stimulus controllers + all web templates implemented
- Documentation updates as systems are implemented

---

## Data Migration (infrastructure, any phase)

A second Doctrine DBAL connection (`legacy`) is configured alongside the primary `default` connection to support reading data from an external/legacy database during migration tasks.

| Item | Detail |
|------|--------|
| **Config** | `config/packages/doctrine.yaml` — `dbal.connections.legacy` + `orm.entity_managers.legacy` |
| **Env var** | `DATABASE_LEGACY_URL` in `.env.local` (not committed; set per environment) |
| **Usage** | Inject `@doctrine.dbal.legacy_connection` (raw SQL) or the `legacy` entity manager into migration services/commands |
| **Scope** | Legacy connection is read-only by convention — no entities are mapped to it unless explicitly added |
| **Migrations** | `doctrine:migrations:*` commands target only the `default` connection; run with `--em=default` if needed |

---

## Status

- Phase 1 (tasks 1–4): ✅ Complete — Auth, Kingdom, and Team systems implemented; full DB schema generated in `Version20260608160305` (covers all phases)
- Data Migration infrastructure: ✅ Complete — dual Doctrine connection configured (`default` + `legacy`)
- Entity + repository layer: ✅ Complete for all phases — all 43 entities, all repositories, and all 34 PHP enums scaffolded
- Phase 2 (tasks 5–8): ✅ Complete — `HeroService` + `HeroGenerator`, `SummoningService`, `HeadquartersService`, `EconomyService` (including financial transaction logging and weekly seating ticket revenue distribution), `TrainingService` (including training tick processor) + all API controllers and commands implemented
- Phase 3 (tasks 9–11): ✅ Complete — `ItemService`, `SpellService`, `FormationService` + API controllers implemented; `Api\TeamController` (dashboard + settings) also done
- Phase 0 design blockers: Items #1 (combat formulas), #2 (item durability/enchanting), #7 (Friendly Matches), #8 (Arena Match mechanics) remain open; previously resolved items removed from list
- Phase 4 (tasks 12–20) frontend templates and Stimulus components: ✅ Complete — all 9 screens implemented (Dashboard, Hero Roster/Detail, Summoning, HQ, Training, Inventory, Spellbook, Formation); routes auto-discovered via `#[Route]` attributes; PHPStan passing at level 6 with 0 errors
- Phase 5 (tasks 21–23): 🔄 Not started — `Battle` entity scaffolded ✅; `LeagueFixtureScheduler` partially done ✅; `Event`+`EventParticipation` entities scaffolded ✅; simulation engine, full matchmaking logic, and event triggers pending. Service directories `src/Service/Combat/` and `src/Service/League/` exist but contain only `.gitkeep` placeholders.
- Phase 6 (tasks 24–27): ⏳ Not started — all entities scaffolded ✅ (`MarketplaceListing`, `MarketplaceBid`, `Transaction`; `Message`, `ForumThread`, `ForumPost`, etc.; `GraveyardRecord`); services and controllers pending. Service directories `src/Service/Marketplace/` and `src/Service/Team/` exist but contain only `.gitkeep` placeholders.
- Phase 7 (tasks 28–31): ⏳ Not started — all entities scaffolded ✅ (`DungeonRun`; `Quest`, `PlayerQuestProgress`; `CraftingRecipe`, `CraftingQueue`; `ArenaRevenueService` done ✅); encounter logic, reward logic, crafting service pending. Service directory `src/Service/Crafting/` exists but contains only a `.gitkeep` placeholder.
- Code quality tooling: PHPStan ✅ (level 6, 0 errors), PHP-CS-Fixer ✅, PHPUnit ✅ (19 tests, 1433 assertions all passing), Playwright ❌ not installed
- Routing: ✅ Migrated to `#[Route]` attribute auto-discovery — 45 routes registered (20 web + 25 API)
- CI/CD: ❌ No GitHub Actions workflows configured yet
- Race stat bonus table in `game-summary.md` updated June 4, 2026 — multipliers replaced with flat integer bonuses from `races.yaml`
- Hero name pools expanded June 4, 2026 — all 8 races now have 30 first names and 30 surnames in `HeroGenerator`

*Last updated: June 5, 2026 — Phase 4 complete; Phase 5–7 entities scaffolded*
