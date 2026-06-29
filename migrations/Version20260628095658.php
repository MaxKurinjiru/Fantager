<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260628095658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hero_weapon_mastery (id INT AUTO_INCREMENT NOT NULL, style VARCHAR(30) NOT NULL, mastery_tier INT DEFAULT 1 NOT NULL, xp INT DEFAULT 0 NOT NULL, attunement_progress INT DEFAULT 0 NOT NULL, hero_id INT NOT NULL, INDEX IDX_86EC151345B0BCD (hero_id), UNIQUE INDEX UNIQ_HERO_WEAPON_STYLE (hero_id, style), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE hero_weapon_mastery ADD CONSTRAINT FK_86EC151345B0BCD FOREIGN KEY (hero_id) REFERENCES hero (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hero ADD base_ovr INT DEFAULT 0 NOT NULL, ADD complex_rating INT DEFAULT 0 NOT NULL, ADD trait VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE hero_school_mastery ADD xp INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE item ADD sub_type VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hero_weapon_mastery DROP FOREIGN KEY FK_86EC151345B0BCD');
        $this->addSql('DROP TABLE hero_weapon_mastery');
        $this->addSql('ALTER TABLE hero DROP base_ovr, DROP complex_rating, DROP trait');
        $this->addSql('ALTER TABLE hero_school_mastery DROP xp');
        $this->addSql('ALTER TABLE item DROP sub_type');
    }
}
