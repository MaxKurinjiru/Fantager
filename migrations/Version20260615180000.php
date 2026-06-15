<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop crystals column from team and crystals_change from team_financial_record';
    }

    public function up(Schema $schema): void
    {
        if ($schema->getTable('team')->hasColumn('crystals')) {
            $this->addSql('ALTER TABLE team DROP crystals');
        }
        if ($schema->getTable('team_financial_record')->hasColumn('crystals_change')) {
            $this->addSql('ALTER TABLE team_financial_record DROP crystals_change');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->getTable('team')->hasColumn('crystals')) {
            $this->addSql('ALTER TABLE team ADD crystals INT DEFAULT 0 NOT NULL');
        }
        if (!$schema->getTable('team_financial_record')->hasColumn('crystals_change')) {
            $this->addSql('ALTER TABLE team_financial_record ADD crystals_change INT DEFAULT 0 NOT NULL');
        }
    }
}
