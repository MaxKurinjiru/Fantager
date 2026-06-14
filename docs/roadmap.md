# Implementation Roadmap (Vertical-Slice & Chronological)

Purpose: Define a logical, step-by-step implementation path for the Fantager project. Unlike pure backend/frontend phase separation, this roadmap is organized by functional **Milestones (Vertical Slices)**. Each milestone builds a complete, testable, and visual slice of a feature—from database schema and service logic to internal APIs and interactive web interfaces.

---

## Guiding Principles

- **Vertical Slice Development**: Implement database, service logic, API endpoints, and frontend views together for each feature. This ensures every step produces a runnable, visually testable increment.
- **Dependency Flow**: Do not skip ahead. Later milestones depend on the foundational entities and calculations established in earlier ones.
- **Aesthetic Excellence**: Every frontend step must align with the UI specifications (`docs/ui-guidelines.md`), utilizing customized colors, typography, micro-animations, and responsive designs.
- **Verification First**: Every step must include unit/integration tests and manual walkthrough verification.

---

## Milestone 1: Authentication & Kingdom Setup
*Build the core user account systems and allow players to choose their starting Kingdom and auto-claim their initial team.*

### Step 1.1: Database Infrastructure & Schema Setup
- **Database & Entities**: Create migrations for base tables: `User`, `Kingdom`, `Team`, and session tables. Configure dual Doctrine connections (`default` and `legacy`) to support raw SQL read operations from legacy DB.
- **Verification**: Run `bin/console doctrine:migrations:migrate` and check connection configuration.
- **Status**: ✅ Complete (`Version20260608160305` covers full system schema).

### Step 1.2: Authentication & Sessions
- **Service Layer**: Implement security firewalls, CSRF protection, rate limiting, and password hashing.
- **API/Web Controllers**: Implement signup, login, email verification, and password reset routes.
- **Frontend Views**: Create registration and login templates matching design guidelines.
- **Verification**: Run integration tests for signup/login validation.
- **Status**: ✅ Complete (`SecurityController`, `RegistrationController`, `ResetPasswordController`).

### Step 1.3: Kingdom Selection
- **Database & Entities**: `Kingdom` entity with `league_tiers_config` (JSON configuration for league capacities). Load static kingdom data.
- **Service Layer**: `KingdomService` to calculate current player capacities and manage availability.
- **API Contracts**: `GET /api/v1/kingdoms` returning active kingdoms and capacity status.
- **Frontend Views**: Integration of Kingdom selection dropdown/cards into the registration process.
- **Verification**: Verify capacity block when a kingdom reaches maximum player limit.
- **Status**: ✅ Complete (`KingdomService`, `/api/v1/kingdoms`, Twig integration).

### Step 1.4: Team Dashboard
- **Database & Entities**: `Team` entity linked to `User` and `Kingdom`.
- **Service Layer**: Automatic NPC team claiming logic during user registration (`RegistrationService`).
- **API Contracts**: `GET /api/v1/teams/{teamId}/dashboard` returning wallet data, reputation, and roster stats.
- **Frontend Views**: Main Dashboard (`templates/dashboard/index.html.twig`) with active team stats, recent activity feeds, and settings tab.
- **Verification**: Register a new user and verify that an NPC team is successfully claimed and rendered.
- **Status**: ✅ Complete (`Web\DashboardController`, `Web\SettingsController`, `Api\V1\TeamController`, Stimulus: `profile_settings_controller.js`).

---

## Milestone 2: Hero Summoning & Roster Management
*Allow players to summon their first batch of heroes, view roster stats, and manage hero profiles.*

### Step 2.1: Economy & Wallet Transactions
- **Database & Entities**: Add currency columns (`gold`, `essence`) to `Team`. Create `FinancialRecord` logs.
- **Service Layer**: `EconomyService` to manage currency deposits, deductions, and transaction logging.
- **Verification**: Unit tests on wallet operations (e.g., negative balance checks, overflow protection).
- **Status**: ✅ Complete (`EconomyService`, ledger logs, weekly seating ticket revenue distribution).

### Step 2.2: Hero Summoning Chamber
- **Database & Entities**: `Hero` and `SummonHistory` entities.
- **Service Layer**: `HeroGenerator` with race name pools (first and surname definitions per race in `HeroGenerator`). `SummoningService` to handle race compatibilities and cooldowns.
- **API Contracts**: `POST /api/v1/summoning` (initiates summon), `GET /api/v1/summoning/status`.
- **Frontend Views**: Summoning Chamber UI (`templates/summoning/index.html.twig`) with cooldown timers, race select, and Reveal AJAX animation.
- **Verification**: Summon heroes, verify cooldown timings, and check names are correctly chosen from the race configuration pools.
- **Status**: ✅ Complete (`SummoningService`, `HeroGenerator`, `templates/summoning/index.html.twig`, Stimulus: `summoning_controller.js`).

### Step 2.3: Hero Roster & Profile Management
- **Service Layer**: Hero CRUD operations, renaming validator, and stat calculations based on race.
- **API Contracts**: `GET /api/v1/heroes` (roster list), `PUT /api/v1/heroes/{id}` (rename/update).
- **Frontend Views**: Roster cards grid (`templates/hero/roster.html.twig`), detail card with stat meters (`templates/hero/detail.html.twig`).
- **Verification**: Filter/sort heroes by level, class, and race; rename a hero and check constraints.
- **Status**: ✅ Complete (`Web\HeroController`, `templates/hero/roster.html.twig`, Stimulus: `roster_filter_controller.js`, `hero_rename_controller.js`).

### Step 2.4: Finance History Ledger
- **Database & Entities**: `FinancialRecord` logs of transactions.
- **Service Layer**: Listing and sorting transactions via `FinancialRecordRepository`.
- **API/Web Controllers**: `GET /app/finance` rendering filtered ledger logs by type and actor.
- **Frontend Views**: Finance History page (`templates/finance/index.html.twig`) with filters (type, actor) and transaction rows.
- **Verification**: Run transactions (e.g. summoning, upgrading) and verify they are recorded and displayed with correct details on the finance history page.
- **Status**: ✅ Complete (`Web\FinanceController`, `templates/finance/index.html.twig`).

---

## Milestone 3: Headquarters & Training Loop
*Upgrade headquarters facilities to unlock passive bonuses and queue heroes in the training center.*

### Step 3.1: Headquarters Upgrades
- **Database & Entities**: `Headquarters` and `Facility` entities.
- **Service Layer**: Upgrade costs and time math, facility level checks, and passive resource buffs. Race optimization toggling.
- **API Contracts**: `POST /api/v1/hq/upgrade`, `POST /api/v1/hq/optimize`.
- **Frontend Views**: HQ dashboard, facilities level bars, and live upgrade progress counters.
- **Verification**: Check gold deduction during upgrades, test passive multiplier calculations.
- **Status**: ✅ Complete (`HeadquartersService`, `Web\HeadquartersController`, Stimulus: `hq_controller.js`).

### Step 3.2: Hero Training Loop
- **Database & Entities**: `TrainingQueue` and `Trainer` entities.
- **Service Layer**: Training rate calculations. Tick processing CLI command (`App\Command\ProcessTrainingTickCommand`) to apply accumulated stat increases.
- **API Contracts**: `POST /api/v1/training/trainers/{id}/assign` (assign hero to trainer), `POST /api/v1/training/trainers/{id}/unassign`, `POST /api/v1/training/trainers/{id}/configure`.
- **Frontend Views**: Trainers dashboard panel, trainers selection list, and assigned trainees list.
- **Verification**: Assign a hero to a trainer, configure trainer focus, run the command `bin/console app:training:tick`, and verify hero stats increase correctly.
- **Status**: ✅ Complete (`TrainingService`, `ProcessTrainingTickCommand`, `Web\TrainingController`, `Api\V1\TrainingController`, Stimulus: `training_controller.js`).

---

## Milestone 4: Combat Prep (Equipment, Spells, Formations)
*Prepare heroes for battle by equipping weapons, organizing spellbooks, and configuring tactical grids.*

### Step 4.1: Items & Roster Inventory
- **Database & Entities**: `Item` and `Equipment` slots.
- **Service Layer**: Equip/unequip validators, item attributes, and dismantling rewards.
- **API Contracts**: `PUT /api/v1/heroes/{id}/equipment` (equip/unequip), `POST /api/v1/items/dismantle`, `POST /api/v1/items/{id}/repair`.
- **Frontend Views**: Interactive inventory drag-and-drop grid and hero equipment slots (paperdoll UI).
- **Verification**: Equip items to matching slots, verify stat modifier calculations, dismantle items.
- **Status**: ✅ Complete (`ItemService`, `Web\ItemController`, Stimulus: `equipment_controller.js`).

### Step 4.2: Spellbooks & Magic Learning
- **Database & Entities**: `Spell` and `SpellMastery` entities.
- **Service Layer**: Magic schools mastery levels, learning spells requirements, equipping.
- **API Contracts**: `POST /api/v1/heroes/{id}/spells/learn`, `POST /api/v1/heroes/{id}/spells/equip`, `POST /api/v1/heroes/{id}/spells/unequip`.
- **Frontend Views**: Spell learning dashboard, magic slots assignment list.
- **Verification**: Learn spells using essence, equip them, and confirm slot limits are respected.
- **Status**: ✅ Complete (`SpellService`, `Web\SpellController`, Stimulus: `spellbook_controller.js`).

### Step 4.3: Strategic Formations
- **Database & Entities**: `Formation` layout.
- **Service Layer**: Lineup configuration (3 front, 3 back slots), target priorities, and synergies logic.
- **API Contracts**: `PUT /api/v1/formations` (save/update formation), `DELETE /api/v1/formations/{id}`.
- **Frontend Views**: Interactive drag-and-drop tactical grid, action sequences selector.
- **Verification**: Move heroes between positions, verify front/back line constraints (max 6 active).
- **Status**: ✅ Complete (`FormationService`, `Web\FormationController`, Stimulus: `formation_controller.js`).

---

## Milestone 5: Marketplace & Community Forum
*Open the player economy with auctions and establish independent communication boards for the kingdoms.*

### Step 5.1: Marketplace Auctions
- **Database & Entities**: `MarketplaceListing` and `MarketplaceBid` entities.
- **Service Layer**: Auction bid validation, fee deduction, item escrow, buyout options.
- **API Contracts**: `GET /api/v1/marketplace` (search/list listings), `POST /api/v1/marketplace/listings` (create listing), `POST /api/v1/marketplace/bid`, `POST /api/v1/marketplace/purchase`, `DELETE /api/v1/marketplace/listings/{id}`.
- **Frontend Views**:
  - **[NEW]** Marketplace Hub: Search filters (item level, rarity, class), bidding card widgets, active listing forms.
- **Verification**: List a hero, bid from a different account, verify gold deductions and escrow refunds on higher bids.
- **Status**: ✅ Complete (MarketplaceService, CLI processor, API & Web Controllers, and templates/controllers refactored per UI guidelines).

### Step 5.2: Kingdom Community Forum
- **Database & Entities**: `ForumThread`, `ForumPost`, and `Message` entities.
- **Service/Business Logic**: Messaging filters, Kingdom-specific discussion categorization, post moderation.
- **API Contracts**: `GET /api/v1/forum/threads`, `POST /api/v1/forum/threads`, `POST /api/v1/forum/threads/{id}/posts`.
- **Frontend Views**:
  - **[NEW]** Community Boards: Integrated discussion boards (categorized by Kingdom and global discussion), global player mail.
- **Verification**: Create a thread, reply to a thread, view categorized discussion boards.
- **Status**: ✅ Complete (Entities updated, services and API controllers implemented, and templates/controllers refactored per UI guidelines).

---

## Milestone 6: Combat Simulation, Calendar & Leagues
*Implement the core combat engine, chronological event tick scheduler, the weekly league competition, and hero mortality.*

### Step 6.1: Combat Simulation Engine (Core Block)
- **Design Prerequisites (Phase 0)**: Resolve [known-issues.md](known-issues.md) #1 (Combat formulas: HP, damage, defense, accuracy, dodge, crits, status effects).
- **Service/Business Logic**:
  - Implement a deterministic turn-resolution engine resolving combat round-by-round.
  - Apply status effects (poison, stun, buffs) per tick based on speed order.
  - Calculate post-match updates: XP gains, form adjustments, fatigue accumulation, morale impact, and hero aging.
  - Generate a detailed `combat_log` JSON structure containing step-by-step actions.
- **API Contracts**:
  - `POST /api/v1/combat/simulate` — Practice/sandbox match between two rosters (requires 6 combat-ready heroes per team).
  - `GET /api/v1/combat/{matchId}/log` — Retrieve replay log.
- **Frontend Views**:
  - **[NEW]** Combat Replay Viewer UI: A visually compelling page that reads a combat log JSON and steps through the battle with animations, hit point bars, and logs.
- **Verification**:
  - Write extensive unit tests for combat calculations (accuracy, dodge, crit multipliers).
  - Test forfeit validation: if one team has <6 combat-ready heroes, ensure immediate 3-0 forfeit without simulator trigger.
- **Status**: ⏳ Not Started (Entities scaffolded, engine pending).

### Step 6.2: Calendar & Server Ticks System
- **Database & Entities**: `CalendarSeason` and `GameEvent` entities.
- **Service Layer**:
  - Timeline tick scheduler running weekly/hourly increments.
  - Action runners triggered by calendar ticks: passive economy income, training time updates, and league match executions.
- **API/CLI Contracts**:
  - `bin/console app:process-calendar-tick` — Command triggered by cron to advance game time.
- **Verification**: Trigger a calendar tick and check if queues (training, items, leagues) update.
- **Status**: ⏳ Not Started (Partial entities scaffolded, tick processor pending).

### Step 6.3: League Matchmaking & Season Transition
- **Design Prerequisites (Phase 0)**: Resolve [known-issues.md](known-issues.md) #7 (Friendly match rules) and #8 (Arena Match mechanics).
- **Service Layer**:
  - Implement Berger’s Algorithm to generate 18 rounds of double round-robin fixtures.
  - Enforce home/away balance (1 home, 1 away match per week of play).
  - Standings updates: calculate played, wins, draws, losses, points, goal difference.
  - Season transition: process promotions, relegations, compound rewards (using global comparison tie-breakers). Shuffle groups for next season.
- **API Contracts** (Planned/Deferred - Currently optional as the Web dashboard renders standings/fixtures server-side via Twig):
  - `GET /api/v1/league/standings` — standings list (planned).
  - `GET /api/v1/league/fixtures` — matches list (planned).
  - `POST /api/v1/league/process-season` — manual admin season trigger (planned).
- **Frontend Views**:
  - **[NEW]** League Dashboard: Group standings table, fixture timeline, match summaries, and promotion/relegation threshold lines.
- **Verification**: Run complete 11-week season simulation using CLI commands and verify standings and reward distributions.
- **Status**: 🔄 Partially Complete (`LeagueFixtureScheduler`, `SeasonTransitionService`, and Web League Dashboard fully complete; API endpoints and combat simulation matching are pending implementation under the remaining Phase 6 simulation tasks).

### Step 6.4: Hero Mortality & Graveyard
- **Database & Entities**: `GraveyardRecord` entity.
- **Service Layer**: Permanent death triggers in combat, transfer stats to graveyard log.
- **API Contracts**: `GET /api/v1/graveyard/records`.
- **Frontend Views**:
  - **[NEW]** Memorial Graveyard: Interactive cemetery listing fallen heroes with final stats, cause of death, and customizable epitaph texts.
- **Verification**: Run a combat simulation where a hero dies, verify they are removed from rosters and added to the Graveyard DB.
- **Status**: ⏳ Not Started (Entities scaffolded, services pending).

---

## Milestone 7: Alliances & Guild System
*Establish alliances, team cooperation, and guild chat communication.*

### Step 7.1: Alliance Foundation & Management
- **Database & Entities**: `Alliance`, `AllianceMember` entities.
- **Service/Business Logic**: Alliance creation, invitations, membership application, roles/ranks, alliance leaderboards.
- **API Contracts**: `GET /api/v1/alliances`, `POST /api/v1/alliances/create`, `POST /api/v1/alliances/{id}/invite`, `POST /api/v1/alliances/{id}/join`.
- **Frontend Views**:
  - **[NEW]** Alliance Hub: Roster lists, application portals, alliance rank leaderboards, and settings page.
- **Verification**: Create an alliance, invite another team, accept the invitation, verify permissions and ranking.
- **Status**: ⏳ Not Started.

### Step 7.2: Alliance Communication
- **Database & Entities**: Uses existing communication entities (`Message` etc. scoped to Alliance).
- **Service/Business Logic**: Alliance-only chat persistence and filtering.
- **API Contracts**: `POST /api/v1/alliances/chat`, `GET /api/v1/alliances/chat/history`.
- **Frontend Views**:
  - **[NEW]** Alliance Chat Pane: Embedded live alliance chat feed within the Alliance Hub.
- **Verification**: Send chat messages within an alliance, confirm they are only visible to alliance members.
- **Status**: ⏳ Not Started.

---

## Milestone 8: Endgame & Advanced Content
*Extend the sandbox with PvE dungeon instances, daily quests, item crafting, and stadium business operations.*

### Step 8.1: PvE Dungeon Encounters
- **Database & Entities**: `DungeonRun` and `DungeonFloor` tables (depends on Combat).
- **Service Layer**: Monster roster generation, floor difficulty progression, chest loot generators.
- **API Contracts**: `POST /api/v1/dungeons/enter`, `POST /api/v1/dungeons/combat`.
- **Frontend Views**:
  - **[NEW]** Dungeon Map UI: Floor progression map, encounter cards, reward reveals.
- **Verification**: Complete dungeon floors, confirm health persistence across fights and reward logs.
- **Status**: ⏳ Not Started (Entities scaffolded, services pending).

### Step 8.2: Daily & Weekly Quest Systems
- **Database & Entities**: `Quest` and `PlayerQuestProgress` tables.
- **Service Layer**: Daily Quest pool generation, progress triggers (e.g., training ticks, marketplace trades), rewards allocation.
- **API Contracts**: `GET /api/v1/quests`, `POST /api/v1/quests/claim/{id}`.
- **Frontend Views**:
  - **[NEW]** Quest log: list of daily/weekly challenges, progress indicators, claim buttons.
- **Verification**: Perform quest conditions, verify progress bar fills, claim rewards.
- **Status**: ⏳ Not Started (Entities scaffolded, services pending).

### Step 8.3: Material Gathering & Crafting
- **Database & Entities**: `CraftingRecipe` and `CraftingQueue` tables.
- **Service Layer**: Recipe unlocks, materials cost validation, crafting queue speed bonuses from HQ.
- **API/Web Controllers**: `POST /api/v1/crafting/start`, `DELETE /api/v1/crafting/queue/{id}`.
- **Frontend Views**:
  - **[NEW]** Crafting Workshop: Recipe catalog, required ingredients checklist, active crafting progress bars.
- **Verification**: Check material requirement validation, verify crafted items appear in the team inventory.
- **Status**: ⏳ Not Started (Entities scaffolded, services pending).

### Step 8.4: Arena Facility Management
- **Database & Entities**: Link stadium upgrades directly to HQ Arena levels.
- **Service Layer**: Weekly seating capacity calculations, ticket price elasticity calculations, passive revenue distribution service.
- **API Contracts**: `POST /api/v1/hq/arena/tickets/price`.
- **Frontend Views**:
  - **[NEW]** Arena Hub: Seating upgrade charts, weekly attendance graphs, ticket price sliders.
- **Verification**: Modify ticket price, trigger weekly ticket revenue command, and confirm revenue scales with formulas.
- **Status**: ⏳ Not Started (`ArenaRevenueService` skeleton exists, UI pending).

---

## Cross-Cutting Infrastructures (Ongoing)

- **CI/CD Pipeline**: Setup GitHub Actions workflow to run PHPStan static analysis and PHPUnit tests automatically on pull requests.
- **Code Quality Tooling**: Maintain PHPStan configuration (level 6, zero errors limit) and automated PHP-CS-Fixer styling rules.
- **E2E Testing Suite**: Set up Playwright to cover registration, team selection, and training/summoning flows under realistic user action scenarios.

---

## Project Chronological Progression Status

The following matrix displays what has been completed in the codebase relative to the newly defined chronological steps:

| Milestone / Slice | Database / Entities | Service Layer / CLI | API Endpoints | Frontend UI Views | Current Status |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Milestone 1 (Auth & Kingdom)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 2 (Heroes & Economy)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 3 (HQ & Training)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 4 (Combat Prep)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 5 (Marketplace & Forum)**| ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 6 (Combat & Leagues)** | 🔄 | 🔄 | 🔄 | ✅ | *In Progress* |
| **Milestone 7 (Alliances)** | ⏳ | ⏳ | ⏳ | ⏳ | *Not Started* |
| **Milestone 8 (Endgame & Crafting)** | ✅ | ⏳ | ⏳ | ⏳ | *Scaffolded Only* |

*Last updated: June 13, 2026 — Transformed to vertical slice roadmap layout*
