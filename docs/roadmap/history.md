# Implementation Roadmap (Vertical-Slice & Chronological)

> [!IMPORTANT]
> Alignment waves 0–8 are **complete** ([current.md](current.md)). This file is the M1–9 chronicle, not a new work queue. Do not start Milestone 8/9.
> Milestones 1–6 shipped a **playable core**. ✅ on a step means that slice landed, **not** that no gaps remain (inventory DnD, formation synergies Cut, combat HUD extras Cut). Do not treat the matrix at the bottom as a green board.
> Milestones 8 and 9 stay **parked**. Milestone 7 analytics UI shipped as alignment A7.8.

Purpose: Historical, step-by-step chronicle of how the playable core was built (vertical slices). It is not the current work queue.

**AI agents:** Follow [AGENTS.md](../../AGENTS.md) for task routing. Alignment waves 0–8 have no remaining unchecked steps. Write tests when implementing a step; do not run PHPUnit, migrations, or other verification commands unless the user explicitly asks.

---

## Guiding Principles

- **Vertical Slice Development**: Implement database, service logic, API endpoints, and frontend views together for each feature. This ensures every step produces a runnable, visually testable increment.
- **Dependency Flow**: Do not skip ahead. Later milestones depend on the foundational entities and calculations established in earlier ones.
- **Aesthetic Excellence**: Every frontend step must align with the UI specifications (`docs/guides/ui-guidelines.md`), utilizing customized colors, typography, micro-animations, and responsive designs.
- **Verification**: Each step should include unit/integration tests where applicable and a clear manual walkthrough for humans. **Running** tests, linters, builds, and migrations is done by maintainers, CI, or when explicitly requested — AI agents write tests and follow [AGENTS.md](../../AGENTS.md); they do not execute verification commands to finish a task.

---

## Milestone 1: Authentication & Kingdom Setup
*Build the core user account systems and allow players to choose their starting Kingdom and auto-claim their initial team.*

### Step 1.1: Database Infrastructure & Schema Setup
- **Database & Entities**: Create init migration for base tables: `User`, `Kingdom`, `Team`, and session tables. Dual Doctrine connections (`default` and `legacy`) were wired as scaffolding; **`legacy` is parked** until the one-shot old-game import immediately before the first production deploy (A8.4). Live runtime uses `default` only.
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
- **Database & Entities**: Add currency column (`gold`) to `Team`. Create `FinancialRecord` logs.
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
- **Frontend Views**: Hero-detail Equipment tab, click-to-equip paperdoll (`equipment_controller.js`). `/app/inventory` redirects there.
- **Verification**: Equip items to matching slots, verify stat modifier calculations, dismantle items.
- **Status**: ✅ Core complete (`ItemService`, `Web\ItemController`, Stimulus: `equipment_controller.js`). **Not shipped:** inventory **drag-and-drop** grid (click-to-equip only). Occupancy (two-handed / dual-wield / two rings) locked A7.3 / [#8](../product/known-issues.md). Enchanting is not in the live game (A7.4 Cut / [#2](../product/known-issues.md) Resolved).

### Step 4.2: Spellbooks & Magic Learning
- **Database & Entities**: `Spell` and `SpellMastery` entities.
- **Service Layer**: Magic schools mastery levels, learning spells requirements, equipping.
- **API Contracts**: `POST /api/v1/heroes/{id}/spells/learn`, `POST /api/v1/heroes/{id}/spells/equip`, `POST /api/v1/heroes/{id}/spells/unequip`.
- **Frontend Views**: Spell learning dashboard, magic slots assignment list.
- **Verification**: Learn spells using gold, equip them, and confirm slot limits are respected.
- **Status**: ✅ Complete (`SpellService`, `Web\SpellController`, Stimulus: `spellbook_controller.js`).

### Step 4.3: Strategic Formations
- **Database & Entities**: `Formation` layout.
- **Service Layer**: Lineup configuration (3 front, 3 back slots). Engine may read per-slot `target_order` / spell conditions when present; **race-synergy tables are Cut** ([known-issues #9](../product/known-issues.md) Resolved / A7.5). Team chemistry is flavor for matches ([#7](../product/known-issues.md) Resolved / A7.2).
- **API Contracts**: `PUT /api/v1/formations` (save/update formation), `DELETE /api/v1/formations/{id}`.
- **Frontend Views**: Drag-and-drop **lineup** grid (formation page). Per-slot L1/L2 tactics editor is live (A7.6 / [#12](../product/known-issues.md)); action-sequence selector stays L3 deferred.
- **Verification**: Move heroes between positions, verify front/back line constraints (max 6 active).
- **Status**: ✅ Lineup CRUD complete (`FormationService`, `Web\FormationController`, Stimulus: `formation_controller.js`). **Cut:** formation **synergies** (A7.5 / [#9](../product/known-issues.md) Resolved). Save allows 0–6 slots and rejects duplicate `hero_id` ([#10](../product/known-issues.md) / A6.1). Kickoff: assigned → default → roster fill; forfeit if < 6 combat-ready.

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
- **Status**: ✅ Complete (Entities updated, services and API controllers implemented, and templates/controllers refactored per UI guidelines). Forum `is_pinned` is stored and shown when true, but **unsettable** (A6.3 Cut; no pin API).

---

## Milestone 6: Combat Simulation, Calendar & Leagues
*Implement the core combat engine, chronological event tick scheduler, the weekly league competition, and hero mortality.*

### Step 6.1: Combat Simulation Engine (Core Block)
- **Design Prerequisites (Phase 0)**: Locked in [combat-system.md](../systems/combat-system.md#simulation-contract) — VOs, event-stream `combat_log`, seed, **wave Messenger orchestration** (cohort lockstep, `MAX_ROUNDS=200`, no wave timeout, `stalled` isolation, no live UI), L0→L2 AI. NPC teams use the same combat path.
- **Service/Business Logic** (phased — see combat-system § Implementation phases):
  - **6.1a-0** — ✅ Contract VO + thin one-shot `CombatEngine` / `LeagueMatchSimulator` (placeholder scores + envelope).
  - **6.1a** — ✅ Persisted run state; `CombatWave` / `ProcessCombatRound` / `CompleteBattle`; barrier without timeout; `stalled` + `ResumeCombat`; real per-round turn loop + L0; replace one-shot league binding.
  - **6.1b** — ✅ L1 targeting (`strategy.target_order`); freeze slot JSON schema ([formation-system.md](../systems/formation-system.md#strategy-json-schema-phased)).
  - **6.1c** — ✅ L2 spell conditions; **post-match** replay viewer MVP (not live).
  - **6.1d** — ✅ Combat deaths → aging → graveyard; item durability loss after battle.
  - ✅ Status effects per tick (speed order). Post-match team morale, mastery XP, hero XP, form, fatigue, durability, and aging ship with completion.
- **API Contracts**:
  - `POST /api/v1/combat/simulate` — Practice/sandbox match (requires 6 combat-ready heroes per team); optional `seed` (**planned**; A7.5 Cut from live leftovers).
  - `GET /api/v1/battles/{id}` / `GET /api/v1/battles/{id}/log` — ✅ Result + replay log after completion.
- **Frontend Views**:
  - **[NEW]** ✅ Combat Replay Viewer UI (post-match only): Reads event-stream `combat_log`. Static match report showing final states and accordion round-by-round log.
- **Verification**:
  - ✅ Unit tests for combat math, seed + RNG-state reproducibility across wave messages, barrier ignoring `stalled`, hard stop at round 200.
  - ✅ Forfeit: <6 combat-ready → 3–0 / 0–0 without enqueueing waves.
- **Status**: ✅ Core 6.1a–d is in the tree, and follow-up steps 2–7 are in (L0 spell pick, defensive targeting, stat formulas, hero XP/form/fatigue, replay fidelity, full AP movement). Preparation length and fumble-without-retarget stay as designed. Replay HUD, Haste/Shock initiative sort, and leftover traits (morale / synergy / simulate Cut) are locked [#9](../product/known-issues.md) Resolved / A7.5.

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
- **Design Prerequisites (Phase 0)**: Friendly-match rules and arena ticket revenue are specified in [friendly-match-system.md](../systems/friendly-match-system.md) and [headquarters-system.md](../systems/headquarters-system.md). Attendance analytics UI later shipped as A7.8 ([product/known-issues.md](../product/known-issues.md) #4 Resolved).
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
  - HQ Arena facility panel (`/app/hq?facility=arena`): ticket price and attendance analytics (last 18 payouts — table + chart + summary). **Cut / not live:** seating-upgrade charts, standalone `/app/arena` page, combat home-advantage (A7.8 / [#4](../product/known-issues.md) Resolved).
- **Verification**: Modify ticket price, trigger weekly ticket revenue command, and confirm revenue scales with formulas.
- **Status**: ✅ Complete (`ArenaRevenueService`, league-match tick payout, HQ arena panel, ticket price API, friendly match scheduling at `/app/friendly-matches`, and attendance analytics UI — last 18 payouts, A7.8 / [#4](../product/known-issues.md) Resolved).

---

## Milestone 8: Endgame & Advanced Content (Deferred / Out of Scope)
> [!NOTE]
> **Status: DEFERRED / FUTURE FEATURE (Parked)**  
> All features in Milestone 8 are deferred and outside the active development scope. Detailed design specifications are preserved in the [`docs/future/`](../future) directory.

*Extend the sandbox with PvE dungeon encounters, quest systems, and item crafting.*

### Step 8.1: PvE Dungeon Encounters
- **Database & Entities**: `DungeonRun` table (depends on Combat). Design preserved in [future/dungeon-system.md](../future/dungeon-system.md); no code in codebase yet.
- **Service Layer**: Monster roster generation, floor difficulty progression, chest loot generators.
- **API Contracts**: `POST /api/v1/dungeons/enter`, `POST /api/v1/dungeons/combat`.
- **Frontend Views**:
  - **[NEW]** Dungeon Map UI: Floor progression map, encounter cards, reward reveals.
- **Verification**: Complete dungeon floors, confirm health persistence across fights and reward logs.
- **Status**: ⏸️ Deferred / Out of Active Scope (design preserved in [future/dungeon-system.md](../future/dungeon-system.md)).

### Step 8.2: Daily & Weekly Quest Systems
- **Design Reference**: [future/quest-system.md](../future/quest-system.md)
- **Database & Entities**: `Quest` and `PlayerQuestProgress` tables (not scaffolded yet).
- **Service Layer**: Daily Quest pool generation, progress triggers (e.g., training ticks, marketplace trades), rewards allocation.
- **API Contracts**: `GET /api/v1/quests`, `POST /api/v1/quests/claim/{id}`.
- **Frontend Views**:
  - **[NEW]** Quest log: list of daily/weekly challenges, progress indicators, claim buttons.
- **Verification**: Perform quest conditions, verify progress bar fills, claim rewards.
- **Status**: ⏸️ Deferred / Out of Active Scope (design preserved in [future/quest-system.md](../future/quest-system.md)).

### Step 8.3: Material Gathering & Crafting
- **Design Reference**: [future/crafting-system.md](../future/crafting-system.md)
- **Database & Entities**: `CraftingRecipe` and `CraftingQueue` tables.
- **Service Layer**: Recipe unlocks, materials cost validation, crafting queue speed bonuses from HQ.
- **API/Web Controllers**: `POST /api/v1/crafting/start`, `DELETE /api/v1/crafting/queue/{id}`.
- **Frontend Views**:
  - **[NEW]** Crafting Workshop: Recipe catalog, required ingredients checklist, active crafting progress bars.
- **Verification**: Check material requirement validation, verify crafted items appear in the team inventory.
- **Status**: ⏸️ Deferred / Out of Active Scope (design preserved in [future/crafting-system.md](../future/crafting-system.md)).

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

## Cross-Cutting Infrastructures

These shipped with the playable core or during alignment. They are **not** an open “ongoing” backlog.

- **CI/CD Pipeline**: PHPStan, UI compliance, backend-docs, and **PHPUnit** (`composer test` via `.github/workflows/phpunit.yml`) run on pull requests that touch matching paths. Deploy waits on the same jobs (A8.1).
- **Code Quality Tooling**: PHPStan configuration (level 8) and PHP-CS-Fixer rules are in the repo.
- **Browser E2E**: **Cut** (A8.2). Playwright is not installed and is not a live claim. Automated tests are PHPUnit. A later product decision is required before a browser suite.
- **Liveness**: `GET /health` returns `{ "status": "ok" }` without auth, session, or secrets (A8.3). Compose `web` probes that path. Not a database readiness probe.
- **Legacy DB connection**: **Parked** (A8.4). `legacy` Doctrine connection + unused EM stay in `config/packages/doctrine.yaml` for a one-shot import immediately before the first production deploy. No live service injects them. Guide: [`_deferred/legacy-migration.md`](../../_deferred/legacy-migration.md).
- **Public wiki**: **Planned** (A8.5). No `/wiki` controller. Live public content is homepage + `/news`. A later product decision is required before a wiki CMS or help pages.
- **Password change / extra settings**: **Planned** (A8.6). Live account settings are language, backdrop-close, email change, and delete. Forgotten passwords use `/password-reset`. A later product decision is required before session password change, notification prefs, or privacy.

---

## Project Chronological Progression Status

> [!IMPORTANT]
> **Active work:** [current.md](current.md) (alignment waves), not new Milestone 8/9 features and not “M1–6 fully complete.”
> This matrix is a **chronicle of slices that shipped a playable core**. Remaining honesty footnotes: inventory is click-to-equip (not DnD); formation synergies and combat HUD extras were Cut in Wave 7. PHPUnit in CI is A8.1 (done). Playwright browser E2E is A8.2 **Cut**. `GET /health` is A8.3 (done). Legacy Doctrine connection is A8.4 **Parked** (one-shot import before first prod). Public wiki is A8.5 **Planned**. Password change / extra settings are A8.6 **Planned**. XP→level is A7.1; chemistry is flavor for matches (A7.2); occupancy is A7.3. Milestone 7 analytics UI is A7.8 (done). Alignment waves 0–8 complete. Milestones 8 and 9 stay **deferred / parked**.

| Milestone / Slice | Database / Entities | Service Layer / CLI | API Endpoints | Frontend UI Views | Current Status |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Milestone 1 (Auth & Kingdom)** | ✅ | ✅ | ✅ | ✅ | **Shipped core** — live contract in [systems/README.md](../systems/README.md) |
| **Milestone 2 (Heroes & Economy)** | ✅ | ✅ | ✅ | ✅ | **Shipped core** — XP→level later in A7.1 ([#6](../product/known-issues.md)) |
| **Milestone 3 (HQ & Training)** | ✅ | ✅ | ✅ | ✅ | **Shipped core** |
| **Milestone 4 (Combat Prep)** | ✅ | ✅ | ✅ | 🔄 | **Shipped core with gaps** — equipment is click-to-equip, **not** inventory DnD (4.1); formation synergies **Cut** (4.3 / [#9](../product/known-issues.md)); occupancy locked [#8](../product/known-issues.md) |
| **Milestone 5 (Marketplace & Forum)**| ✅ | ✅ | ✅ | ✅ | **Shipped core** — forum pin stored/shown but **unsettable** (A6.3 Cut) |
| **Milestone 6 (Combat & Leagues)** | ✅ | ✅ | ✅ | ✅ | **Shipped core (replay combat)** — [#9](../product/known-issues.md) Resolved (HUD + Haste/Shock locked; morale / synergy / simulate Cut) |
| **Milestone 7 (Arena Management)** | ✅ | ✅ | ✅ | ✅ | **Shipped core** — revenue, ticket price API, friendlies, HQ attendance analytics ([#4](../product/known-issues.md) Resolved / A7.8). Combat home-advantage stays planned. |
| **Milestone 8 (Endgame & Crafting)** | ⏳ | ⏳ | ⏳ | ⏳ | ⏸️ **Deferred / parked** (dungeons, crafting, quests in `future/`) |
| **Milestone 9 (Alliances)** | ⏳ | ⏳ | ⏳ | ⏳ | ⏸️ **Deferred / parked** |

*Last updated: 16 October 2026 — Alignment waves 0–8 complete. This file is the M1–9 chronicle, not a work queue. Leftovers: [current.md](current.md) and each `systems/*.md` Remaining work.*
