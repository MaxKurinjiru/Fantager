<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fan_base column to team for persistent fan club size';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('team')->hasColumn('fan_base')) {
            $this->addSql('ALTER TABLE team ADD fan_base INT DEFAULT 350 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('team')->hasColumn('fan_base')) {
            $this->addSql('ALTER TABLE team DROP fan_base');
        }
    }
}
