<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add staff_record table and original_hero_id to graveyard_record';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('graveyard_record')->hasColumn('original_hero_id')) {
            $this->addSql('ALTER TABLE graveyard_record ADD original_hero_id INT DEFAULT NULL');
        }

        $this->addSql('CREATE TABLE staff_record (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, race VARCHAR(10) NOT NULL, age INT NOT NULL, cause VARCHAR(20) NOT NULL, training_type VARCHAR(20) DEFAULT NULL, final_stats JSON NOT NULL, trainees_count INT NOT NULL, original_trainer_id INT DEFAULT NULL, date_of_departure DATE NOT NULL, team_id INT NOT NULL, INDEX IDX_STAFF_RECORD_TEAM (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE staff_record ADD CONSTRAINT FK_STAFF_RECORD_TEAM FOREIGN KEY (team_id) REFERENCES team (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE staff_record DROP FOREIGN KEY FK_STAFF_RECORD_TEAM');
        $this->addSql('DROP TABLE staff_record');

        if ($schema->getTable('graveyard_record')->hasColumn('original_hero_id')) {
            $this->addSql('ALTER TABLE graveyard_record DROP original_hero_id');
        }
    }
}
