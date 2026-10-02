<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add ticket_price column to team table.
 */
final class Version20260806140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ticket_price column to team table for dynamic arena pricing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD ticket_price INT DEFAULT 5 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP ticket_price');
    }
}
