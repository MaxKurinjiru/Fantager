<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add royal treasury balance to kingdom';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kingdom ADD royal_treasury_gold INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kingdom DROP royal_treasury_gold');
    }
}
