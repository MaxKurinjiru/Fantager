<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_fan_base_delta to team for dashboard fan club change display';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD last_fan_base_delta INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP last_fan_base_delta');
    }
}
