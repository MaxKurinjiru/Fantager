<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260701114154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE team_daily_snapshot (id INT AUTO_INCREMENT NOT NULL, recorded_at DATE NOT NULL, morale INT NOT NULL, reputation INT NOT NULL, chemistry INT NOT NULL, fan_base INT NOT NULL, team_id INT NOT NULL, INDEX IDX_113F0316296CD8AE (team_id), UNIQUE INDEX uniq_team_date (team_id, recorded_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE team_daily_snapshot ADD CONSTRAINT FK_113F0316296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE team_daily_snapshot DROP FOREIGN KEY FK_113F0316296CD8AE');
        $this->addSql('DROP TABLE team_daily_snapshot');
    }
}
