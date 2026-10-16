<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A7.1: live Kingdom.level_cap is 20 (was unused default 100).
 */
final class Version20261016140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set kingdom.level_cap default and existing rows to 20 (A7.1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kingdom CHANGE level_cap level_cap INT DEFAULT 20 NOT NULL');
        $this->addSql('UPDATE kingdom SET level_cap = 20');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE kingdom CHANGE level_cap level_cap INT DEFAULT 100 NOT NULL');
    }
}
