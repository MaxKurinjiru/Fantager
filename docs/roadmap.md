# Implementation Roadmap (Vertical-Slice & Chronological)

Purpose: Define a logical, step-by-step implementation path for the Fantager project. Unlike pure backend/frontend phase separation, this roadmap is organized by functional **Milestones (Vertical Slices)**. Each milestone builds a complete, testable, and visual slice of a feature—from database schema and service logic to internal APIs and interactive web interfaces.

**AI agents:** Follow [AGENTS.md](../AGENTS.md) for task routing. Write tests when implementing a step; do not run PHPUnit, migrations, or other verification commands unless the user explicitly asks.

---

## Guiding Principles

- **Vertical Slice Development**: Implement database, service logic, API endpoints, and frontend views together for each feature. This ensures every step produces a runnable, visually testable increment.
- **Dependency Flow**: Do not skip ahead. Later milestones depend on the foundational entities and calculations established in earlier ones.
- **Aesthetic Excellence**: Every frontend step must align with the UI specifications (`docs/ui-guidelines.md`), utilizing customized colors, typography, micro-animations, and responsive designs.
- **Verification**: Each step should include unit/integration tests where applicable and a clear manual walkthrough for humans. **Running** tests, linters, builds, and migrations is done by maintainers, CI, or when explicitly requested — AI agents write tests and follow [AGENTS.md](../AGENTS.md); they do not execute verification commands to finish a task.

---

## Milestone 1: Authentication & Kingdom Setup
*Build the core user account systems and allow players to choose their starting Kingdom and auto-claim their initial team.*

### Step 1.1: Database Infrastructure & Schema Setup
- **Database & Entities**: Create init migration for base tables: `User`, `Kingdom`, `Team`, and session tables. Configure dual Doctrine connections (`default` and `legacy`) to support raw SQL read operations from legacy DB.
- **Verification**: Run `bin/console doctrine:migrations:migrate` and check connection configuration.
- **Status**: ✅ Complete (init migration covers full system schema).

### Step 1.2: Authentication & Sessions
- **Service Layer**: Implement security firewalls, CSRF protection, rate limiting, and password hashing.
- **API/Web Controllers**: Implement signup, login, email verification, and password reset routes.
- **Frontend Views**: Create registration and login templates matching design guidelines.
- **Verification**: Run integration tests for signup/login validation.
- **Status**: ✅ Complete (`AuthController`, `RegisterController`, `PasswordResetController`).

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
- **Frontend Views**: Main Dashboard (`templates/dashboard/index.html.twig`) with active team stats, **team chronicle widget** (5 recent `team_chronicle` entries), and settings tab. Full chronicle at `/app/chronicle`.
- **Verification**: Register a new user and verify that an NPC team is successfully claimed, chronicle shows `player_joined`, and dashboard renders recent events.
- **Status**: ✅ Complete (`Web\DashboardController`, `Web\TeamChronicleController`, `TeamChronicleService`, `templates/components/dashboard/recent_chronicle.html.twig`, `templates/team_chronicle/index.html.twig`).

---

## Milestone 2: Hero Summoning & Roster Management
*Allow players to summon their first batch of heroes, view roster stats, and manage hero profiles.*

### Step 2.1: Economy & Wallet Transactions
- **Database & Entities**: Add currency columns (`gold`, `essence`) to `Team`. Create `FinancialRecord` logs.
- **Service Layer**: `EconomyService` to manage currency deposits, deductions, and transaction logging.
- **Verification**: Unit tests on wallet operations (e.g., negative balance checks, overflow protection).
- **Status**: ✅ Complete (`EconomyService`, ledger logs, weekly seating ticket revenue distribution).

### Step 2.2: Hero Summoning Chamber
- **Database & Entities**: `Hero` and `TeamSummonHistory` entities.
- **Service Layer**: `HeroGenerator` with race name pools (first and surname definitions per race in `HeroGenerator`). `SummoningService` to handle race compatibilities and cooldowns.
- **API Contracts**: `POST /api/v1/summoning` (initiates summon), `GET /api/v1/summoning/status`.
- **Frontend Views**: Summoning Chamber HQ panel (`templates/components/hq/facility_panel/_summoning.html.twig`, `templates/components/summoning/`) with cooldown timers and Reveal AJAX animation. `GET /app/summon` redirects to `/app/hq?facility=summoning_chamber`.
- **Verification**: Summon heroes, verify cooldown timings, and check names are correctly chosen from the race configuration pools.
- **Status**: ✅ Complete (`SummoningService`, `HeroGenerator`, HQ summoning panel, Stimulus: `summoning_controller.js`).

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
- **Service Layer**: Upgrade costs and time math, facility level checks, and passive resource buffs. Arena adaptation toggling.
- **API Contracts**: `POST /api/v1/hq/upgrade`, `POST /api/v1/hq/optimize`.
- **Frontend Views**: HQ dashboard, facilities level bars, and live upgrade progress counters.
- **Verification**: Check gold deduction during upgrades, test passive multiplier calculations.
- **Status**: ✅ Complete (`HeadquartersService`, `Web\HeadquartersController`, Stimulus: `hq_controller.js`).

### Step 3.2: Hero Training Loop
- **Database & Entities**: `HeroTrainingHistory`; trainers are heroes with `role = trainer`.
- **Service Layer**: Training rate calculations. Weekly training tick (`TickType::WeeklyTraining`) processed by `ExecuteSingleTickHandler`.
- **API Contracts**: `POST /api/v1/training/trainers/{id}/assign` (assign hero to trainer), `POST /api/v1/training/trainers/{id}/unassign`, `POST /api/v1/training/trainers/{id}/configure`.
- **Frontend Views**: Trainers dashboard panel, trainers selection list, and assigned trainees list.
- **Verification**: Assign a hero to a trainer, configure trainer focus, run `bin/console app:ticks:run --time="YYYY-MM-DD 10:00:00"` after the scheduled Thursday training tick, and verify hero stats increase correctly.
- **Status**: ✅ Complete (`TrainingService`, `ExecuteSingleTickHandler` weekly training tick, `Web\TrainingController`, `Api\V1\TrainingController`, Stimulus: `training_controller.js`).

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
- **Status**: ✅ Complete (`MarketplaceService`, CLI processor, `Web\MarketplaceController`, `Api\V1\MarketplaceController`, templates/controllers refactored per UI guidelines).

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
- **Design Prerequisites (Phase 0)**: Locked in [combat-system.md](systems/combat-system.md#simulation-contract) — VOs, event-stream `combat_log`, seed, **wave Messenger orchestration** (cohort lockstep, `MAX_ROUNDS=200`, no wave timeout, `stalled` isolation, no live UI), L0→L2 AI. NPC teams use the same combat path.
- **Service/Business Logic** (phased — see combat-system § Implementation phases):
  - **6.1a-0** — ✅ Contract VO + thin one-shot `CombatEngine` / `LeagueMatchSimulator` (placeholder scores + envelope).
  - **6.1a** — ✅ Persisted run state; `CombatWave` / `ProcessCombatRound` / `CompleteBattle`; barrier without timeout; `stalled` + `ResumeCombat`; real per-round turn loop + L0; replace one-shot league binding.
  - **6.1b** — ✅ L1 targeting (`strategy.target_order`); freeze slot JSON schema ([formation-system.md](systems/formation-system.md#strategy-json-schema-phased)).
  - **6.1c** — ✅ L2 spell conditions; **post-match** replay viewer MVP (not live).
  - **6.1d** — ✅ Combat deaths → aging → graveyard; item durability loss after battle.
  - ✅ Status effects per tick (speed order); post-match XP / form / fatigue / morale on completion (aging in 6.1d).
- **API Contracts**:
  - `POST /api/v1/combat/simulate` — Practice/sandbox match (requires 6 combat-ready heroes per team); optional `seed` (planned).
  - `GET /api/v1/battles/{id}` / `GET /api/v1/battles/{id}/log` — ✅ Result + replay log after completion.
- **Frontend Views**:
  - **[NEW]** ✅ Combat Replay Viewer UI (post-match only): Reads event-stream `combat_log`. Static match report showing final states and accordion round-by-round log.
- **Verification**:
  - ✅ Unit tests for combat math, seed + RNG-state reproducibility across wave messages, barrier ignoring `stalled`, hard stop at round 200.
  - ✅ Forfeit: <6 combat-ready → 3–0 / 0–0 without enqueueing waves.
- **Status**: ✅ Complete — 6.1a-d fully implemented.

### Step 6.2: Calendar & Server Ticks System
- **Database & Entities**: `KingdomTickLog` (implemented).
- **Service Layer**:
  - Timeline tick scheduler running weekly/hourly increments.
  - Action runners triggered by calendar ticks: passive economy income, training time updates, and league match executions.
- **API/CLI Contracts**:
  - `bin/console app:ticks:run` — Command triggered by cron to advance game time.
  - `GET /api/v1/kingdom/{id}/calendar` — Kingdom schedule feed.
- **Verification**: Trigger a calendar tick and check if queues (training, items, leagues) update.
- **Status**: ✅ Complete (`ProcessTicksCommand`, `TickScheduleCalculator`, `CalendarService`, `ExecuteSingleTickHandler`, Web Calendar page, kingdom calendar API). League match ticks process both arena revenue and start wave combat simulation.

### Step 6.3: League Matchmaking & Season Transition
- **Design Prerequisites (Phase 0)**: Resolve [known-issues.md](known-issues.md) #7 (Friendly match rules) and #8 (Arena Match mechanics).
- **Service Layer**:
  - Implement Berger’s Algorithm to generate 18 rounds of double round-robin fixtures.
  - Enforce home/away balance (1 home, 1 away match per week of play).
  - Standings updates: calculate played, wins, draws, losses, points, goal difference.
  - Season transition: process promotions, relegations, compound rewards (using global comparison tie-breakers). Shuffle groups for next season.
- **API Contracts**:
  - `GET /api/v1/league/standings` — standings list.
  - `GET /api/v1/league/fixtures` — matches list.
  - `GET /api/v1/league/seasons` — season history.
  - `POST /api/v1/league/process-season` — manual admin season trigger.
- **Frontend Views**:
  - **[NEW]** League Dashboard: Group standings table, fixture timeline, match summaries, and promotion/relegation threshold lines.
- **Verification**: Run complete 11-week season simulation using CLI commands and verify standings and reward distributions.
- **Status**: ✅ Complete (`LeagueFixtureScheduler`, `SeasonTransitionService`, `Api\V1\LeagueController`, and Web League Dashboard fully complete).

### Step 6.4: Hero Mortality & Graveyard
- **Database & Entities**: `GraveyardMemorial` entity (`graveyard` table).
- **Service Layer**: `GraveyardService` records memorial snapshots on hero/trainer dismissal and combat deaths.
- **API Contracts**: `GET /api/v1/graveyard`, `GET /api/v1/graveyard/{id}`.
- **Frontend Views**:
  - **[NEW]** Memorial Graveyard: Cemetery listing with filters (role, cause, race), summary stats, and memorial detail.
- **Verification**: Dismiss a hero/trainer or trigger combat death and verify memorial appears on `/app/graveyard` and via read API.
- **Status**: ✅ Complete (`GraveyardService`, dismissal flows, combat death memorial snapshots, Web UI, and read API fully implemented).

---

## Milestone 7: Arena Management & Operations
*Extend HQ stadium operations, ticket pricing, seating capacity upgrades, and financial attendance analytics.*

### Step 7.1: Arena Facility Management & Ticket Pricing
- **Database & Entities**: Link stadium upgrades directly to HQ Arena levels.
- **Service Layer**: Weekly seating capacity calculations, ticket price elasticity calculations, passive revenue distribution service (`ArenaRevenueService`, `FanClubService`).
- **API Contracts**: `GET /api/v1/arena`, `POST /api/v1/hq/arena/tickets/price`.
- **Frontend Views**:
  - HQ Arena facility panel (`/app/hq?facility=arena`), seating upgrade charts, ticket price sliders, and weekly attendance graphs.
- **Verification**: Modify ticket price, trigger weekly ticket revenue command, and confirm revenue scales with formulas.
- **Status**: 🔄 Partially Complete (`ArenaRevenueService`, league-match tick payout, HQ arena panel at `/app/hq?facility=arena`, ticket price API `POST /api/v1/hq/arena/tickets/price` implemented; extended analytics UI and friendly scheduling pending).

---

## Milestone 8: Endgame & Advanced Content (Deferred / Out of Scope)
> [!NOTE]
> **Status: DEFERRED / FUTURE FEATURE (Parked)**  
> All features in Milestone 8 are deferred and outside the active development scope. Detailed design specifications are preserved in the [`docs/future/`](future/) directory.

*Extend the sandbox with PvE dungeon encounters, quest systems, and item crafting.*

### Step 8.1: PvE Dungeon Encounters
- **Database & Entities**: `DungeonRun` table (depends on Combat). Design preserved in [future/dungeon-system.md](future/dungeon-system.md); no code in codebase yet.
- **Service Layer**: Monster roster generation, floor difficulty progression, chest loot generators.
- **API Contracts**: `POST /api/v1/dungeons/enter`, `POST /api/v1/dungeons/combat`.
- **Frontend Views**:
  - **[NEW]** Dungeon Map UI: Floor progression map, encounter cards, reward reveals.
- **Verification**: Complete dungeon floors, confirm health persistence across fights and reward logs.
- **Status**: ⏸️ Deferred / Out of Active Scope (design preserved in [future/dungeon-system.md](future/dungeon-system.md)).

### Step 8.2: Daily & Weekly Quest Systems
- **Design Reference**: [future/quest-system.md](future/quest-system.md)
- **Database & Entities**: `Quest` and `PlayerQuestProgress` tables (not scaffolded yet).
- **Service Layer**: Daily Quest pool generation, progress triggers (e.g., training ticks, marketplace trades), rewards allocation.
- **API Contracts**: `GET /api/v1/quests`, `POST /api/v1/quests/claim/{id}`.
- **Frontend Views**:
  - **[NEW]** Quest log: list of daily/weekly challenges, progress indicators, claim buttons.
- **Verification**: Perform quest conditions, verify progress bar fills, claim rewards.
- **Status**: ⏸️ Deferred / Out of Active Scope (design preserved in [future/quest-system.md](future/quest-system.md)).

### Step 8.3: Material Gathering & Crafting
- **Design Reference**: [future/crafting-system.md](future/crafting-system.md)
- **Database & Entities**: `CraftingRecipe` and `CraftingQueue` tables.
- **Service Layer**: Recipe unlocks, materials cost validation, crafting queue speed bonuses from HQ.
- **API/Web Controllers**: `POST /api/v1/crafting/start`, `DELETE /api/v1/crafting/queue/{id}`.
- **Frontend Views**:
  - **[NEW]** Crafting Workshop: Recipe catalog, required ingredients checklist, active crafting progress bars.
- **Verification**: Check material requirement validation, verify crafted items appear in the team inventory.
- **Status**: ⏸️ Deferred / Out of Active Scope (design preserved in [future/crafting-system.md](future/crafting-system.md)).

---

## Milestone 9: Alliances & Guild System (Deferred / Out of Scope)
> [!NOTE]
> **Status: DEFERRED / FUTURE FEATURE (Parked)**  
> Alliance and Guild systems are deferred and outside the active development scope.

*Establish alliances, team cooperation, and guild chat communication.*

### Step 9.1: Alliance Foundation & Management
- **Database & Entities**: `Alliance`, `AllianceMember` entities.
- **Service/Business Logic**: Alliance creation, invitations, membership application, roles/ranks, alliance leaderboards.
- **API Contracts**: `GET /api/v1/alliances`, `POST /api/v1/alliances/create`, `POST /api/v1/alliances/{id}/invite`, `POST /api/v1/alliances/{id}/join`.
- **Frontend Views**:
  - **[NEW]** Alliance Hub: Roster lists, application portals, alliance rank leaderboards, and settings page.
- **Verification**: Create an alliance, invite another team, accept the invitation, verify permissions and ranking.
- **Status**: ⏸️ Deferred / Out of Active Scope.

### Step 9.2: Alliance Communication
- **Database & Entities**: Uses existing communication entities (`Message` etc. scoped to Alliance).
- **Service/Business Logic**: Alliance-only chat persistence and filtering.
- **API Contracts**: `POST /api/v1/alliances/chat`, `GET /api/v1/alliances/chat/history`.
- **Frontend Views**:
  - **[NEW]** Alliance Chat Pane: Embedded live alliance chat feed within the Alliance Hub.
- **Verification**: Send chat messages within an alliance, confirm they are only visible to alliance members.
- **Status**: ⏸️ Deferred / Out of Active Scope.

---

## Cross-Cutting Infrastructures (Ongoing)

- **CI/CD Pipeline**: Setup GitHub Actions workflow to run PHPStan static analysis and PHPUnit tests automatically on pull requests.
- **Code Quality Tooling**: Maintain PHPStan configuration (level 6, zero errors limit) and automated PHP-CS-Fixer styling rules.
- **E2E Testing Suite**: Set up Playwright to cover registration, team selection, and training/summoning flows under realistic user action scenarios.

---

## Project Chronological Progression Status

> [!IMPORTANT]
> **Active Scope Guardrail:** Milestones 1–6 are fully complete. Milestone 7 (Arena Management) is the final active milestone. Everything after Milestone 7 (Milestones 8 and 9) is explicitly **deferred / parked**. Do not implement or plan work for items beyond Milestone 7.

The following matrix displays what has been completed in the codebase relative to the newly defined chronological steps:

| Milestone / Slice | Database / Entities | Service Layer / CLI | API Endpoints | Frontend UI Views | Current Status |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Milestone 1 (Auth & Kingdom)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 2 (Heroes & Economy)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 3 (HQ & Training)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 4 (Combat Prep)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 5 (Marketplace & Forum)**| ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 6 (Combat & Leagues)** | ✅ | ✅ | ✅ | ✅ | **Complete** |
| **Milestone 7 (Arena Management)** | ✅ | 🔄 | ✅ | 🔄 | **Active / Partially Complete** (arena revenue + ticket price API done; extended analytics UI & friendly scheduling pending) |
| **Milestone 8 (Endgame & Crafting)** | ⏳ | ⏳ | ⏳ | ⏳ | ⏸️ **Deferred / Out of Scope** (dungeons, crafting, quests in `future/`) |
| **Milestone 9 (Alliances)** | ⏳ | ⏳ | ⏳ | ⏳ | ⏸️ **Deferred / Out of Scope** (alliance & guild systems deferred) |

*Last updated: August 6, 2026 — Milestones 8 and 9 explicitly marked as Deferred / Out of Active Scope; Milestone 7 is the final active scope milestone.*
