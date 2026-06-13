<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add deletedBySender and deletedByReceiver columns to community_message table.
 */
final class Version20260613203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deletedBySender and deletedByReceiver columns to community_message';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_message ADD deleted_by_sender TINYINT(1) DEFAULT 0 NOT NULL, ADD deleted_by_receiver TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE community_forum_thread ADD is_locked TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_message DROP deleted_by_sender, DROP deleted_by_receiver');
        $this->addSql('ALTER TABLE community_forum_thread DROP is_locked');
    }
}
