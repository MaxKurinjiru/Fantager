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
| **FinancialRecord** as audit log for Team finances                    | Separate entity to record all gold, crystals, and essence changes (income and expenses), capturing context and actor details.                                                                                                                                       |
| **SummonCooldown** as columns on Team                                 | 1:1, few fields.                                                                                                                                                                                                                                                    |
| **ItemAttributeBonus** as JSON column on Item                         | Always read as whole; no cross-item queries needed.                                                                                                                                                                                                                 |
| **FormationHeroStrategy** as JSON on FormationSlot                    | Complex per-slot config, never queried across slots.                                                                                                                                                                                                                |
| **FormationSpellPriority** as JSON on FormationSlot                   | Same reasoning.                                                                                                                                                                                                                                                     |
| **NotificationPreference** as JSON on User                            | Simple key-value per user.                                                                                                                                                                                                                                          |
| **TrainingHistory** = completed TrainingQueue entries                 | No separate entity; add `stat_gain` to TrainingQueue.                                                                                                                                                                                                               |
| **Leaderboard** = SQL view / computed                                 | Derived from LeagueStanding, not stored separately.                                                                                                                                                                                                                 |
| **PlayerProfile** = projection of User+Team+Achievements              | Not a DB entity.                                                                                                                                                                                                                                                    |
| **NPC Team** as `is_npc = true` on Team entity                        | No separate entity needed; NPC and player teams share all fields. `user_id` is NULL for NPC teams.                                                                                                                                                                  |
| **Kingdom initialization** creates all NPC teams + first LeagueSeason | Ensures a full league bracket exists before any player joins.                                                                                                                                                                                                       |
| `**display_name` + `display_name_slug`** both on User                 | `display_name` is stored and displayed exactly as entered. `display_name_slug` is the webalized form (lowercase, diacritics stripped, non-alphanumeric replaced with `-`) and carries a unique index — used solely for collision detection.                         |
| **ActivityLog** as single generic table                               | One table covers all game event types. `type` (enum) enables filtering; `data` (JSON) holds event-specific context. `subject_key` + `subject_params` (JSON) allow translated rendering without re-generating text at insert time. No separate log table per domain. |
| `**league_tiers_config` JSON on Kingdom**                             | Configurable per kingdom; total player capacity is derived as `sum(tier.groups) × teams_per_group`. Eliminates redundant `max_players` field.                                                                                                                       |
| **Team `morale` default value = 50**                                  | Range 0–100; 50 represents neutral/mid morale. Applied at team creation and on hero transfer/sell.                                                                                                                                                                  |
| `**Facility.passive_bonuses` JSON structure**                         | Flat key-value map: `{"training_efficiency_pct": 5, "fatigue_reduction_pct": 10, ...}`. Keys are snake_case with unit suffix (`_pct`, `_flat`). Simple to read; keys vary per `FacilityType`.                                                                       |
| `**Hero.intel` column name**                                          | PHP property and DB column named `intel` instead of `int` — `int` is a PHP reserved word and cannot be used as an identifier.                                                                                                                                       |
| `**MarketplaceListing` polymorphic entity reference**                 | Three nullable FK columns (`hero_id`, `item_id`, `trainer_id`) — only one set per row based on `listing_type`. Provides DB-level referential integrity without a generic polymorphic pattern. Application layer enforces mutual exclusivity.                        |
| **Database Table Naming Convention**                                  | Main/root entities (e.g. `team`, `kingdom`, `headquarters`, `hero`, `formation`, `spell`, `item`, `event`, `quest`, `notification`, `achievement`, `news_article`, `activity_log`, `trainer`, `training_queue`) do NOT have domain prefixes. Sub-entities (e.g. `headquarters_facility`, `team_financial_record`, `formation_slot`, `hero_school_mastery`, `quest_player_progress`) or entities with SQL reserved keyword conflicts (e.g. `user` -> `auth_user`, `verification_token` -> `auth_verification_token`, `battle` -> `combat_battle`, `message` -> `community_message`) are prefixed with their domain namespace. |

---

## Database Entities (43)

### 1. Auth Domain


| Entity                | Key Fields                                                                                                      | Relationships         |
| --------------------- | --------------------------------------------------------------------------------------------------------------- | --------------------- |
| **User**              | id, email, password_hash, is_verified, roles[], kingdom_id, locale, display_name, display_name_slug, created_at | N:1 Kingdom, 1:1 Team |
| **VerificationToken** | id, user_id, token, type (email_verify / password_reset), expires_at, used_at                                   | → User                |


### 2. Kingdom Domain


| Entity      | Key Fields                                                                                                                                        | Relationships        |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| **Kingdom** | id, name, language, timezone, game_speed, marketplace_tax_rate, season_length, league_tiers_config (JSON), level_cap, xp_modifier, crafting_boost | 1:N Users, 1:N Teams |
| **KingdomTickLog** | id, kingdom_id, tickType (enum), scheduledAt, status (enum), errorMessage, executedAt                                                             | → Kingdom (N:1)      |


### 3. Team Domain


| Entity   | Key Fields                                                                                                                                                                                                                                           | Relationships                                                |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| **Team**            | id, user_id (nullable), kingdom_id, name, emblem, colors, morale, reputation, chemistry, gold, crystals, essence_common, essence_uncommon, essence_rare, essence_epic, essence_legendary, essence_mythic, is_npc, last_summon_at, summons_this_cycle | → User (N:1, nullable — NULL for NPC teams), → Kingdom (N:1) |
| **FinancialRecord** | id, team_id, type (enum), actor (enum), gold_change, crystals_change, essence_common_change, essence_uncommon_change, essence_rare_change, essence_epic_change, essence_legendary_change, essence_mythic_change, context (JSON), created_at | → Team (N:1)                                                 |


### 4. Hero Domain


| Entity            | Key Fields                                                                                                                                     | Relationships                              |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| **Hero**          | id, team_id, name, race (enum), level, xp, age, form, fatigue, morale, magic_capacity, str, dex, kon, spd, intel, wil, cha, lck, status (enum) | → Team, has many HeroSpell, equipped Items |
| **SchoolMastery** | id, hero_id, school (enum), mastery_tier                                                                                                       | → Hero                                     |
| **HeroSpell**     | id, hero_id, spell_id, is_equipped, slot_number                                                                                                | → Hero, → Spell                            |


### 5. Training Domain


| Entity            | Key Fields                                                                                                                                              | Relationships                |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| **Trainer**       | id, team_id, name, race, stats (8×, range 1–20), age, death_expectation, status, original_hero_id | → Team, was Hero             |
| **TrainingQueue** | id, hero_id, training_type (enum), target_attribute (nullable), trainer_id (nullable), gold_cost, essence_cost, status (enum), stat_gain (nullable), execute_at, completed_at (nullable) | → Hero, → Trainer (optional). Multiple rows can share the same trainer + target_attribute for one training job |


### 6. Formation Domain


| Entity            | Key Fields                                                                           | Relationships                  |
| ----------------- | ------------------------------------------------------------------------------------ | ------------------------------ |
| **Formation**     | id, team_id, name, is_default, approach (enum)                                       | → Team, has many FormationSlot |
| **FormationSlot** | id, formation_id, hero_id, position (enum), strategy (JSON), spell_priorities (JSON) | → Formation, → Hero            |


### 7. Headquarters Domain


| Entity           | Key Fields                                                      | Relationships  |
| ---------------- | --------------------------------------------------------------- | -------------- |
| **Headquarters** | id, team_id, total_level, race_optimization                     | → Team (1:1)   |
| **Facility**     | id, headquarters_id, type (enum), level, passive_bonuses (JSON) | → Headquarters |


### 8. Item Domain


| Entity   | Key Fields                                                                                                                                                     | Relationships                |
| -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| **Item** | id, owner_team_id, equipped_hero_id, equipped_slot, name, slot_type (enum), category (enum), rarity (enum), durability, bonuses (JSON), special_effects (JSON) | → Team, → Hero (if equipped) |


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
| **LeagueFixture**  | id, group_id, home_team_id, away_team_id, scheduled_at, battle_id, status (enum) | → LeagueGroup, → Teams, → Battle |


### 12. Marketplace Domain


| Entity                 | Key Fields                                                                                                                                                                     | Relationships      |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------ |
| **MarketplaceListing** | id, kingdom_id, seller_team_id, listing_type (enum), hero_id (nullable), item_id (nullable), trainer_id (nullable), price_gold, listing_mode (enum), expires_at, status (enum) | → Kingdom, → Team  |
| **MarketplaceBid**     | id, listing_id, bidder_team_id, bid_amount, bid_time                                                                                                                           | → Listing, → Team  |
| **Transaction**        | id, buyer_team_id, seller_team_id, listing_id, amount, fee_amount, type (enum), created_at                                                                                     | → Teams, → Listing |


### 13. Event Domain


| Entity                 | Key Fields                                                                                      | Relationships   |
| ---------------------- | ----------------------------------------------------------------------------------------------- | --------------- |
| **Event**              | id, kingdom_id, type (enum), name, description, status (enum), start_at, end_at, rewards (JSON) | → Kingdom       |
| **EventParticipation** | id, event_id, team_id, progress, rewards_claimed                                                | → Event, → Team |


### 14. Dungeon Domain


| Entity         | Key Fields                                                                                                                                             | Relationships                  |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------ |
| **DungeonRun** | id, kingdom_id, team_id, dungeon_key (references config), formation_id, result (enum), rewards_xp, rewards_essence, rewards_items (JSON), completed_at | → Kingdom, → Team, → Formation |


### 15. Quest Domain


| Entity                  | Key Fields                                                                                       | Relationships   |
| ----------------------- | ------------------------------------------------------------------------------------------------ | --------------- |
| **Quest**               | id, kingdom_id, type (enum), title, description, rewards (JSON), requirements (JSON), expires_at | → Kingdom       |
| **PlayerQuestProgress** | id, team_id, quest_id, status (enum), progress, completed_at                                     | → Team, → Quest |


### 16. Crafting Domain


| Entity             | Key Fields                                                                                                                                                                         | Relationships            |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------ |
| **CraftingRecipe** | id, result_item_category, result_item_rarity, required_materials (JSON), essence_cost_type, essence_cost_amount, gold_cost, success_rate_base, crafting_time, required_forge_level | —                        |
| **CraftingQueue**  | id, team_id, recipe_id, status (enum), started_at, completes_at                                                                                                                    | → Team, → CraftingRecipe |


### 17. Community Domain


| Entity              | Key Fields                                                             | Relationships         |
| ------------------- | ---------------------------------------------------------------------- | --------------------- |
| **Message**         | id, sender_team_id, receiver_team_id, subject, body, read_at, sent_at  | → Teams               |
| **NewsArticle**     | id, kingdom_id, title, content, published_at                           | → Kingdom (optional)  |
| **ForumThread**     | id, kingdom_id, category, title, author_team_id, created_at, is_pinned | → Kingdom, → Team     |
| **ForumPost**       | id, thread_id, author_team_id, body, created_at                        | → ForumThread, → Team |
| **Achievement**     | id, name, description, icon, unlock_condition (JSON)                   | —                     |
| **TeamAchievement** | id, team_id, achievement_id, unlocked_at                               | → Team, → Achievement |


### 18. Graveyard Domain


| Entity              | Key Fields                                                                                                                                                | Relationships |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- |
| **GraveyardRecord** | id, team_id, hero_name, race, final_level, age_at_death, cause_of_death, total_battles, victories, final_stats (JSON), achievements (JSON), date_of_death | → Team        |


### 19. Summoning Domain


| Entity            | Key Fields                                                  | Relationships  |
| ----------------- | ----------------------------------------------------------- | -------------- |
| **SummonHistory** | id, team_id, race_selected, hero_id, gold_cost, summoned_at | → Team, → Hero |


### 20. Notification Domain


| Entity           | Key Fields                                                 | Relationships |
| ---------------- | ---------------------------------------------------------- | ------------- |
| **Notification** | id, user_id, type (enum), title, body, is_read, created_at | → User        |


### 21. Activity Log Domain


| Entity          | Key Fields                                                                                              | Relationships |
| --------------- | ------------------------------------------------------------------------------------------------------- | ------------- |
| **ActivityLog** | id, team_id, type (`ActivityLogType` enum), subject_key, subject_params (JSON), data (JSON), created_at | → Team        |


- `type` — enum value used for filtering and grouping (e.g. show only battles, only training).
- `subject_key` — Symfony translation key (e.g. `activity.battle.win`); rendered in the player's locale at display time.
- `subject_params` — JSON map of parameters injected into the translation string (e.g. `{"opponent": "Iron Wolves", "score": "3–2"}`).
- `data` — full machine-readable context for detailed views or future processing (e.g. linked `battle_id`, `hero_id`).
- Entries are **append-only**; never updated or deleted individually (pruned in bulk after a configurable retention period).

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
| `HeroStatus`        | available, tired, training, in_match, injured, dead                                                                                                                                                                                               |
| `School`            | fire, water, air, earth, light, dark                                                                                                                                                                                                              |
| `SpellType`         | offensive, defensive, utility                                                                                                                                                                                                                     |
| `ItemSlotType`      | main_hand, off_hand, head, body, hands, feet, amulet, ring                                                                                                                                                                                          |
| `ActivityLogType`   | battle_win, battle_loss, battle_draw, hero_levelup, hero_died, hero_retired, training_completed, item_crafted, item_purchased, item_sold, quest_completed, achievement_unlocked, dungeon_completed, summon_completed, season_ended, player_joined |
| `ItemCategory`      | weapon, shield, spell_accelerator, armor, accessory, material                                                                                                                                                                                      |
| `ItemRarity`        | common, uncommon, rare, epic, legendary, mythic                                                                                                                                                                                                   |
| `FormationPosition` | front_1, front_2, front_3, back_1, back_2, back_3                                                                                                                                                                                                 |
| `FormationApproach` | aggressive, balanced, defensive                                                                                                                                                                                                                   |
| `MatchType`         | league, friendly, dungeon, arena                                                                                                                                                                                                                  |
| `BattleResult`      | win_a, win_b, draw                                                                                                                                                                                                                                |
| `TrainingType`      | attribute, magic, form                                                                                                                                                                                                                            |
| `ListingType`       | hero, item, trainer                                                                                                                                                                                                                               |
| `ListingMode`       | buy_now, auction                                                                                                                                                                                                                                  |
| `ListingStatus`     | active, sold, expired, cancelled                                                                                                                                                                                                                  |
| `EventType`         | world_event, seasonal, limited_mission, special_economic                                                                                                                                                                                          |
| `QuestType`         | daily, weekly, story, repeatable                                                                                                                                                                                                                  |
| `TokenType`         | email_verify, password_reset, change_email_old, change_email_new, delete_account                                                                                                                                                                  |
| `FacilityType`      | training, medical, library, forge, treasury, barracks, summoning_chamber, arena                                                                                                                                                                    |
| `StatusEffect`      | burn, freeze, shock, petrify, blind, curse, stun, poison, shield, regeneration, haste, bless, fury, shadow_cloak, taunt, silence                                                                                                                  |
| `TickType`          | daily_reset, fatigue_recovery, league_match, weekly_training, season_transition, weekly_reset, race_optimization, inactive_registration_cleanup                                                                                                                                   |
| `LeagueSeasonStatus` | scheduled, active, completed                                                                                                                                                                                                                      |
| `LeagueFixtureStatus` | scheduled, in_progress, completed, cancelled                                                                                                                                                                                                     |
| `TrainerStatus`     | active, retired, dead                                                                                                                                                                                                                             |
| `TrainingStatus`    | pending, in_progress, completed, cancelled                                                                                                                                                                                                        |
| `EventStatus`       | scheduled, active, completed, cancelled                                                                                                                                                                                                           |
| `DungeonResult`     | win, loss, abandoned                                                                                                                                                                                                                              |
| `QuestProgressStatus` | in_progress, completed, failed, expired                                                                                                                                                                                                         |
| `CraftingStatus`    | pending, in_progress, completed, failed, cancelled                                                                                                                                                                                                |
| `TransactionType`   | buy_now, auction_win                                                                                                                                                                                                                              |
| `NotificationType`  | battle_result, training_complete, league_update, marketplace_bid, marketplace_sold, achievement_unlocked, event_started, quest_expired, hero_died, season_ended                                                                                   |
| `FinancialRecordType` | league_reward, arena_revenue, training_cost, summon_fee, marketplace_sale, marketplace_purchase, marketplace_fee, quest_reward, dungeon_reward, crafting_cost, dismantle_gain, item_repair, spell_learning_cost, spell_slot_cost, hq_upgrade_cost, morale_restoration |
| `FinancialRecordActor` | system, active, passive                                                                                                                                                                                                                        |

---

## Entity Count Summary


| Category                   | Count  |
| -------------------------- | ------ |
| DB entities                | 43     |
| Config-based (not DB)      | 5      |
| PHP enums                  | 34     |
| **Total modeled concepts** | **82** |


