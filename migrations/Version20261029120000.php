<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add season_report table for frozen end-of-season yearbooks.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE season_report (
            id INT AUTO_INCREMENT NOT NULL,
            kingdom_id INT NOT NULL,
            season_id INT NOT NULL,
            season_number INT NOT NULL,
            generated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            median_gold INT DEFAULT NULL,
            median_combatant_age DOUBLE PRECISION DEFAULT NULL,
            combat_death_count INT DEFAULT 0 NOT NULL,
            market_volume INT DEFAULT 0 NOT NULL,
            crisis_count INT DEFAULT 0 NOT NULL,
            mean_kill_score DOUBLE PRECISION DEFAULT NULL,
            payload JSON NOT NULL,
            INDEX IDX_SEASON_REPORT_KINGDOM (kingdom_id),
            UNIQUE INDEX UNIQ_SEASON_REPORT_SEASON (season_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE season_report ADD CONSTRAINT FK_SEASON_REPORT_KINGDOM FOREIGN KEY (kingdom_id) REFERENCES kingdom (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE season_report ADD CONSTRAINT FK_SEASON_REPORT_SEASON FOREIGN KEY (season_id) REFERENCES league_season (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE season_report DROP FOREIGN KEY FK_SEASON_REPORT_KINGDOM');
        $this->addSql('ALTER TABLE season_report DROP FOREIGN KEY FK_SEASON_REPORT_SEASON');
        $this->addSql('DROP TABLE season_report');
    }
}
