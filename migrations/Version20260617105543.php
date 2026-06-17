<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617105543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auth_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, is_verified TINYINT NOT NULL, roles JSON NOT NULL, locale VARCHAR(5) DEFAULT \'cs\' NOT NULL, display_name VARCHAR(50) NOT NULL, display_name_slug VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, team_reassignment_available_at DATETIME DEFAULT NULL, last_activity_at DATETIME DEFAULT NULL, inactive_warning_sent_at DATETIME DEFAULT NULL, kingdom_id INT DEFAULT NULL, INDEX IDX_A3B536FD6976FEC0 (kingdom_id), UNIQUE INDEX UNIQ_EMAIL (email), UNIQUE INDEX UNIQ_DISPLAY_NAME_SLUG (display_name_slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE auth_user_settings (id INT AUTO_INCREMENT NOT NULL, close_modal_on_backdrop TINYINT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_25B98D5A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE auth_verification_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, type VARCHAR(20) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, data JSON DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_7DBA01A65F37A13B (token), INDEX IDX_7DBA01A6A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE combat_battle (id INT AUTO_INCREMENT NOT NULL, match_type VARCHAR(15) NOT NULL, result VARCHAR(10) DEFAULT NULL, score_a INT DEFAULT 0 NOT NULL, score_b INT DEFAULT 0 NOT NULL, combat_log JSON NOT NULL, xp_awarded INT DEFAULT 0 NOT NULL, processed_at DATETIME DEFAULT NULL, kingdom_id INT NOT NULL, team_a_id INT NOT NULL, team_b_id INT NOT NULL, formation_a_id INT DEFAULT NULL, formation_b_id INT DEFAULT NULL, INDEX IDX_BC1D295D6976FEC0 (kingdom_id), INDEX IDX_BC1D295DEA3FA723 (team_a_id), INDEX IDX_BC1D295DF88A08CD (team_b_id), INDEX IDX_BC1D295D6C01B618 (formation_a_id), INDEX IDX_BC1D295D7EB419F6 (formation_b_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community_forum_post (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, thread_id INT NOT NULL, author_user_id INT NOT NULL, author_team_id INT DEFAULT NULL, INDEX IDX_A8A0BA46E2904019 (thread_id), INDEX IDX_A8A0BA46E2544CD6 (author_user_id), INDEX IDX_A8A0BA466C5647ED (author_team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community_forum_thread (id INT AUTO_INCREMENT NOT NULL, category VARCHAR(100) NOT NULL, title VARCHAR(200) NOT NULL, created_at DATETIME NOT NULL, is_pinned TINYINT DEFAULT 0 NOT NULL, is_locked TINYINT DEFAULT 0 NOT NULL, kingdom_id INT NOT NULL, author_user_id INT NOT NULL, author_team_id INT DEFAULT NULL, INDEX IDX_769EC7CD6976FEC0 (kingdom_id), INDEX IDX_769EC7CDE2544CD6 (author_user_id), INDEX IDX_769EC7CD6C5647ED (author_team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community_message (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, read_at DATETIME DEFAULT NULL, sent_at DATETIME NOT NULL, deleted_by_sender TINYINT DEFAULT 0 NOT NULL, deleted_by_receiver TINYINT DEFAULT 0 NOT NULL, sender_user_id INT NOT NULL, receiver_user_id INT NOT NULL, sender_team_id INT DEFAULT NULL, receiver_team_id INT DEFAULT NULL, INDEX IDX_9278592C2A98155E (sender_user_id), INDEX IDX_9278592CDA57E237 (receiver_user_id), INDEX IDX_9278592CA49A1E65 (sender_team_id), INDEX IDX_9278592C5455E90C (receiver_team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, is_default TINYINT DEFAULT 0 NOT NULL, approach VARCHAR(15) NOT NULL, is_temporary TINYINT DEFAULT 0 NOT NULL, team_id INT NOT NULL, source_fixture_id INT DEFAULT NULL, INDEX IDX_404021BF296CD8AE (team_id), INDEX IDX_404021BF99901049 (source_fixture_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formation_slot (id INT AUTO_INCREMENT NOT NULL, position VARCHAR(10) NOT NULL, strategy JSON NOT NULL, spell_priorities JSON NOT NULL, formation_id INT NOT NULL, hero_id INT DEFAULT NULL, INDEX IDX_FBD1C5275200282E (formation_id), INDEX IDX_FBD1C52745B0BCD (hero_id), UNIQUE INDEX UNIQ_FORMATION_POSITION (formation_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE graveyard (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, race VARCHAR(10) NOT NULL, role_at_departure VARCHAR(15) NOT NULL, cause VARCHAR(20) NOT NULL, age INT NOT NULL, final_level INT DEFAULT NULL, final_stats JSON NOT NULL, departed_at DATE NOT NULL, original_hero_id INT DEFAULT NULL, team_id INT NOT NULL, INDEX IDX_99E2CFAA296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE headquarters (id INT AUTO_INCREMENT NOT NULL, total_level INT DEFAULT 1 NOT NULL, race_optimization VARCHAR(20) DEFAULT NULL, pending_race_optimization VARCHAR(20) DEFAULT NULL, has_pending_race_optimization_change TINYINT DEFAULT 0 NOT NULL, race_optimization_lock_cycle TINYINT DEFAULT 0 NOT NULL, upgrade_completed_at DATETIME DEFAULT NULL, facility_operation VARCHAR(10) DEFAULT NULL, facility_downgrade_lock_cycle TINYINT DEFAULT 0 NOT NULL, team_id INT NOT NULL, upgrading_facility_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_ABF65D25296CD8AE (team_id), INDEX IDX_ABF65D255DF6B900 (upgrading_facility_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE headquarters_facility (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, level INT DEFAULT 1 NOT NULL, metadata JSON NOT NULL, headquarters_id INT NOT NULL, INDEX IDX_4CA91EE730C35A0 (headquarters_id), UNIQUE INDEX UNIQ_HQ_TYPE (headquarters_id, type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, race VARCHAR(10) NOT NULL, role VARCHAR(15) DEFAULT \'combatant\' NOT NULL, level INT DEFAULT 1 NOT NULL, xp INT DEFAULT 0 NOT NULL, age INT NOT NULL, form INT DEFAULT 100 NOT NULL, fatigue INT DEFAULT 0 NOT NULL, morale INT DEFAULT 50 NOT NULL, magic_capacity INT DEFAULT 0 NOT NULL, str INT NOT NULL, dex INT NOT NULL, kon INT NOT NULL, spd INT NOT NULL, intel INT NOT NULL, wil INT NOT NULL, cha INT NOT NULL, lck INT NOT NULL, status VARCHAR(15) NOT NULL, training_type VARCHAR(15) DEFAULT NULL, target_attribute VARCHAR(20) DEFAULT NULL, team_id INT NOT NULL, trainer_id INT DEFAULT NULL, INDEX IDX_51CE6E86296CD8AE (team_id), INDEX IDX_51CE6E86FB08EDF6 (trainer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero_school_mastery (id INT AUTO_INCREMENT NOT NULL, school VARCHAR(10) NOT NULL, mastery_tier INT DEFAULT 1 NOT NULL, hero_id INT NOT NULL, INDEX IDX_B1F467CF45B0BCD (hero_id), UNIQUE INDEX UNIQ_HERO_SCHOOL (hero_id, school), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero_spell (id INT AUTO_INCREMENT NOT NULL, is_equipped TINYINT DEFAULT 0 NOT NULL, slot_number INT DEFAULT NULL, hero_id INT NOT NULL, spell_id INT NOT NULL, INDEX IDX_4F00C24A45B0BCD (hero_id), INDEX IDX_4F00C24A479EC90D (spell_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hero_training_history (id INT AUTO_INCREMENT NOT NULL, training_type VARCHAR(15) NOT NULL, target_attribute VARCHAR(20) DEFAULT NULL, stat_gain INT DEFAULT NULL, completed_at DATETIME NOT NULL, hero_id INT NOT NULL, trainer_id INT DEFAULT NULL, INDEX IDX_84352B6545B0BCD (hero_id), INDEX IDX_84352B65FB08EDF6 (trainer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item (id INT AUTO_INCREMENT NOT NULL, equipped_slot VARCHAR(15) DEFAULT NULL, name VARCHAR(100) NOT NULL, slot_type VARCHAR(15) NOT NULL, category VARCHAR(20) NOT NULL, rarity VARCHAR(15) NOT NULL, durability INT DEFAULT 100 NOT NULL, bonuses JSON NOT NULL, special_effects JSON NOT NULL, status VARCHAR(15) DEFAULT \'available\' NOT NULL, owner_team_id INT NOT NULL, equipped_hero_id INT DEFAULT NULL, INDEX IDX_1F1B251EA51A5E71 (owner_team_id), INDEX IDX_1F1B251EEB6EE5C (equipped_hero_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE kingdom (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, language VARCHAR(5) NOT NULL, timezone VARCHAR(50) NOT NULL, game_speed NUMERIC(3, 2) DEFAULT \'1.00\' NOT NULL, marketplace_tax_rate NUMERIC(4, 2) DEFAULT \'10.00\' NOT NULL, season_length INT DEFAULT 28 NOT NULL, league_tiers_config JSON NOT NULL, level_cap INT DEFAULT 100 NOT NULL, xp_modifier NUMERIC(3, 2) DEFAULT \'1.00\' NOT NULL, royal_treasury_gold INT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE kingdom_tick_log (id INT AUTO_INCREMENT NOT NULL, tick_type VARCHAR(30) NOT NULL, scheduled_at DATETIME NOT NULL, status VARCHAR(15) NOT NULL, error_message LONGTEXT DEFAULT NULL, executed_at DATETIME NOT NULL, kingdom_id INT NOT NULL, INDEX IDX_5B878E756976FEC0 (kingdom_id), UNIQUE INDEX uniq_kingdom_tick_type_scheduled (kingdom_id, tick_type, scheduled_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE league_fixture (id INT AUTO_INCREMENT NOT NULL, scheduled_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, league_group_id INT NOT NULL, home_team_id INT NOT NULL, away_team_id INT NOT NULL, battle_id INT DEFAULT NULL, home_formation_id INT DEFAULT NULL, away_formation_id INT DEFAULT NULL, INDEX IDX_BC4C599FD9EF31F4 (league_group_id), INDEX IDX_BC4C599F9C4C13F6 (home_team_id), INDEX IDX_BC4C599F45185D02 (away_team_id), INDEX IDX_BC4C599FC9732719 (battle_id), INDEX IDX_BC4C599F5AD0D93E (home_formation_id), INDEX IDX_BC4C599FA7E0F897 (away_formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE league_group (id INT AUTO_INCREMENT NOT NULL, group_name VARCHAR(50) NOT NULL, tier_id INT NOT NULL, INDEX IDX_1A2DABF3A354F9DC (tier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE league_season (id INT AUTO_INCREMENT NOT NULL, season_number INT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, status VARCHAR(20) NOT NULL, kingdom_id INT NOT NULL, INDEX IDX_3F2923DF6976FEC0 (kingdom_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE league_standing (id INT AUTO_INCREMENT NOT NULL, played INT DEFAULT 0 NOT NULL, wins INT DEFAULT 0 NOT NULL, draws INT DEFAULT 0 NOT NULL, losses INT DEFAULT 0 NOT NULL, points INT DEFAULT 0 NOT NULL, goal_difference INT DEFAULT 0 NOT NULL, league_group_id INT NOT NULL, team_id INT NOT NULL, INDEX IDX_4621626BD9EF31F4 (league_group_id), INDEX IDX_4621626B296CD8AE (team_id), UNIQUE INDEX UNIQ_GROUP_TEAM (league_group_id, team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE league_tier (id INT AUTO_INCREMENT NOT NULL, tier_name VARCHAR(50) NOT NULL, promotion_slots INT NOT NULL, relegation_slots INT NOT NULL, rewards JSON NOT NULL, season_id INT NOT NULL, INDEX IDX_CE41378B4EC001D1 (season_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marketplace_bid (id INT AUTO_INCREMENT NOT NULL, bid_amount INT NOT NULL, bid_time DATETIME NOT NULL, listing_id INT NOT NULL, bidder_team_id INT NOT NULL, INDEX IDX_863185AAD4619D1A (listing_id), INDEX IDX_863185AA2FAA92E5 (bidder_team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marketplace_listing (id INT AUTO_INCREMENT NOT NULL, listing_type VARCHAR(20) NOT NULL, price_gold INT NOT NULL, buyout_price_gold INT DEFAULT NULL, listing_mode VARCHAR(20) NOT NULL, expires_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, kingdom_id INT NOT NULL, seller_team_id INT NOT NULL, hero_id INT DEFAULT NULL, item_id INT DEFAULT NULL, INDEX IDX_2FE69BC86976FEC0 (kingdom_id), INDEX IDX_2FE69BC8F26261BF (seller_team_id), INDEX IDX_2FE69BC845B0BCD (hero_id), INDEX IDX_2FE69BC8126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marketplace_transaction (id INT AUTO_INCREMENT NOT NULL, amount INT NOT NULL, fee_amount INT NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, buyer_team_id INT NOT NULL, seller_team_id INT NOT NULL, listing_id INT NOT NULL, INDEX IDX_4874149489C071D8 (buyer_team_id), INDEX IDX_48741494F26261BF (seller_team_id), INDEX IDX_48741494D4619D1A (listing_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE news_article (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, content LONGTEXT NOT NULL, published_at DATETIME NOT NULL, kingdom_id INT DEFAULT NULL, INDEX IDX_55DE12806976FEC0 (kingdom_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(25) NOT NULL, title VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_BF5476CAA76ED395 (user_id), INDEX IDX_USER_READ (user_id, is_read), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE spell (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, school VARCHAR(10) NOT NULL, tier INT NOT NULL, type VARCHAR(15) NOT NULL, effects JSON NOT NULL, mana_cost INT NOT NULL, cooldown INT NOT NULL, required_mastery_tier INT NOT NULL, learning_cost_gold INT NOT NULL, learning_cost_essence INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, emblem VARCHAR(255) DEFAULT NULL, colors JSON DEFAULT NULL, morale INT DEFAULT 50 NOT NULL, reputation INT DEFAULT 0 NOT NULL, chemistry INT DEFAULT 0 NOT NULL, fan_base INT DEFAULT 350 NOT NULL, gold INT DEFAULT 0 NOT NULL, essence_common INT DEFAULT 0 NOT NULL, essence_uncommon INT DEFAULT 0 NOT NULL, essence_rare INT DEFAULT 0 NOT NULL, essence_epic INT DEFAULT 0 NOT NULL, essence_legendary INT DEFAULT 0 NOT NULL, essence_mythic INT DEFAULT 0 NOT NULL, is_npc TINYINT DEFAULT 0 NOT NULL, last_summon_at DATETIME DEFAULT NULL, summons_this_cycle INT DEFAULT 0 NOT NULL, unpaid_debt INT DEFAULT 0 NOT NULL, crisis_weeks INT DEFAULT 0 NOT NULL, last_recovery_action_at DATETIME DEFAULT NULL, user_id INT DEFAULT NULL, kingdom_id INT NOT NULL, UNIQUE INDEX UNIQ_C4E0A61FA76ED395 (user_id), INDEX IDX_C4E0A61F6976FEC0 (kingdom_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_chronicle (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, subject_key VARCHAR(255) NOT NULL, subject_params JSON NOT NULL, data JSON NOT NULL, created_at DATETIME NOT NULL, team_id INT NOT NULL, INDEX IDX_6B94C50B296CD8AE (team_id), INDEX IDX_TEAM_TYPE (team_id, type), INDEX IDX_CREATED_AT (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_financial_record (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, actor VARCHAR(15) NOT NULL, gold_change INT DEFAULT 0 NOT NULL, essence_common_change INT DEFAULT 0 NOT NULL, essence_uncommon_change INT DEFAULT 0 NOT NULL, essence_rare_change INT DEFAULT 0 NOT NULL, essence_epic_change INT DEFAULT 0 NOT NULL, essence_legendary_change INT DEFAULT 0 NOT NULL, essence_mythic_change INT DEFAULT 0 NOT NULL, context JSON NOT NULL, created_at DATETIME NOT NULL, team_id INT NOT NULL, INDEX IDX_870AC103296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_summon_history (id INT AUTO_INCREMENT NOT NULL, race_selected VARCHAR(10) NOT NULL, gold_cost INT NOT NULL, summoned_at DATETIME NOT NULL, team_id INT NOT NULL, hero_id INT DEFAULT NULL, INDEX IDX_C8331C74296CD8AE (team_id), INDEX IDX_C8331C7445B0BCD (hero_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE auth_user ADD CONSTRAINT FK_A3B536FD6976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE auth_user_settings ADD CONSTRAINT FK_25B98D5A76ED395 FOREIGN KEY (user_id) REFERENCES auth_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE auth_verification_token ADD CONSTRAINT FK_7DBA01A6A76ED395 FOREIGN KEY (user_id) REFERENCES auth_user (id)');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295D6976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295DEA3FA723 FOREIGN KEY (team_a_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295DF88A08CD FOREIGN KEY (team_b_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295D6C01B618 FOREIGN KEY (formation_a_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295D7EB419F6 FOREIGN KEY (formation_b_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE community_forum_post ADD CONSTRAINT FK_A8A0BA46E2904019 FOREIGN KEY (thread_id) REFERENCES community_forum_thread (id)');
        $this->addSql('ALTER TABLE community_forum_post ADD CONSTRAINT FK_A8A0BA46E2544CD6 FOREIGN KEY (author_user_id) REFERENCES auth_user (id)');
        $this->addSql('ALTER TABLE community_forum_post ADD CONSTRAINT FK_A8A0BA466C5647ED FOREIGN KEY (author_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE community_forum_thread ADD CONSTRAINT FK_769EC7CD6976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE community_forum_thread ADD CONSTRAINT FK_769EC7CDE2544CD6 FOREIGN KEY (author_user_id) REFERENCES auth_user (id)');
        $this->addSql('ALTER TABLE community_forum_thread ADD CONSTRAINT FK_769EC7CD6C5647ED FOREIGN KEY (author_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE community_message ADD CONSTRAINT FK_9278592C2A98155E FOREIGN KEY (sender_user_id) REFERENCES auth_user (id)');
        $this->addSql('ALTER TABLE community_message ADD CONSTRAINT FK_9278592CDA57E237 FOREIGN KEY (receiver_user_id) REFERENCES auth_user (id)');
        $this->addSql('ALTER TABLE community_message ADD CONSTRAINT FK_9278592CA49A1E65 FOREIGN KEY (sender_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE community_message ADD CONSTRAINT FK_9278592C5455E90C FOREIGN KEY (receiver_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF99901049 FOREIGN KEY (source_fixture_id) REFERENCES league_fixture (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE formation_slot ADD CONSTRAINT FK_FBD1C5275200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE formation_slot ADD CONSTRAINT FK_FBD1C52745B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id)');
        $this->addSql('ALTER TABLE graveyard ADD CONSTRAINT FK_99E2CFAA296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE headquarters ADD CONSTRAINT FK_ABF65D25296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE headquarters ADD CONSTRAINT FK_ABF65D255DF6B900 FOREIGN KEY (upgrading_facility_id) REFERENCES headquarters_facility (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE headquarters_facility ADD CONSTRAINT FK_4CA91EE730C35A0 FOREIGN KEY (headquarters_id) REFERENCES headquarters (id)');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E86296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE6E86FB08EDF6 FOREIGN KEY (trainer_id) REFERENCES hero (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE hero_school_mastery ADD CONSTRAINT FK_B1F467CF45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id)');
        $this->addSql('ALTER TABLE hero_spell ADD CONSTRAINT FK_4F00C24A45B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id)');
        $this->addSql('ALTER TABLE hero_spell ADD CONSTRAINT FK_4F00C24A479EC90D FOREIGN KEY (spell_id) REFERENCES spell (id)');
        $this->addSql('ALTER TABLE hero_training_history ADD CONSTRAINT FK_84352B6545B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id)');
        $this->addSql('ALTER TABLE hero_training_history ADD CONSTRAINT FK_84352B65FB08EDF6 FOREIGN KEY (trainer_id) REFERENCES hero (id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251EA51A5E71 FOREIGN KEY (owner_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251EEB6EE5C FOREIGN KEY (equipped_hero_id) REFERENCES hero (id)');
        $this->addSql('ALTER TABLE kingdom_tick_log ADD CONSTRAINT FK_5B878E756976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599FD9EF31F4 FOREIGN KEY (league_group_id) REFERENCES league_group (id)');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599F9C4C13F6 FOREIGN KEY (home_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599F45185D02 FOREIGN KEY (away_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599FC9732719 FOREIGN KEY (battle_id) REFERENCES combat_battle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599F5AD0D93E FOREIGN KEY (home_formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599FA7E0F897 FOREIGN KEY (away_formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE league_group ADD CONSTRAINT FK_1A2DABF3A354F9DC FOREIGN KEY (tier_id) REFERENCES league_tier (id)');
        $this->addSql('ALTER TABLE league_season ADD CONSTRAINT FK_3F2923DF6976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE league_standing ADD CONSTRAINT FK_4621626BD9EF31F4 FOREIGN KEY (league_group_id) REFERENCES league_group (id)');
        $this->addSql('ALTER TABLE league_standing ADD CONSTRAINT FK_4621626B296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE league_tier ADD CONSTRAINT FK_CE41378B4EC001D1 FOREIGN KEY (season_id) REFERENCES league_season (id)');
        $this->addSql('ALTER TABLE marketplace_bid ADD CONSTRAINT FK_863185AAD4619D1A FOREIGN KEY (listing_id) REFERENCES marketplace_listing (id)');
        $this->addSql('ALTER TABLE marketplace_bid ADD CONSTRAINT FK_863185AA2FAA92E5 FOREIGN KEY (bidder_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE marketplace_listing ADD CONSTRAINT FK_2FE69BC86976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE marketplace_listing ADD CONSTRAINT FK_2FE69BC8F26261BF FOREIGN KEY (seller_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE marketplace_listing ADD CONSTRAINT FK_2FE69BC845B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE marketplace_listing ADD CONSTRAINT FK_2FE69BC8126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE marketplace_transaction ADD CONSTRAINT FK_4874149489C071D8 FOREIGN KEY (buyer_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE marketplace_transaction ADD CONSTRAINT FK_48741494F26261BF FOREIGN KEY (seller_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE marketplace_transaction ADD CONSTRAINT FK_48741494D4619D1A FOREIGN KEY (listing_id) REFERENCES marketplace_listing (id)');
        $this->addSql('ALTER TABLE news_article ADD CONSTRAINT FK_55DE12806976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA76ED395 FOREIGN KEY (user_id) REFERENCES auth_user (id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61FA76ED395 FOREIGN KEY (user_id) REFERENCES auth_user (id)');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F6976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE team_chronicle ADD CONSTRAINT FK_6B94C50B296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE team_financial_record ADD CONSTRAINT FK_870AC103296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE team_summon_history ADD CONSTRAINT FK_C8331C74296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE team_summon_history ADD CONSTRAINT FK_C8331C7445B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE auth_user DROP FOREIGN KEY FK_A3B536FD6976FEC0');
        $this->addSql('ALTER TABLE auth_user_settings DROP FOREIGN KEY FK_25B98D5A76ED395');
        $this->addSql('ALTER TABLE auth_verification_token DROP FOREIGN KEY FK_7DBA01A6A76ED395');
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295D6976FEC0');
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295DEA3FA723');
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295DF88A08CD');
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295D6C01B618');
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295D7EB419F6');
        $this->addSql('ALTER TABLE community_forum_post DROP FOREIGN KEY FK_A8A0BA46E2904019');
        $this->addSql('ALTER TABLE community_forum_post DROP FOREIGN KEY FK_A8A0BA46E2544CD6');
        $this->addSql('ALTER TABLE community_forum_post DROP FOREIGN KEY FK_A8A0BA466C5647ED');
        $this->addSql('ALTER TABLE community_forum_thread DROP FOREIGN KEY FK_769EC7CD6976FEC0');
        $this->addSql('ALTER TABLE community_forum_thread DROP FOREIGN KEY FK_769EC7CDE2544CD6');
        $this->addSql('ALTER TABLE community_forum_thread DROP FOREIGN KEY FK_769EC7CD6C5647ED');
        $this->addSql('ALTER TABLE community_message DROP FOREIGN KEY FK_9278592C2A98155E');
        $this->addSql('ALTER TABLE community_message DROP FOREIGN KEY FK_9278592CDA57E237');
        $this->addSql('ALTER TABLE community_message DROP FOREIGN KEY FK_9278592CA49A1E65');
        $this->addSql('ALTER TABLE community_message DROP FOREIGN KEY FK_9278592C5455E90C');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF296CD8AE');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF99901049');
        $this->addSql('ALTER TABLE formation_slot DROP FOREIGN KEY FK_FBD1C5275200282E');
        $this->addSql('ALTER TABLE formation_slot DROP FOREIGN KEY FK_FBD1C52745B0BCD');
        $this->addSql('ALTER TABLE graveyard DROP FOREIGN KEY FK_99E2CFAA296CD8AE');
        $this->addSql('ALTER TABLE headquarters DROP FOREIGN KEY FK_ABF65D25296CD8AE');
        $this->addSql('ALTER TABLE headquarters DROP FOREIGN KEY FK_ABF65D255DF6B900');
        $this->addSql('ALTER TABLE headquarters_facility DROP FOREIGN KEY FK_4CA91EE730C35A0');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E86296CD8AE');
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE6E86FB08EDF6');
        $this->addSql('ALTER TABLE hero_school_mastery DROP FOREIGN KEY FK_B1F467CF45B0BCD');
        $this->addSql('ALTER TABLE hero_spell DROP FOREIGN KEY FK_4F00C24A45B0BCD');
        $this->addSql('ALTER TABLE hero_spell DROP FOREIGN KEY FK_4F00C24A479EC90D');
        $this->addSql('ALTER TABLE hero_training_history DROP FOREIGN KEY FK_84352B6545B0BCD');
        $this->addSql('ALTER TABLE hero_training_history DROP FOREIGN KEY FK_84352B65FB08EDF6');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251EA51A5E71');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251EEB6EE5C');
        $this->addSql('ALTER TABLE kingdom_tick_log DROP FOREIGN KEY FK_5B878E756976FEC0');
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599FD9EF31F4');
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599F9C4C13F6');
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599F45185D02');
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599FC9732719');
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599F5AD0D93E');
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599FA7E0F897');
        $this->addSql('ALTER TABLE league_group DROP FOREIGN KEY FK_1A2DABF3A354F9DC');
        $this->addSql('ALTER TABLE league_season DROP FOREIGN KEY FK_3F2923DF6976FEC0');
        $this->addSql('ALTER TABLE league_standing DROP FOREIGN KEY FK_4621626BD9EF31F4');
        $this->addSql('ALTER TABLE league_standing DROP FOREIGN KEY FK_4621626B296CD8AE');
        $this->addSql('ALTER TABLE league_tier DROP FOREIGN KEY FK_CE41378B4EC001D1');
        $this->addSql('ALTER TABLE marketplace_bid DROP FOREIGN KEY FK_863185AAD4619D1A');
        $this->addSql('ALTER TABLE marketplace_bid DROP FOREIGN KEY FK_863185AA2FAA92E5');
        $this->addSql('ALTER TABLE marketplace_listing DROP FOREIGN KEY FK_2FE69BC86976FEC0');
        $this->addSql('ALTER TABLE marketplace_listing DROP FOREIGN KEY FK_2FE69BC8F26261BF');
        $this->addSql('ALTER TABLE marketplace_listing DROP FOREIGN KEY FK_2FE69BC845B0BCD');
        $this->addSql('ALTER TABLE marketplace_listing DROP FOREIGN KEY FK_2FE69BC8126F525E');
        $this->addSql('ALTER TABLE marketplace_transaction DROP FOREIGN KEY FK_4874149489C071D8');
        $this->addSql('ALTER TABLE marketplace_transaction DROP FOREIGN KEY FK_48741494F26261BF');
        $this->addSql('ALTER TABLE marketplace_transaction DROP FOREIGN KEY FK_48741494D4619D1A');
        $this->addSql('ALTER TABLE news_article DROP FOREIGN KEY FK_55DE12806976FEC0');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA76ED395');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61FA76ED395');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F6976FEC0');
        $this->addSql('ALTER TABLE team_chronicle DROP FOREIGN KEY FK_6B94C50B296CD8AE');
        $this->addSql('ALTER TABLE team_financial_record DROP FOREIGN KEY FK_870AC103296CD8AE');
        $this->addSql('ALTER TABLE team_summon_history DROP FOREIGN KEY FK_C8331C74296CD8AE');
        $this->addSql('ALTER TABLE team_summon_history DROP FOREIGN KEY FK_C8331C7445B0BCD');
        $this->addSql('DROP TABLE auth_user');
        $this->addSql('DROP TABLE auth_user_settings');
        $this->addSql('DROP TABLE auth_verification_token');
        $this->addSql('DROP TABLE combat_battle');
        $this->addSql('DROP TABLE community_forum_post');
        $this->addSql('DROP TABLE community_forum_thread');
        $this->addSql('DROP TABLE community_message');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE formation_slot');
        $this->addSql('DROP TABLE graveyard');
        $this->addSql('DROP TABLE headquarters');
        $this->addSql('DROP TABLE headquarters_facility');
        $this->addSql('DROP TABLE hero');
        $this->addSql('DROP TABLE hero_school_mastery');
        $this->addSql('DROP TABLE hero_spell');
        $this->addSql('DROP TABLE hero_training_history');
        $this->addSql('DROP TABLE item');
        $this->addSql('DROP TABLE kingdom');
        $this->addSql('DROP TABLE kingdom_tick_log');
        $this->addSql('DROP TABLE league_fixture');
        $this->addSql('DROP TABLE league_group');
        $this->addSql('DROP TABLE league_season');
        $this->addSql('DROP TABLE league_standing');
        $this->addSql('DROP TABLE league_tier');
        $this->addSql('DROP TABLE marketplace_bid');
        $this->addSql('DROP TABLE marketplace_listing');
        $this->addSql('DROP TABLE marketplace_transaction');
        $this->addSql('DROP TABLE news_article');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE spell');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_chronicle');
        $this->addSql('DROP TABLE team_financial_record');
        $this->addSql('DROP TABLE team_summon_history');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
