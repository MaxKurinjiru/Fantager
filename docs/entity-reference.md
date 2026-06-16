# Entity Reference

Purpose: Canonical list of all database entities, config-based data, and structural decisions.

Reference: Derived from [game-summary.md](game-summary.md), system docs, and screen docs.

---

## Design Decisions


| Decision                                                              | Rationale                                                                                                                                                                                                                                                           |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **VerificationToken** replaces separate email/password-reset tokens   | Identical mechanic (token, user, expiry, one-time). Differentiated by `type` field.                                                                                                                                                                                 |
| **Race** as PHP enum + config YAML, not DB entity                     | 8 races (`human, elf, dwarf, orc, undead, giant, ent, genie`), static data, never changes at runtime.                                                                                                                                                              |
| **RaceRelationship** as config matrix, not DB                         | 28 unique pairs, static, read-only game data.                                                                                                                                                                                                                       |
| **StatusEffect** as enum + value objects                              | Defined by code (Burn, Stun, etc.), not user-created.                                                                                                                                                                                                               |
| **DungeonEnemy** as YAML/JSON config                                  | Static game content, loaded into combat engine.                                                                                                                                                                                                                     |
| **CraftingMaterial** = Item with `category=material`                  | No separate entity needed.                                                                                                                                                                                                                                          |
| **SeasonReward** as JSON on LeagueTier or config                      | Design-defined, not player-created.                                                                                                                                                                                                                                 |
| **PlayerWallet** as columns on Team                                   | 1:1, no reason for extra table.                                                                                                                                                                                                                                     |
| **FinancialRecord** as audit log for Team finances                    | Separate entity to record all gold and essence changes (income and expenses), capturing context and actor details.                                                                                                                                       |
| **SummonCooldown** as columns on Team                                 | 1:1, few fields.                                                                                                                                                                                                                                                    |
| **ItemAttributeBonus** as JSON column on Item                         | Always read as whole; no cross-item queries needed.                                                                                                                                                                                                                 |
| **FormationHeroStrategy** as JSON on FormationSlot                    | Complex per-slot config, never queried across slots.                                                                                                                                                                                                                |
| **FormationSpellPriority** as JSON on FormationSlot                   | Same reasoning.                                                                                                                                                                                                                                                     |
| **NotificationPreference** | Dedicated **`UserSettings`** table (`auth_user_settings`), 1:1 with User | UI/interface preferences (modal backdrop, future toggles). Notification email/in-game prefs still **planned**. |
| **HeroTrainingHistory** as append-only weekly training log            | One row per hero trained during each weekly tick. Used for hero recent activity and calendar history until `team_chronicle.training_completed` is wired.                                                                                                              |
| **Leaderboard** = SQL view / computed                                 | Derived from LeagueStanding, not stored separately.                                                                                                                                                                                                                 |
| **PlayerProfile** = projection of User+Team                              | Not a DB entity.                                                                                                                                                                                                                                                    |
| **NPC Team** as `is_npc = true` on Team entity                        | No separate entity needed; NPC and player teams share all fields. `user_id` is NULL for NPC teams.                                                                                                                                                                  |
| **Kingdom initialization** creates all NPC teams + first LeagueSeason | Ensures a full league bracket exists before any player joins.                                                                                                                                                                                                       |
| `**display_name` + `display_name_slug`** both on User                 | `display_name` is stored and displayed exactly as entered. `display_name_slug` is the webalized form (lowercase, diacritics stripped, non-alphanumeric replaced with `-`) and carries a unique index — used solely for collision detection.                         |
| **TeamChronicle** as single generic table (team chronicle)              | One table (`team_chronicle`) covers all game event types, scoped by **`team_id`** (not `user_id`). `type` (enum) enables filtering; `data` (JSON) holds event-specific context. `subject_key` + `subject_params` (JSON) allow translated rendering at display time in the player's locale. Append-only team history across NPC periods and multiple managers. See [team-chronicle-system.md](systems/team-chronicle-system.md). |
| `**league_tiers_config` JSON on Kingdom**                             | Configurable per kingdom; total player capacity is derived as `sum(tier.groups) × teams_per_group`. Eliminates redundant `max_players` field.                                                                                                                       |
| **Team `morale` default value = 50**                                  | Range 0–100; 50 represents neutral/mid morale. Applied at team creation and on hero transfer/sell.                                                                                                                                                                  |
| `**Facility.passive_bonuses` design**                         | In DB, `Facility` uses a `metadata` (JSON) field. The passive bonuses are computed using `getPassiveBonuses()`, combining `metadata` with static bonuses defined per `FacilityType` level.                                                                         |
| `**Hero.intel` column name**                                          | PHP property and DB column named `intel` instead of `int` — `int` is a PHP reserved word and cannot be used as an identifier.                                                                                                                                       |
| `**MarketplaceListing` polymorphic entity reference**                 | Three nullable FK columns (`hero_id`, `item_id`, `trainer_id`) — only one set per row based on `listing_type`. Provides DB-level referential integrity without a generic polymorphic pattern. Application layer enforces mutual exclusivity.                        |
| **Database Table Naming Convention**                                  | Main/root entities (e.g. `team`, `kingdom`, `headquarters`, `hero`, `formation`, `spell`, `item`, `event`, `notification`, `news_article`) do NOT have domain prefixes. Sub-entities (e.g. `headquarters_facility`, `team_financial_record`, `team_chronicle`, `team_summon_history`, `formation_slot`, `hero_school_mastery`, `hero_training_history`, **`auth_user_settings`**) or entities with SQL reserved keyword conflicts (e.g. `user` -> `auth_user`, `verification_token` -> `auth_verification_token`, `battle` -> `combat_battle`, `message` -> `community_message`) are prefixed with their domain namespace. |

---

## Domain Boundaries (Combat vs Arena)

**Combat** and **Arena** are separate game concepts. Controllers may use user-facing names (`ArenaController`, `/app/arena`) while services follow entity ownership.

| Concept | Entity / data | Service namespace | Responsibility |
| ------- | ------------- | ----------------- | -------------- |
| **Combat** | `App\Entity\Combat\Battle` (`combat_battle`) | `App\Service\Combat` *(planned)* | Turn-based battle simulation, combat log, post-match XP/form/fatigue |
| **Arena facility** | `Headquarters` + `Facility` (`FacilityType::Arena`) | `App\Service\Headquarters\ArenaService` | Arena level, seating capacity, fan appeal, next-home-match projection |
| **Arena revenue** | `FinancialRecord` (`arena_revenue`) | `App\Service\Economy\ArenaRevenueService` | Ticket payout on league match tick, attendance calculation |

- `ArenaController` stays under `Controller` — it is the player-facing screen name; Web route redirects to `/app/hq?facility=arena`.
- `MatchType::Arena` is a match category consumed by the combat engine, not by `ArenaService`.
- When the combat engine ships (Milestone 6), add `App\Service\Combat\BattleSimulationService` etc.; do not fold it into `ArenaService`.

---

## Database Entities (34 implemented)

### 1. Auth Domain


| Entity                | Key Fields                                                                                                      | Relationships         |
| --------------------- | --------------------------------------------------------------------------------------------------------------- | --------------------- |
| **User**              | id, email, password_hash, is_verified, roles[], kingdom_id, locale, display_name, display_name_slug, created_at | N:1 Kingdom, 1:1 Team, 1:1 UserSettings |
| **UserSettings**      | id, user_id, close_modal_on_backdrop, updated_at                                                                | 1:1 User (CASCADE DELETE) |
| **VerificationToken** | id, user_id, token, type (email_verify / password_reset / …), expires_at, used_at                             | → User                |


### 2. Kingdom Domain


| Entity      | Key Fields                                                                                                                                        | Relationships        |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| **Kingdom** | id, name, language, timezone, game_speed, marketplace_tax_rate, season_length, league_tiers_config (JSON), level_cap, xp_modifier, crafting_boost | 1:N Users, 1:N Teams |
| **KingdomTickLog** | id, kingdom_id, tickType (enum), scheduledAt, status (enum), errorMessage, executedAt                                                             | → Kingdom (N:1)      |


### 3. Team Domain


| Entity   | Key Fields                                                                                                                                                                                                                                           | Relationships                                                |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| **Team**            | id, user_id (nullable), kingdom_id, name, emblem, colors, morale, reputation, chemistry, fan_base, gold, essence_common–mythic, is_npc, last_summon_at, summons_this_cycle, **unpaid_debt**, **crisis_weeks**, **last_recovery_action_at** | → User (N:1, nullable — NULL for NPC teams), → Kingdom (N:1) |
| **FinancialRecord** | id, team_id, type (enum), actor (enum), gold_change, essence_common_change, essence_uncommon_change, essence_rare_change, essence_epic_change, essence_legendary_change, essence_mythic_change, context (JSON), created_at | → Team (N:1)                                                 |
| **TeamChronicle**   | id, team_id, type (`ChronicleEventType` enum), subject_key, subject_params (JSON), data (JSON), created_at | → Team (N:1)                                                 |
| **TeamSummonHistory** | id, team_id, race_selected, hero_id, gold_cost, summoned_at | → Team (N:1), → Hero (N:1)                                   |


### 4. Hero Domain


| Entity            | Key Fields                                                                                                                                     | Relationships                              |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| **Hero**          | id, team_id, name, race (enum), level, xp, age, form, fatigue, morale, magic_capacity, str, dex, kon, spd, intel, wil, cha, lck, status (enum) | → Team, has many HeroSpell, equipped Items |
| **SchoolMastery** | id, hero_id, school (enum), mastery_tier                                                                                                       | → Hero                                     |
| **HeroSpell**     | id, hero_id, spell_id, is_equipped, slot_number                                                                                                | → Hero, → Spell                            |


### 5. Training Domain


| Entity                  | Key Fields                                                                                                                                              | Relationships                |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| **Hero (Trainer role)** | Trainers are heroes with `role = trainer`. Fields: `training_type` (nullable enum), `target_attribute` (nullable string), trainee assignments via `hero.trainer_id` | → Team, has many trainee Heroes |
| **HeroTrainingHistory** | id, hero_id, training_type (enum), target_attribute (nullable), trainer_id (nullable), stat_gain (nullable), completed_at                               | → Hero (trainee), → Hero (trainer, optional). Append-only log written after each weekly training tick |


### 6. Formation Domain


| Entity            | Key Fields                                                                           | Relationships                  |
| ----------------- | ------------------------------------------------------------------------------------ | ------------------------------ |
| **Formation**     | id, team_id, name, is_default, is_temporary, source_fixture_id (nullable), approach (enum) | → Team, → LeagueFixture (optional), has many FormationSlot |
| **FormationSlot** | id, formation_id, hero_id, position (enum), strategy (JSON), spell_priorities (JSON) | → Formation, → Hero            |


### 7. Headquarters Domain


| Entity           | Key Fields                                                      | Relationships  |
| ---------------- | --------------------------------------------------------------- | -------------- |
| **Headquarters** | id, team_id, total_level, race_optimization, pending_race_optimization (nullable string), has_pending_race_optimization_change (bool), race_optimization_lock_cycle (bool), upgrading_facility_id (nullable FK), upgrade_completed_at (nullable datetime) | → Team (1:1)   |
| **Facility**     | id, headquarters_id, type (enum), level, metadata (JSON) | → Headquarters |


### 8. Item Domain


| Entity   | Key Fields                                                                                                                                                     | Relationships                |
| -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| **Item** | id, owner_team_id, equipped_hero_id, equipped_slot, name, slot_type (enum), category (enum), rarity (enum), durability, status (enum), bonuses (JSON), special_effects (JSON) | → Team, → Hero (if equipped) |


### 9. Spell Domain


| Entity    | Key Fields                                                                                                                                        | Relationships           |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- |
| **Spell** | id, name, school (enum), tier, type (enum), effects (JSON), mana_cost, cooldown, required_mastery_tier, learning_cost_gold, learning_cost_essence | referenced by HeroSpell |


### 10. Combat Domain


| Entity     | Key Fields                                                                                                                                                                                  | Relationships                    |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| **Battle** | id, kingdom_id, match_type (enum), team_a_id, team_b_id, formation_a_id, formation_b_id, result (enum), score_a, score_b (kill score 0–6 each), combat_log (JSON), xp_awarded, processed_at | → Kingdom, → Teams, → Formations |


### 11. League Domain


| Entity             | Key Fields                                                                       | Relationships                    |
| ------------------ | -------------------------------------------------------------------------------- | -------------------------------- |
| **LeagueSeason**   | id, kingdom_id, season_number, start_date, end_date, status (enum)               | → Kingdom                        |
| **LeagueTier**     | id, season_id, tier_name, promotion_slots, relegation_slots, rewards (JSON)      | → LeagueSeason                   |
| **LeagueGroup**    | id, tier_id, group_name                                                          | → LeagueTier                     |
| **LeagueStanding** | id, group_id, team_id, played, wins, draws, losses, points, goal_difference      | → LeagueGroup, → Team            |
| **LeagueFixture**  | id, group_id, home_team_id, away_team_id, home_formation_id (nullable), away_formation_id (nullable), scheduled_at, battle_id, status (enum) | → LeagueGroup, → Teams, → Formations (optional), → Battle |


### 12. Marketplace Domain


| Entity                 | Key Fields                                                                                                                                                                     | Relationships      |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------ |
| **MarketplaceListing** | id, kingdom_id, seller_team_id, listing_type (enum), hero_id (nullable), item_id (nullable), trainer_id (nullable), price_gold, buyout_price_gold (nullable int), listing_mode (enum), expires_at, status (enum) | → Kingdom, → Team  |
| **MarketplaceBid**     | id, listing_id, bidder_team_id, bid_amount, bid_time                                                                                                                           | → Listing, → Team  |
| **MarketplaceTransaction** | id, buyer_team_id, seller_team_id, listing_id, amount, fee_amount, type (enum), created_at                                                                                 | → Teams, → Listing |


### 16. Crafting Domain (deferred — not in codebase)


| Entity             | Key Fields                                                                                                                                                                         | Relationships            |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------ |
| **CraftingRecipe** | id, result_item_category, result_item_rarity, required_materials (JSON), essence_cost_type, essence_cost_amount, gold_cost, success_rate_base, crafting_time, required_forge_level | —                        |
| **CraftingQueue**  | id, team_id, recipe_id, status (enum), started_at, completes_at                                                                                                                    | → Team, → CraftingRecipe |


### 17. Community Domain


| Entity              | Key Fields                                                             | Relationships         |
| ------------------- | ---------------------------------------------------------------------- | --------------------- |
| **Message**         | id, sender_team_id, receiver_team_id, subject, body, read_at, sent_at, deleted_by_sender (bool), deleted_by_receiver (bool)  | → Teams               |
| **NewsArticle**     | id, kingdom_id, title, content, published_at                           | → Kingdom (optional)  |
| **ForumThread**     | id, kingdom_id, category, title, author_team_id, created_at, is_pinned, is_locked (bool) | → Kingdom, → Team     |
| **ForumPost**       | id, thread_id, author_team_id, body, created_at                        | → ForumThread, → Team |


### 18. Graveyard Domain


| Entity              | Key Fields                                                                                                                                                | Relationships |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- |
| **GraveyardMemorial** | id, team_id, name, race, role_at_departure, cause, age, final_level, final_stats (JSON), departed_at, original_hero_id | → Team        |


### 19. Notification Domain


| Entity           | Key Fields                                                 | Relationships |
| ---------------- | ---------------------------------------------------------- | ------------- |
| **Notification** | id, user_id, type (enum), title, body, is_read, created_at | → User        |


### 20. Team Chronicle (append-only event log)

Table: `team_chronicle` — entity `App\Entity\Team\TeamChronicle`.

| Entity          | Key Fields                                                                                              | Relationships |
| --------------- | ------------------------------------------------------------------------------------------------------- | ------------- |
| **TeamChronicle** | id, team_id, type (`ChronicleEventType` enum), subject_key, subject_params (JSON), data (JSON), created_at | → Team        |


- `type` — enum value used for filtering and grouping (e.g. ownership, competition, roster).
- `subject_key` — Symfony translation key (e.g. `activity.player_joined`, `activity.season_ended`); rendered in the player's locale at display time via `TeamChroniclePresenter`.
- `subject_params` — JSON map of parameters injected into the translation string (e.g. `{"player": "Alice"}`, `{"season": "1", "tier": "T1"}`).
- `data` — full machine-readable context for detailed views or future processing (e.g. `user_id`, `hero_id`, `gold`, `reason`).
- Entries are **append-only**; never updated or deleted individually (bulk retention pruning planned, not implemented).
- **Write path:** `TeamChronicleService` only (do not persist `TeamChronicle` directly from feature services).
- **Read path:** `TeamChronicleRepository` + `TeamChroniclePresenter`; dashboard shows last 5 entries; full history at `GET /app/chronicle`.
- **Implemented types today:** `team_established`, `player_joined`, `player_released`, `season_ended`, `summon_completed`. Other enum values are reserved for upcoming features (combat, marketplace, etc.).

See [team-chronicle-system.md](systems/team-chronicle-system.md) for full behaviour.

---

## Config-Based Data (not in DB)


| Name                    | Storage                                                 | Contents                                                  |
| ----------------------- | ------------------------------------------------------- | --------------------------------------------------------- |
| **Race definitions**    | PHP enum (`Race`) + `config/game/races.yaml`            | 8 races (human, elf, dwarf, orc, undead, giant, ent, genie), stat bonuses, age thresholds, training modifiers |
| **Race relationships**  | `config/game/race_relations.yaml`                       | 8×8 matrix (28 unique pairs)                              |
| **Status effects**      | PHP enum + value objects                                | Burn, Stun, Heal, Buff types — used by combat engine      |
| **Dungeon definitions** | `config/game/dungeons/*.yaml`                           | Enemy stats, abilities, loot tables, scaling              |
| **Season rewards**      | JSON on LeagueTier or `config/game/season_rewards.yaml` | Per-tier position rewards                                 |


---

## Enums (PHP backed enums)


| Enum                | Values                                                                                                                                                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Race`              | human, elf, dwarf, orc, undead, giant, ent, genie                                                                                                                                                                                                                 |
| `HeroStatus`        | available, in_match, selling, recovering, dead                                                                                                                                                                                               |
| `School`            | fire, water, air, earth, light, dark                                                                                                                                                                                                              |
| `SpellType`         | offensive, defensive, utility                                                                                                                                                                                                                     |
| `ItemSlotType`      | main_hand, off_hand, head, body, hands, feet, amulet, ring                                                                                                                                                                                          |
| `ChronicleEventType`   | team_established, player_joined, player_released, battle_win, battle_loss, battle_draw, hero_levelup, hero_died, hero_retired, training_completed, item_purchased, item_sold, dungeon_completed, summon_completed, season_ended |
| `ChronicleReleaseReason` | inactivity, bankruptcy, unverified_registration, account_deleted (stored in `data.reason`; used with `player_released`) |
| `ChronicleCategory` | all, ownership, competition, roster, economy (UI filter groups; not stored on rows) |
| `ItemCategory`      | weapon, shield, spell_accelerator, armor, accessory, material                                                                                                                                                                                      |
| `ItemRarity`        | common, uncommon, rare, epic, legendary, mythic                                                                                                                                                                                                   |
| `ItemStatus`        | available, selling                                                                                                                                                                                                                                |
| `FormationPosition` | front_1, front_2, front_3, back_1, back_2, back_3                                                                                                                                                                                                 |
| `FormationApproach` | aggressive, balanced, defensive                                                                                                                                                                                                                   |
| `MatchType`         | league, friendly, dungeon, arena                                                                                                                                                                                                                  |
| `BattleResult`      | win_a, win_b, draw                                                                                                                                                                                                                                |
| `TrainingType`      | attribute, magic, form                                                                                                                                                                                                                            |
| `ListingType`       | hero, item, trainer                                                                                                                                                                                                                               |
| `ListingMode`       | buy_now, auction                                                                                                                                                                                                                                  |
| `ListingStatus`     | active, sold, expired, cancelled                                                                                                                                                                                                                  |
| `TokenType`         | email_verify, password_reset, change_email_old, change_email_new, delete_account                                                                                                                                                                  |
| `FacilityType`      | training, medical, library, forge, treasury, barracks, summoning_chamber, arena                                                                                                                                                                    |
| `StatusEffect`      | burn, freeze, shock, petrify, blind, curse, stun, poison, shield, regeneration, haste, bless, fury, shadow_cloak, taunt, silence                                                                                                                  |
| `TickType`          | daily_reset, fatigue_recovery, league_match, weekly_training, season_transition, weekly_reset, race_optimization, inactive_registration_cleanup, inactive_player_cleanup                                                                                                                                   |
| `LeagueSeasonStatus` | scheduled, active, completed                                                                                                                                                                                                                      |
| `LeagueFixtureStatus` | scheduled, in_progress, completed, cancelled                                                                                                                                                                                                     |
| `TrainerStatus`     | active, retired, dead                                                                                                                                                                                                                             |
| `DungeonResult`     | win, loss, abandoned *(deferred — not in codebase)*                                                                                                                                                                                              |
| `CraftingStatus`    | pending, in_progress, completed, failed, cancelled                                                                                                                                                                                                |
| `TransactionType`   | buy_now, auction_win                                                                                                                                                                                                                              |
| `NotificationType`  | battle_result, training_complete, league_update, marketplace_bid, marketplace_sold, event_started, hero_died, season_ended, **system**                                                                                   |
| `FinancialRecordType` | league_reward, arena_revenue, summon_fee, marketplace_sale, marketplace_purchase, marketplace_fee, dungeon_reward, dismantle_gain, item_repair, spell_learning_cost, spell_slot_cost, hq_upgrade_cost, **hq_maintenance_fee**, morale_restoration, **debt_repayment**, **hero_dismissal_compensation**, **hq_downgrade_refund** |
| `FinancialCrisisLevel` | **none**, **warning**, **restricted**, **bankruptcy_pending** |
| `FacilityOperation` | **upgrade**, **downgrade** |
| `FinancialRecordActor` | system, active, passive                                                                                                                                                                                                                        |

---

## Entity Count Summary


| Category                   | Count  |
| -------------------------- | ------ |
| DB entities (implemented) | 34     |
| Config-based (not DB)      | 5      |
| PHP enums                  | 30     |
| **Total modeled concepts** | **68** |


---

## Deferred Concepts

World events (`Event`, `EventParticipation`, `EventType`, `EventStatus`) are not implemented. See [future/world-events-system.md](future/world-events-system.md).

Dungeon runs (`DungeonRun`, table `dungeon_run`) were removed from the codebase. See [future/dungeon-system.md](future/dungeon-system.md).

Crafting (`CraftingRecipe`, `CraftingQueue`) is not implemented. See [systems/crafting-system.md](systems/crafting-system.md).

