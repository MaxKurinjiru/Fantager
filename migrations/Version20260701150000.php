<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hero_chronicle table and add matches statistics columns to hero and graveyard tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hero_chronicle (
            id INT AUTO_INCREMENT NOT NULL,
            hero_id INT DEFAULT NULL,
            original_hero_id INT DEFAULT NULL,
            team_id INT DEFAULT NULL,
            type VARCHAR(30) NOT NULL,
            subject_key VARCHAR(255) NOT NULL,
            subject_params JSON NOT NULL,
            data JSON NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_HERO_TYPE (hero_id, type),
            INDEX IDX_HERO_ORIGINAL (original_hero_id),
            INDEX IDX_HERO_CREATED_AT (created_at),
            INDEX IDX_HERO_CHRON_TEAM (team_id),
            CONSTRAINT FK_HERO_CHRON_HERO FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE SET NULL,
            CONSTRAINT FK_HERO_CHRON_TEAM FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE SET NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE hero ADD matches_played INT DEFAULT 0 NOT NULL, ADD matches_won INT DEFAULT 0 NOT NULL, ADD combats_fallen INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE graveyard ADD matches_played INT DEFAULT 0 NOT NULL, ADD matches_won INT DEFAULT 0 NOT NULL, ADD combats_fallen INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE graveyard DROP matches_played, DROP matches_won, DROP combats_fallen');
        $this->addSql('ALTER TABLE hero DROP matches_played, DROP matches_won, DROP combats_fallen');
        $this->addSql('DROP TABLE hero_chronicle');
    }
}
