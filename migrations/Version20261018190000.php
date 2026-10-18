<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember when a weapon style or magic school was last exercised so daily mastery decay can skip a recent week.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero_weapon_mastery ADD last_exercised_at DATETIME DEFAULT NULL, ADD last_gain_xp INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE hero_school_mastery ADD last_exercised_at DATETIME DEFAULT NULL, ADD last_gain_xp INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero_weapon_mastery DROP last_exercised_at, DROP last_gain_xp');
        $this->addSql('ALTER TABLE hero_school_mastery DROP last_exercised_at, DROP last_gain_xp');
    }
}
