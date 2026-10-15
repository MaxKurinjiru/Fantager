<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove unused essence currency columns and spell essence learning cost.
 */
final class Version20261014140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop essence columns from team, team_financial_record, and spell';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP essence_common, DROP essence_uncommon, DROP essence_rare, DROP essence_epic, DROP essence_legendary, DROP essence_mythic');
        $this->addSql('ALTER TABLE team_financial_record DROP essence_common_change, DROP essence_uncommon_change, DROP essence_rare_change, DROP essence_epic_change, DROP essence_legendary_change, DROP essence_mythic_change');
        $this->addSql('ALTER TABLE spell DROP learning_cost_essence');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD essence_common INT DEFAULT 0 NOT NULL, ADD essence_uncommon INT DEFAULT 0 NOT NULL, ADD essence_rare INT DEFAULT 0 NOT NULL, ADD essence_epic INT DEFAULT 0 NOT NULL, ADD essence_legendary INT DEFAULT 0 NOT NULL, ADD essence_mythic INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE team_financial_record ADD essence_common_change INT DEFAULT 0 NOT NULL, ADD essence_uncommon_change INT DEFAULT 0 NOT NULL, ADD essence_rare_change INT DEFAULT 0 NOT NULL, ADD essence_epic_change INT DEFAULT 0 NOT NULL, ADD essence_legendary_change INT DEFAULT 0 NOT NULL, ADD essence_mythic_change INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE spell ADD learning_cost_essence INT NOT NULL');
    }
}
