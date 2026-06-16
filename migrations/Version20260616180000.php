<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop quest tables (feature deferred; design kept in docs/systems/quest-system.md)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quest_player_progress DROP FOREIGN KEY FK_C6B91FF8296CD8AE');
        $this->addSql('ALTER TABLE quest_player_progress DROP FOREIGN KEY FK_C6B91FF8209E9EF4');
        $this->addSql('ALTER TABLE quest DROP FOREIGN KEY FK_4317F8176976FEC0');
        $this->addSql('DROP TABLE quest_player_progress');
        $this->addSql('DROP TABLE quest');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE quest (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(15) NOT NULL, title VARCHAR(150) NOT NULL, description LONGTEXT NOT NULL, rewards JSON NOT NULL, requirements JSON NOT NULL, expires_at DATETIME DEFAULT NULL, kingdom_id INT NOT NULL, INDEX IDX_4317F8176976FEC0 (kingdom_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE quest_player_progress (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(15) NOT NULL, progress INT DEFAULT 0 NOT NULL, completed_at DATETIME DEFAULT NULL, team_id INT NOT NULL, quest_id INT NOT NULL, INDEX IDX_C6B91FF8296CD8AE (team_id), INDEX IDX_C6B91FF8209E9EF4 (quest_id), UNIQUE INDEX UNIQ_TEAM_QUEST (team_id, quest_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE quest ADD CONSTRAINT FK_4317F8176976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
        $this->addSql('ALTER TABLE quest_player_progress ADD CONSTRAINT FK_C6B91FF8296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE quest_player_progress ADD CONSTRAINT FK_C6B91FF8209E9EF4 FOREIGN KEY (quest_id) REFERENCES quest (id)');
    }
}
