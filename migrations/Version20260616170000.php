<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set ON DELETE SET NULL for battle/dungeon formation foreign keys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295D6C01B618');
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295D7EB419F6');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295D6C01B618 FOREIGN KEY (formation_a_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295D7EB419F6 FOREIGN KEY (formation_b_id) REFERENCES formation (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE dungeon_run DROP FOREIGN KEY FK_EF129CD95200282E');
        $this->addSql('ALTER TABLE dungeon_run ADD CONSTRAINT FK_EF129CD95200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295D6C01B618');
        $this->addSql('ALTER TABLE combat_battle DROP FOREIGN KEY FK_BC1D295D7EB419F6');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295D6C01B618 FOREIGN KEY (formation_a_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE combat_battle ADD CONSTRAINT FK_BC1D295D7EB419F6 FOREIGN KEY (formation_b_id) REFERENCES formation (id)');

        $this->addSql('ALTER TABLE dungeon_run DROP FOREIGN KEY FK_EF129CD95200282E');
        $this->addSql('ALTER TABLE dungeon_run ADD CONSTRAINT FK_EF129CD95200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
    }
}
