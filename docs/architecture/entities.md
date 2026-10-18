# Entity Reference

Purpose: Canonical list of all database entities, config-based data, and structural decisions.

Reference: Derived from [product/overview.md](../product/overview.md), system docs, and screen docs.

---

## Design Decisions


| Decision                                                              | Rationale                                                                                                                                                                                                                                                           |
| --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **VerificationToken** replaces separate email/password-reset tokens   | Identical mechanic (token, user, expiry, one-time). Differentiated by `type` field.                                                                                                                                                                                 |
| **Race** as PHP enum + config YAML, not DB entity                     | 8 races (`human, elf, dwarf, orc, undead, giant, ent, genie`), static data, never changes at runtime.                                                                                                                                                              |
| **RaceRelationship** as config matrix, not DB                         | 28 unique pairs, static, read-only game data.                                                                                                                                                                                                                       |
| **StatusEffect** as enum + value objects                              | Defined by code (Burn, Stun, etc.), not user-created.                                                                                                                                                                                                               |
| **SeasonReward** as JSON on LeagueTier or config                      | Design-defined, not player-created.                                                                                                                                                                                                                                 |
| **PlayerWallet** as columns on Team                                   | 1:1, no reason for extra table.                                                                                                                                                                                                                                     |
| **FinancialRecord** as audit log for Team finances                    | Separate entity to record all gold changes (income and expenses), capturing context and actor details.                                                                                                                                       |
| **SummonCooldown** as columns on Team                                 | 1:1, few fields.                                                                                                                                                                                                                                                    |
| **ItemAttributeBonus** as JSON column on Item                         | Always read as whole; no cross-item queries needed.                                                                                                                                                                                                                 |
| **Item.special_effects unused (A7.4 / #2)** | JSON column kept. Merchant writes `[]`. Combat reads `bonuses` only. Item API omits the key (`ItemService::serialize()`). Do not drop the column. Enchanting is not in the live game. |
| **FormationHeroStrategy** as JSON on FormationSlot                    | Complex per-slot config, never queried across slots.                                                                                                                                                                                                                |
| **FormationSlot unique hero** (application, not DB)                   | Save rejects duplicate `hero_id` on one formation. Empty slots allowed. Kickoff uniqueness is enforced by `MatchLineupService`. `UNIQ_FORMATION_POSITION` is the only DB unique key.                                                                              |
| **FormationSpellPriority** as JSON on FormationSlot                   | Same reasoning.                                                                                                                                                                                                                                                     |
| **NotificationPreference** | Dedicated **`UserSettings`** table (`auth_user_settings`), 1:1 with User | UI/interface preferences (modal backdrop, future toggles). Notification email/in-game prefs still **planned**. |
| **HeroTrainingHistory** as append-only weekly training log            | One row per hero trained during each weekly tick. Used for hero recent activity and calendar history. The same tick also writes `training_completed` on team chronicle and hero chronicle.                                                                         |
| **Leaderboard** = SQL view / computed                                 | Derived from LeagueStanding, not stored separately.                                                                                                                                                                                                                 |
| **PlayerProfile** = projection of User+Team                              | Not a DB entity.                                                                                                                                                                                                                                                    |
| **NPC Team** as `is_npc = true` on Team entity                        | No separate entity needed; NPC and player teams share all fields. `user_id` is NULL for NPC teams.                                                                                                                                                                  |
| **Kingdom initialization** creates all NPC teams + first LeagueSeason | Ensures a full league bracket exists before any player joins.                                                                                                                                                                                                       |
| `**display_name` + `display_name_slug`** both on User                 | `display_name` is stored and displayed exactly as entered. `display_name_slug` is the webalized form (lowercase, diacritics stripped, non-alphanumeric replaced with `-`) and carries a unique index — used solely for collision detection.                         |
| **TeamChronicle** as single generic table (team chronicle)              | One table (`team_chronicle`) covers all game event types, scoped by **`team_id`** (not `user_id`). `type` (enum) enables filtering; `data` (JSON) holds event-specific context. `subject_key` + `subject_params` (JSON) allow translated rendering at display time in the player's locale. Append-only team history across NPC periods and multiple managers. See [team-chronicle-system.md](../systems/team-chronicle-system.md). |
| **HeroChronicle** as append-only per-hero log | Table `hero_chronicle` scoped by **`hero_id`** (with `original_hero_id` for post-dismiss lookups). Same translation pattern as team chronicle (`subject_key` + `subject_params`). See [hero-chronicle-system.md](../systems/hero-chronicle-system.md). |
| **Hero rating cache** | `Hero.base_ovr` and `Hero.complex_rating` are **cached DB columns**, refreshed on flush via `HeroRatingCacheSubscriber` and bulk backfill via `app:hero-ratings:refresh`. |
| **Hero.level is stored truth (A7.1 / #6)** | Lifetime `Hero.xp` is never spent. Combat/UI/API read `Hero.level`. Live raise after match XP; formula or `HeroProgressionConfig::LEVEL_CAP` change uses `app:hero-levels:refresh` (no on-read derivation). Curve: `config/game/hero-progression.yaml`. Cap constant: **20**. |
| `**league_tiers_config` JSON on Kingdom**                             | Configurable per kingdom; total player capacity is derived as `sum(tier.groups) × teams_per_group`. Eliminates redundant `max_players` field.                                                                                                                       |
| **Team `morale` default value = 50**                                  | Range 0–100; 50 represents neutral/mid morale. Applied at team creation and on hero transfer/sell.                                                                                                                                                                  |
| `**Facility.passive_bonuses` design**                         | In DB, `Facility` uses a `metadata` (JSON) field. The passive bonuses are computed using `getPassiveBonuses()`, combining `metadata` with static bonuses defined per `FacilityType` level. `summon_stat_total_cap` is computed but **unread** (A6.5); HQ cards and API `passive_bonuses` use `getDisplayedPassiveBonuses()`. |
| `**Hero.intel` column name**                                          | PHP property and DB column named `intel` instead of `int` — `int` is a PHP reserved word and cannot be used as an identifier.                                                                                                                                       |
| `**MarketplaceListing` polymorphic entity reference**                 | Two nullable FK columns (`hero_id`, `item_id`) — only one set per row based on `listing_type`. Trainer listings store the trainer in `hero_id`. Application layer enforces mutual exclusivity.                        |
| **Database Table Naming Convention**                                  | Main/root entities (e.g. `team`, `kingdom`, `headquarters`, `hero`, `formation`, `graveyard`, `spell`, `item`, `notification`, `news_article`) do NOT have domain prefixes. Sub-entities (e.g. `headquarters_facility`, `team_financial_record`, `team_chronicle`, `team_summon_history`, `formation_slot`, `hero_school_mastery`, `hero_training_history`, **`auth_user_settings`**) or entities with SQL reserved keyword conflicts (e.g. `user` -> `auth_user`, `verification_token` -> `auth_verification_token`, `battle` -> `combat_battle`, `message` -> `community_message`) are prefixed with their domain namespace. There is no live `event` table (world events are deferred). |
| **Reserved enum cases (A6.2)** | Keep the PHP cases and DB columns. Do **not** add writers, combat/economy hooks, or UI filter options “for completeness”. `isReserved()` / `liveCases()` mark the contract in code. |
| **ForumThread `is_pinned` (A6.3 Cut)** | Column kept. Listing/UI show pinned threads when true. **No writer** — no pin/unpin API, create/lock never set it, no forum admin role. Do not describe pin as admin use. |
| **Notification read-time i18n (A6.4)** | Store `title_key` / `body_key` + JSON params (same pattern as chronicle `subject_key`). `NotificationPresenter` renders in the reader's locale. Pre-A6.4 `title` / `body` stay as a fallback when keys are null. |
| **`summon_stat_total_cap` unread (A6.5)** | Keep the per-level value on `FacilityType::getPassiveBonuses()`. `HeroGenerator` ignores it (live total cap is 60% of per-stat ceilings). HQ/API omit it via `FacilityType::withoutUnreadBonuses()` so it is not shown as an active bonus. |

---

## Domain Boundaries (Combat vs Arena)

**Combat** and **Arena** are separate game concepts. Controllers may use user-facing names (`ArenaController`, `/app/arena`) while services follow entity ownership.

| Concept | Entity / data | Service namespace | Responsibility |
| ------- | ------------- | ----------------- | -------------- |
| **Combat** | `App\Entity\Combat\Battle` (`combat_battle`) | `App\Service\Combat` | Derived stats ✅; wave Messenger + turn loop ✅; post-match resolution ✅ |
| **Arena facility** | `Headquarters` + `Facility` (`FacilityType::Arena`) | `App\Service\Headquarters\ArenaService` | Arena level, seating capacity, fan appeal, ticket price, next-home-match projection, last-18-payout attendance analytics |
| **Arena revenue** | `FinancialRecord` (`arena_revenue`) | `App\Service\Economy\ArenaRevenueService` | Ticket payout on league match tick, attendance calculation |

- `ArenaController` stays under `Controller` — it is the player-facing screen name; Web route redirects to `/app/hq?facility=arena`.
- `MatchType::Arena` is a **reserved / unused** enum case (A6.2). Live battles use `league` or `friendly`. Do not treat it as a match type consumed by the combat engine or by `ArenaService`.
- Keep combat simulation under `App\Service\Combat` (`CombatEngine`, wave handlers, resume); do not fold it into `ArenaService`.

---

## Database Entities (38 implemented)

### 1. Auth Domain


| Entity                | Key Fields                                                                                                      | Relationships         |
| --------------------- | --------------------------------------------------------------------------------------------------------------- | --------------------- |
| **User**              | id, email, password_hash, is_verified, roles[], kingdom_id, locale, display_name, display_name_slug, created_at, last_activity_at, inactive_warning_sent_at, team_reassignment_available_at | N:1 Kingdom, 1:1 Team, 1:1 UserSettings |
| **UserSettings**      | id, user_id, close_modal_on_backdrop, updated_at                                                                | 1:1 User (CASCADE DELETE) |
| **VerificationToken** | id, user_id, token, type (email_verify / password_reset / …), expires_at, used_at, data (JSON, nullable)                             | → User                |


### 2. Kingdom Domain


| Entity      | Key Fields                                                                                                                                        | Relationships        |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- |
| **Kingdom** | id, name, language, timezone, pace_preset (`PacePreset`), training_speed, recovery_speed, aging_speed, construction_speed, economy_scale, fan_pace, summon_cap, xp_modifier (required; copied from `config/kingdom/pace-presets.json` at creation), season_length (**77**), league_tiers_config (JSON), **royal_treasury_gold** | 1:N Users, 1:N Teams |
| **KingdomTickLog** | id, kingdom_id, team_id (nullable), fixture_id (nullable), friendly_match_id (nullable), tickType (enum), scheduledAt, status, errorMessage, executedAt, retry_count | → Kingdom, → Team (nullable), → LeagueFixture (nullable), → FriendlyMatch (nullable) |


### 3. Team Domain


| Entity   | Key Fields                                                                                                                                                                                                                                           | Relationships                                                |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| **Team**            | id, user_id (nullable), kingdom_id, name, emblem, colors, morale, reputation, chemistry, fan_base, **last_fan_base_delta**, gold, is_npc, last_summon_at, summons_this_cycle, **unpaid_debt**, **crisis_weeks**, **last_recovery_action_at**, **ticket_price** | → User (N:1, nullable — NULL for NPC teams), → Kingdom (N:1) |
| **FinancialRecord** | id, team_id, type (enum), actor (enum), gold_change, context (JSON), created_at, processed_at (nullable) | → Team (N:1)                                                 |
| **TeamChronicle**   | id, team_id, type (`ChronicleEventType` enum), subject_key, subject_params (JSON), data (JSON), created_at | → Team (N:1). Append-only; write via `TeamChronicleService` only. Behaviour: [team-chronicle-system.md](../systems/team-chronicle-system.md). |
| **TeamSummonHistory** | id, team_id, race_selected, hero_id, gold_cost, summoned_at | → Team (N:1), → Hero (N:1)                                   |
| **TeamDailySnapshot** | id, team_id, recorded_at (date), morale, reputation, chemistry, fan_base | → Team (N:1); table `team_daily_snapshot`; unique on (team_id, recorded_at); used to power the team history chart (last 30 days of morale/reputation/chemistry/fan_base trends) |


### 4. Hero Domain


| Entity            | Key Fields                                                                                                                                     | Relationships                              |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| **Hero**          | id, team_id, name, race (enum), role (`HeroRole`), level, xp, age, form, fatigue, morale, magic_capacity, str, dex, kon, spd, intel, wil, cha, lck, **base_ovr**, **complex_rating** (cached), **matches_played**, **matches_won**, **combats_fallen**, status (enum), **trait** (nullable `HeroTrait` enum), training_type / target_attribute / trainer_id (trainer role) | → Team, has many HeroSpell, HeroChronicle, equipped Items; optional trainer → trainees |
| **SchoolMastery** | id, hero_id, school (enum), mastery_tier, xp, last_exercised_at (nullable), last_gain_xp                                                       | → Hero                                     |
| **WeaponMastery** | id, hero_id, style (`ItemSubType` enum), mastery_tier, xp, attunement_progress (0–100), last_exercised_at (nullable), last_gain_xp             | → Hero                                     |
| **HeroSpell**     | id, hero_id, spell_id, is_equipped, slot_number                                                                                                | → Hero, → Spell                            |
| **HeroChronicle** | id, hero_id, original_hero_id (nullable), team_id (nullable), type (`HeroChronicleEventType` enum), subject_key, subject_params (JSON), data (JSON), created_at | → Hero, → Team                             |


### 5. Training Domain


| Entity                  | Key Fields                                                                                                                                              | Relationships                |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| **Hero (Trainer role)** | Trainers are heroes with `role = trainer`. Fields: `training_type` (nullable enum), `target_attribute` (nullable string), trainee assignments via `hero.trainer_id` | → Team, has many trainee Heroes |
| **HeroTrainingHistory** | id, hero_id, training_type (enum), target_attribute (nullable), trainer_id (nullable), stat_gain (nullable), completed_at                               | → Hero (trainee), → Hero (trainer, optional). Append-only log written after each weekly training tick. Same tick writes `training_completed` on team and hero chronicle. |


### 6. Formation Domain


| Entity            | Key Fields                                                                           | Relationships                  |
| ----------------- | ------------------------------------------------------------------------------------ | ------------------------------ |
| **Formation**     | id, team_id, name, is_default, is_temporary, source_fixture_id (nullable), approach (enum) | → Team, → LeagueFixture (optional), has many FormationSlot |
| **FormationSlot** | id, formation_id, hero_id, position (enum), strategy (JSON: `movement_goal`, optional L1 `target_order`/`fallback`), spell_priorities (JSON: L2 list) | → Formation, → Hero            |


### 7. Headquarters Domain


| Entity           | Key Fields                                                      | Relationships  |
| ---------------- | --------------------------------------------------------------- | -------------- |
| **Headquarters** | id, team_id, total_level, race_optimization, pending_race_optimization (nullable string), has_pending_race_optimization_change (bool), race_optimization_lock_cycle (bool), upgrading_facility_id (nullable FK), upgrade_completed_at (nullable datetime), facility_operation (`FacilityOperation`, nullable), facility_downgrade_lock_cycle (bool) | → Team (1:1)   |
| **Facility**     | id, headquarters_id, type (enum), level, metadata (JSON) | → Headquarters |


### 8. Item Domain


| Entity   | Key Fields                                                                                                                                                     | Relationships                |
| -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| **Item** | id, owner_team_id, equipped_hero_id, equipped_slot, name, slot_type (enum), sub_type (nullable `ItemSubType`), category (enum), rarity (enum), durability, status (enum), bonuses (JSON), special_effects (JSON, unused / reserved — A7.4; omitted from item API) | → Team, → Hero (if equipped) |


### 9. Spell Domain


| Entity    | Key Fields                                                                                                                                        | Relationships           |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------- |
| **Spell** | id, name, school (enum), tier (1–5), type (enum), effects (JSON), mana_cost, cooldown, required_mastery_tier, learning_cost_gold, requires_magical_weapon (bool) | referenced by HeroSpell. Seeded catalog: 6 schools × 5 tiers × 2 (A7.11 / #16) |


### 10. Combat Domain


| Entity     | Key Fields                                                                                                                                                                                  | Relationships                    |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| **Battle** | id, kingdom_id, match_type (enum), team_a_id, team_b_id, formation_a_id (nullable), formation_b_id (nullable), result (enum), score_a, score_b (kill score 0–6 each), combat_log (JSON), xp_awarded, processed_at, scheduled_at, status (`BattleStatus`: simulating/stalled/completed), current_round, run_state (JSON snapshot for wave Messenger) | → Kingdom, → Teams, → Formations |


### 11. League Domain


| Entity             | Key Fields                                                                       | Relationships                    |
| ------------------ | -------------------------------------------------------------------------------- | -------------------------------- |
| **LeagueSeason**   | id, kingdom_id, season_number, start_date, end_date, status (enum)               | → Kingdom                        |
| **LeagueTier**     | id, season_id, tier_name, promotion_slots, relegation_slots, rewards (JSON)      | → LeagueSeason                   |
| **LeagueGroup**    | id, tier_id, group_name                                                          | → LeagueTier                     |
| **LeagueStanding** | id, group_id, team_id, played, wins, draws, losses, points, goal_difference      | → LeagueGroup, → Team            |
| **LeagueFixture**  | id, group_id, home_team_id, away_team_id, home_formation_id (nullable), away_formation_id (nullable), scheduled_at, battle_id, status (enum) | → LeagueGroup, → Teams, → Formations (optional), → Battle |
| **FriendlyMatch**  | id, kingdom_id, home_team_id (challenger), away_team_id, home_formation_id (nullable), away_formation_id (nullable), scheduled_at, battle_id (nullable, unique), status (`FriendlyMatchStatus`), created_at, responded_at (nullable) | → Kingdom, → Teams, → Formations (optional), → Battle |


### 12. Marketplace Domain


| Entity                 | Key Fields                                                                                                                                                                     | Relationships      |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------ |
| **MarketplaceListing** | id, kingdom_id, seller_team_id, listing_type (enum), hero_id (nullable), item_id (nullable), price_gold, buyout_price_gold (nullable int), listing_mode (enum), expires_at, status (enum) | → Kingdom, → Team. **Trainer listings** reuse the `hero_id` FK (trainer is a `Hero` with `role = trainer`); there is no separate `trainer_id` column. |
| **MarketplaceBid**     | id, listing_id, bidder_team_id, bid_amount, bid_time                                                                                                                           | → Listing, → Team  |
| **MarketplaceTransaction** | id, buyer_team_id, seller_team_id (nullable), listing_id (nullable), entity_name (nullable snapshot), amount, fee_amount, type (enum), created_at                                                                                 | → Teams, → Listing |


### 13. Community Domain


| Entity              | Key Fields                                                             | Relationships         |
| ------------------- | ---------------------------------------------------------------------- | --------------------- |
| **Message**         | id, sender_user_id, receiver_user_id, sender_team_id (nullable), receiver_team_id (nullable), subject, body, read_at, sent_at, deleted_by_sender (bool), deleted_by_receiver (bool)  | → Users, → Teams (snapshot) |
| **NewsArticle**     | id, kingdom_id, title, content, published_at                           | → Kingdom (optional)  |
| **ForumThread**     | id, kingdom_id, category, title, author_user_id, author_team_id (nullable), created_at, is_pinned (**unsettable**, A6.3 Cut), is_locked (bool) | → Kingdom, → User, → Team (snapshot) |
| **ForumPost**       | id, thread_id, author_user_id, author_team_id (nullable), body, created_at                        | → ForumThread, → User, → Team (snapshot) |


### 14. Graveyard Domain


| Entity              | Key Fields                                                                                                                                                | Relationships |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------- |
| **GraveyardMemorial** | id, team_id, name, race, role_at_departure, cause, age, final_level, final_stats (JSON), trait, matches_played, matches_won, combats_fallen, departed_at, original_hero_id (table: `graveyard`) | → Team        |


### 15. Notification Domain


| Entity           | Key Fields                                                 | Relationships |
| ---------------- | ---------------------------------------------------------- | ------------- |
| **Notification** | id, user_id, type (enum), title_key, body_key, title_params (JSON), body_params (JSON), title, body (nullable legacy fallback), is_read, created_at | → User. Read-time i18n via `NotificationPresenter` (A6.4). Reserved types (`league_update`, `event_started`, `spell_learned`): [notification-system.md](../systems/notification-system.md) (A6.2). |


### Team Chronicle (append-only event log)

Fields: [Team Domain](#3-team-domain) (`TeamChronicle`). Full writer map, categories, and UI: [team-chronicle-system.md](../systems/team-chronicle-system.md).

- Append-only table `team_chronicle`. Do not persist the entity from feature services — `TeamChronicleService` only.
- Read: `TeamChroniclePresenter` (dashboard last 5; `GET /app/chronicle`).
- `ChronicleEventType::HeroRetired` (`hero_retired`) is **reserved / unused** (A6.2).

---

## Config-Based Data (not in DB)


| Name                    | Storage                                                 | Contents                                                  |
| ----------------------- | ------------------------------------------------------- | --------------------------------------------------------- |
| **Race definitions**    | PHP enum (`Race`) + `config/game/races.yaml`            | 8 races (human, elf, dwarf, orc, undead, giant, ent, genie), stat bonuses, age thresholds, training modifiers |
| **Race relationships**  | `config/game/race_relations.yaml`                       | 8×8 matrix (28 unique pairs). Summoning / starting roster / NPC hero buys: ≥ 50, Genie adaptation ≥ 80 ([#14](../product/known-issues.md)) |
| **Status effects**      | PHP enum + value objects + `config/game/status_effects.yaml` | Burn, Stun, Heal, Buff types — used by combat engine |
| **Hero rating**         | `config/game/hero-rating.yaml`                          | Base OVR / complex rating weights                         |
| **Hero progression**    | `config/game/hero-progression.yaml` + `HeroProgressionConfig::LEVEL_CAP` | Cumulative XP→level coefficient (`400`); cap is the PHP constant **20** |
| **Season rewards**      | JSON on LeagueTier                                      | Per-tier position rewards                                 |


---

## Enums (PHP backed enums)

41 files in `src/Enum/`. The table below matches that set. Deferred `DungeonResult` / `CraftingStatus` are **not** PHP enums (see [Deferred Concepts](#deferred-concepts)).

**Reserved / unused (A6.2, locked):** keep the cases in code and the DB columns; do not add writers or combat/economy hooks “for completeness”. Player-facing filter dropdowns use `liveCases()` (reserved cases omitted). `MatchType::Arena`, `FinancialRecordType::MoraleRestoration`, `FinancialRecordType::SpellSlotCost`, `ChronicleEventType::HeroRetired`, `MemorialCause::{Age, Retired, Death}`, `NotificationType::{LeagueUpdate, EventStarted, SpellLearned}`. Related unused retirement cases with no production writer, marked `isReserved()` the same way: `HeroStatus::Retired`, `HeroChronicleEventType::Retired` (readers treat `HeroStatus::Retired` like Dead for roster queries).

| Enum                | Values                                                                                                                                                                                                                                            |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Race`              | human, elf, dwarf, orc, undead, giant, ent, genie. Ent and Giant use a 7-hex combat footprint (see [combat-system.md](../systems/combat-system.md#unit-footprint-size)). |
| `HeroStatus`        | available, in_match, selling, recovering, dead; **retired (reserved / unused — `isReserved()`; no writer; readers treat it like dead)**                                                                                                                                                                                               |
| `HeroTrait`         | quick_learner, clutch, audience_favorite, battle_hardened, volatile, slacker, fragile, glass_jaw, berserker, glass_cannon, reckless, loner, overconfident, perfectionist (nullable on Hero; ~60% assign chance at generation). UI: [hero-system.md](../systems/hero-system.md) § Player UI |
| `School`            | fire, water, air, earth, light, dark                                                                                                                                                                                                              |
| `SpellType`         | offensive, defensive, utility                                                                                                                                                                                                                     |
| `ItemSlotType`      | main_hand, off_hand, head, body, hands, feet, amulet, ring, ring_2                                                                                                                                                                                   |
| `ChronicleEventType`   | team_established, player_joined, player_released, battle_win, battle_loss, battle_draw, hero_levelup, hero_died, **hero_retired (reserved / unused)**, training_completed, item_purchased, item_sold, summon_completed, season_ended, hero_dismissed, trainer_dismissed, hero_purchased, hero_sold, trainer_purchased, trainer_sold, team_renamed, facility_upgraded, facility_downgraded, facility_upgrade_cancelled, race_optimization_changed, starting_roster, spell_learned, item_dismantled, trainer_promoted, financial_crisis_state, kingdom_reward_granted |
| `HeroChronicleEventType` | summoned, transferred, renamed, match_played, levelup, mastery_gained, training_completed, spell_learned, injured, recovered, died; **retired (reserved / unused — `isReserved()`)** |
| `ChronicleReleaseReason` | inactivity, bankruptcy, unverified_registration, account_deleted (stored in `data.reason`; used with `player_released`) |
| `ChronicleCategory` | all, ownership, competition, roster, economy (UI filter groups; not stored on rows) |
| `ItemCategory`      | weapon, shield, spell_accelerator, armor, accessory, material *(material unused — deferred crafting; not a live `CraftingMaterial` entity)*                                                                                                        |
| `ItemSubType`       | one_handed_sword, two_handed_sword, one_handed_axe, two_handed_axe, one_handed_mace, two_handed_mace, dagger, bow, crossbow, wand, staff, shield, spell_accelerator, light_armor, medium_armor, heavy_armor (weapon/armor mastery & gear attunement) |
| `ItemRarity`        | common, uncommon, rare, epic, legendary, mythic                                                                                                                                                                                                   |
| `ItemStatus`        | available, selling                                                                                                                                                                                                                                |
| `FormationPosition` | front_1, front_2, front_3, back_1, back_2, back_3                                                                                                                                                                                                 |
| `FormationApproach` | aggressive, balanced, defensive                                                                                                                                                                                                                   |
| `MatchType`         | league, friendly, **arena (reserved / unused)**                                                                                                                                                                                          |
| `BattleResult`      | win_a, win_b, draw                                                                                                                                                                                                                                |
| `BattleStatus`      | simulating, stalled, completed                                                                                                                                                                                                                    |
| `CombatStatProfile` | equipped, human_neutral, full_intrinsic (hero rating / combat stat calculation modes)                                                                                                                                                             |
| `TrainingType`      | attribute, magic, form                                                                                                                                                                                                                            |
| `ListingType`       | hero, item, trainer                                                                                                                                                                                                                               |
| `ListingMode`       | buy_now, auction                                                                                                                                                                                                                                  |
| `ListingStatus`     | active, sold, expired, cancelled                                                                                                                                                                                                                  |
| `TokenType`         | email_verify, password_reset, change_email_old, change_email_new, delete_account                                                                                                                                                                  |
| `FacilityType`      | training, medical, library, treasury, barracks, summoning_chamber, arena                                                                                                                                                                    |
| `StatusEffect`      | burn, freeze, shock, petrify, blind, curse, stun, poison, shield, regeneration, haste, bless, fury, shadow_cloak, taunt, silence                                                                                                                  |
| `TickType`          | daily_reset, fatigue_recovery, league_match, friendly_match, weekly_training, season_transition, weekly_reset, race_optimization, inactive_registration_cleanup, inactive_player_cleanup                                                                                                                                   |
| `LeagueSeasonStatus` | scheduled, active, completed                                                                                                                                                                                                                      |
| `PacePreset` | classic, fast_season, short_careers, rich_world, test                                                                                                                                                                                             |
| `LeagueFixtureStatus` | scheduled, in_progress, completed, cancelled                                                                                                                                                                                                     |
| `FriendlyMatchStatus` | pending, scheduled, in_progress, completed, declined, cancelled, expired                                                                                                                                                                         |
| `MatchResult`       | win, loss, draw *(team fan-base deltas; distinct from `BattleResult`)*                                                                                                                                                                          |
| `MemorialCause`     | dismissed, combat_death *(live)*; **age, retired, death (reserved / unused)**                                                                                                                                                                     |
| `RoyalTreasuryContributionSource` | marketplace_tax, hq_upgrade_cost, hq_maintenance_fee, summon_fee                                                                                                                                                                      |
| `HeroRole`          | combatant, trainer                                                                                                                                                                                                                                |
| `TransactionType`   | buy_now, auction_win                                                                                                                                                                                                                              |
| `NotificationType`  | battle_result, training_complete, marketplace_bid, marketplace_sold, hero_died, season_ended, system, hq_upgrade, financial_crisis; **league_update, event_started, spell_learned (reserved / unused)** |
| `FinancialRecordType` | league_reward, arena_revenue, summon_fee, marketplace_sale, marketplace_purchase, marketplace_fee, dismantle_gain, item_repair, spell_learning_cost, hq_upgrade_cost, hq_maintenance_fee, debt_repayment, hero_dismissal_compensation, trainer_dismissal_compensation, hq_downgrade_refund, hq_upgrade_refund, kingdom_reward, hero_salary, trainer_salary; **spell_slot_cost, morale_restoration (reserved / unused)** |
| `FinancialCrisisLevel` | **none**, **warning**, **restricted**, **bankruptcy_pending** |
| `FacilityOperation` | **upgrade**, **downgrade** |
| `FinancialRecordActor` | system, active, passive                                                                                                                                                                                                                        |

---

## Entity Count Summary


| Category                   | Count  |
| -------------------------- | ------ |
| DB entities (implemented) | 38     |
| Config-based (not DB)      | 6      |
| PHP enums (`src/Enum`)     | 41     |
| **Total modeled concepts** | **85** |


---

## Deferred Concepts

Not in `src/Entity/` or `src/Enum/`. Design only under [`future/`](../future/). Do not add these to the live tables above.

- **World events** (`Event`, `EventParticipation`, `EventType`, `EventStatus`, table `event`) — [future/world-events-system.md](../future/world-events-system.md)
- **Dungeons** (`DungeonRun`, `DungeonEnemy` YAML, `DungeonResult`) — removed from the codebase; [future/dungeon-system.md](../future/dungeon-system.md)
- **Quests** (`Quest`, `PlayerQuestProgress`) — [future/quest-system.md](../future/quest-system.md)
- **Crafting** (`CraftingRecipe`, `CraftingQueue`, `CraftingStatus`; no separate crafting-material entity) — [future/crafting-system.md](../future/crafting-system.md)

