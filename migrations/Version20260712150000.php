<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add retry_count to kingdom_tick_log for self-healing queue worker support.
 */
final class Version20260712150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add retry_count to kingdom_tick_log for queue self-healing support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kingdom_tick_log ADD retry_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kingdom_tick_log DROP retry_count');
    }
}
