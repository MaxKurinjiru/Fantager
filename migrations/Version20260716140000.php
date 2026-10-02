<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add status, current_round, run_state, and scheduled_at to combat_battle for asynchronous waves.
 */
final class Version20260716140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status, current_round, run_state, and scheduled_at to combat_battle for asynchronous waves';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE combat_battle ADD status VARCHAR(15) DEFAULT 'completed' NOT NULL");
        $this->addSql('ALTER TABLE combat_battle ADD current_round INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE combat_battle ADD run_state LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE combat_battle ADD scheduled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE combat_battle DROP status');
        $this->addSql('ALTER TABLE combat_battle DROP current_round');
        $this->addSql('ALTER TABLE combat_battle DROP run_state');
        $this->addSql('ALTER TABLE combat_battle DROP scheduled_at');
    }
}
