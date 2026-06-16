<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add financial crisis tracking to team, facility operation fields to headquarters, and team reassignment cooldown to auth_user';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('team')->hasColumn('unpaid_debt')) {
            $this->addSql('ALTER TABLE team ADD unpaid_debt INT DEFAULT 0 NOT NULL');
        }
        if (!$schema->getTable('team')->hasColumn('crisis_weeks')) {
            $this->addSql('ALTER TABLE team ADD crisis_weeks INT DEFAULT 0 NOT NULL');
        }
        if (!$schema->getTable('team')->hasColumn('last_recovery_action_at')) {
            $this->addSql('ALTER TABLE team ADD last_recovery_action_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }

        if (!$schema->getTable('headquarters')->hasColumn('facility_operation')) {
            $this->addSql('ALTER TABLE headquarters ADD facility_operation VARCHAR(10) DEFAULT NULL');
        }
        if (!$schema->getTable('headquarters')->hasColumn('facility_downgrade_lock_cycle')) {
            $this->addSql('ALTER TABLE headquarters ADD facility_downgrade_lock_cycle TINYINT(1) DEFAULT 0 NOT NULL');
        }

        if (!$schema->getTable('auth_user')->hasColumn('team_reassignment_available_at')) {
            $this->addSql('ALTER TABLE auth_user ADD team_reassignment_available_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('team')->hasColumn('unpaid_debt')) {
            $this->addSql('ALTER TABLE team DROP unpaid_debt');
        }
        if ($schema->getTable('team')->hasColumn('crisis_weeks')) {
            $this->addSql('ALTER TABLE team DROP crisis_weeks');
        }
        if ($schema->getTable('team')->hasColumn('last_recovery_action_at')) {
            $this->addSql('ALTER TABLE team DROP last_recovery_action_at');
        }

        if ($schema->getTable('headquarters')->hasColumn('facility_operation')) {
            $this->addSql('ALTER TABLE headquarters DROP facility_operation');
        }
        if ($schema->getTable('headquarters')->hasColumn('facility_downgrade_lock_cycle')) {
            $this->addSql('ALTER TABLE headquarters DROP facility_downgrade_lock_cycle');
        }

        if ($schema->getTable('auth_user')->hasColumn('team_reassignment_available_at')) {
            $this->addSql('ALTER TABLE auth_user DROP team_reassignment_available_at');
        }
    }
}
