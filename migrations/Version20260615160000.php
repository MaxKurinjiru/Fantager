<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove arena_ticket_price from team (fixed global ticket price)';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('team')->hasColumn('arena_ticket_price')) {
            $this->addSql('ALTER TABLE team DROP arena_ticket_price');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->getTable('team')->hasColumn('arena_ticket_price')) {
            $this->addSql('ALTER TABLE team ADD arena_ticket_price INT DEFAULT 5 NOT NULL');
        }
    }
}
