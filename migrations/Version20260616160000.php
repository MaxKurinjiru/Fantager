<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fixture formation assignments and temporary match-specific formations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation ADD is_temporary TINYINT DEFAULT 0 NOT NULL, ADD source_fixture_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF4DA1E751 FOREIGN KEY (source_fixture_id) REFERENCES league_fixture (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_404021BF4DA1E751 ON formation (source_fixture_id)');

        $this->addSql('ALTER TABLE league_fixture ADD home_formation_id INT DEFAULT NULL, ADD away_formation_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599F8CFF4AE FOREIGN KEY (home_formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE league_fixture ADD CONSTRAINT FK_BC4C599F9F4C4F FOREIGN KEY (away_formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BC4C599F8CFF4AE ON league_fixture (home_formation_id)');
        $this->addSql('CREATE INDEX IDX_BC4C599F9F4C4F ON league_fixture (away_formation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599F8CFF4AE');
        $this->addSql('ALTER TABLE league_fixture DROP FOREIGN KEY FK_BC4C599F9F4C4F');
        $this->addSql('DROP INDEX IDX_BC4C599F8CFF4AE ON league_fixture');
        $this->addSql('DROP INDEX IDX_BC4C599F9F4C4F ON league_fixture');
        $this->addSql('ALTER TABLE league_fixture DROP home_formation_id, DROP away_formation_id');

        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF4DA1E751');
        $this->addSql('DROP INDEX IDX_404021BF4DA1E751 ON formation');
        $this->addSql('ALTER TABLE formation DROP is_temporary, DROP source_fixture_id');
    }
}
