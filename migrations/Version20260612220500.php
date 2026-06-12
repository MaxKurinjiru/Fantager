<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to support headquarters facility upgrade duration.
 */
final class Version20260612220500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add upgrading_facility_id and upgrade_completed_at to headquarters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE headquarters ADD upgrading_facility_id INT DEFAULT NULL, ADD upgrade_completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE headquarters ADD CONSTRAINT FK_HQ_UPGRADING_FACILITY FOREIGN KEY (upgrading_facility_id) REFERENCES headquarters_facility (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_HQ_UPGRADING_FACILITY ON headquarters (upgrading_facility_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE headquarters DROP FOREIGN KEY FK_HQ_UPGRADING_FACILITY');
        $this->addSql('DROP INDEX IDX_HQ_UPGRADING_FACILITY ON headquarters');
        $this->addSql('ALTER TABLE headquarters DROP upgrading_facility_id, DROP upgrade_completed_at');
    }
}
