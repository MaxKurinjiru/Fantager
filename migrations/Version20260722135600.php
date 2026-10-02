<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add requires_magical_weapon column to spell table.
 */
final class Version20260722135600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add requires_magical_weapon column to spell table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE spell ADD requires_magical_weapon TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE spell DROP requires_magical_weapon');
    }
}
