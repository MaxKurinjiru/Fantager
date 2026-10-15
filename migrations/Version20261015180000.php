<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create friendly_match table; add friendly_match_id FK to kingdom_tick_log;
 * update the kingdom_tick_log unique constraint to include friendly_match_id.
 */
final class Version20261015180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add friendly_match table and wire it into kingdom_tick_log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE friendly_match (
                id INT AUTO_INCREMENT NOT NULL,
                kingdom_id INT NOT NULL,
                home_team_id INT NOT NULL,
                away_team_id INT NOT NULL,
                scheduled_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                battle_id INT DEFAULT NULL,
                home_formation_id INT DEFAULT NULL,
                away_formation_id INT DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                responded_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX IDX_FM_kingdom (kingdom_id),
                INDEX IDX_FM_home_team (home_team_id),
                INDEX IDX_FM_away_team (away_team_id),
                UNIQUE INDEX UNIQ_FM_battle (battle_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql('ALTER TABLE friendly_match ADD CONSTRAINT FK_FM_kingdom FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE friendly_match ADD CONSTRAINT FK_FM_home_team FOREIGN KEY (home_team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friendly_match ADD CONSTRAINT FK_FM_away_team FOREIGN KEY (away_team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friendly_match ADD CONSTRAINT FK_FM_battle FOREIGN KEY (battle_id) REFERENCES combat_battle (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE friendly_match ADD CONSTRAINT FK_FM_home_formation FOREIGN KEY (home_formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE friendly_match ADD CONSTRAINT FK_FM_away_formation FOREIGN KEY (away_formation_id) REFERENCES formation (id) ON DELETE SET NULL');

        // Add friendly_match_id column to kingdom_tick_log
        $this->addSql('ALTER TABLE kingdom_tick_log ADD friendly_match_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE kingdom_tick_log ADD CONSTRAINT FK_KTL_friendly_match FOREIGN KEY (friendly_match_id) REFERENCES friendly_match (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_KTL_friendly_match ON kingdom_tick_log (friendly_match_id)');

        // Drop old unique constraint and add new one including friendly_match_id
        $this->addSql('DROP INDEX uniq_kingdom_tick_type_scheduled_team_fixture ON kingdom_tick_log');
        $this->addSql('CREATE UNIQUE INDEX uniq_kingdom_tick_type_scheduled_team_fixture ON kingdom_tick_log (kingdom_id, tick_type, scheduled_at, team_id, fixture_id, friendly_match_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_kingdom_tick_type_scheduled_team_fixture ON kingdom_tick_log');
        $this->addSql('ALTER TABLE kingdom_tick_log DROP FOREIGN KEY FK_KTL_friendly_match');
        $this->addSql('DROP INDEX IDX_KTL_friendly_match ON kingdom_tick_log');
        $this->addSql('ALTER TABLE kingdom_tick_log DROP COLUMN friendly_match_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_kingdom_tick_type_scheduled_team_fixture ON kingdom_tick_log (kingdom_id, tick_type, scheduled_at, team_id, fixture_id)');

        $this->addSql('ALTER TABLE friendly_match DROP FOREIGN KEY FK_FM_kingdom');
        $this->addSql('ALTER TABLE friendly_match DROP FOREIGN KEY FK_FM_home_team');
        $this->addSql('ALTER TABLE friendly_match DROP FOREIGN KEY FK_FM_away_team');
        $this->addSql('ALTER TABLE friendly_match DROP FOREIGN KEY FK_FM_battle');
        $this->addSql('ALTER TABLE friendly_match DROP FOREIGN KEY FK_FM_home_formation');
        $this->addSql('ALTER TABLE friendly_match DROP FOREIGN KEY FK_FM_away_formation');
        $this->addSql('DROP TABLE friendly_match');
    }
}
