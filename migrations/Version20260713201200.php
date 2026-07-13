<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to update FK on hero_training_history to set trainer_id to NULL on delete.
 */
final class Version20260713201200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set hero_training_history.trainer_id to NULL on trainer deletion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero_training_history DROP FOREIGN KEY FK_84352B65FB08EDF6');
        $this->addSql('ALTER TABLE hero_training_history ADD CONSTRAINT FK_84352B65FB08EDF6 FOREIGN KEY (trainer_id) REFERENCES hero (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero_training_history DROP FOREIGN KEY FK_84352B65FB08EDF6');
        $this->addSql('ALTER TABLE hero_training_history ADD CONSTRAINT FK_84352B65FB08EDF6 FOREIGN KEY (trainer_id) REFERENCES hero (id)');
    }
}
