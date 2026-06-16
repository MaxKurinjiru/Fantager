<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add player activity tracking fields to auth_user for inactive player team release';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('auth_user')->hasColumn('last_activity_at')) {
            $this->addSql('ALTER TABLE auth_user ADD last_activity_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }

        if (!$schema->getTable('auth_user')->hasColumn('inactive_warning_sent_at')) {
            $this->addSql('ALTER TABLE auth_user ADD inactive_warning_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }

        $this->addSql('UPDATE auth_user SET last_activity_at = created_at WHERE last_activity_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('auth_user')->hasColumn('inactive_warning_sent_at')) {
            $this->addSql('ALTER TABLE auth_user DROP inactive_warning_sent_at');
        }

        if ($schema->getTable('auth_user')->hasColumn('last_activity_at')) {
            $this->addSql('ALTER TABLE auth_user DROP last_activity_at');
        }
    }
}
