<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260626144655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_kingdom_tick_type_scheduled ON kingdom_tick_log');
        $this->addSql('ALTER TABLE kingdom_tick_log ADD team_id INT DEFAULT NULL, ADD fixture_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE kingdom_tick_log ADD CONSTRAINT FK_5B878E75296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE kingdom_tick_log ADD CONSTRAINT FK_5B878E75E524616D FOREIGN KEY (fixture_id) REFERENCES league_fixture (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_5B878E75296CD8AE ON kingdom_tick_log (team_id)');
        $this->addSql('CREATE INDEX IDX_5B878E75E524616D ON kingdom_tick_log (fixture_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_kingdom_tick_type_scheduled_team_fixture ON kingdom_tick_log (kingdom_id, tick_type, scheduled_at, team_id, fixture_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kingdom_tick_log DROP FOREIGN KEY FK_5B878E75296CD8AE');
        $this->addSql('ALTER TABLE kingdom_tick_log DROP FOREIGN KEY FK_5B878E75E524616D');
        $this->addSql('DROP INDEX IDX_5B878E75296CD8AE ON kingdom_tick_log');
        $this->addSql('DROP INDEX IDX_5B878E75E524616D ON kingdom_tick_log');
        $this->addSql('DROP INDEX uniq_kingdom_tick_type_scheduled_team_fixture ON kingdom_tick_log');
        $this->addSql('ALTER TABLE kingdom_tick_log DROP team_id, DROP fixture_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_kingdom_tick_type_scheduled ON kingdom_tick_log (kingdom_id, tick_type, scheduled_at)');
    }
}
