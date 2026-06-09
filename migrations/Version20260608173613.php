<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260608173613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE kingdom_tick_log (id INT AUTO_INCREMENT NOT NULL, tick_type VARCHAR(30) NOT NULL, scheduled_at DATETIME NOT NULL, status VARCHAR(15) NOT NULL, error_message LONGTEXT DEFAULT NULL, executed_at DATETIME NOT NULL, kingdom_id INT NOT NULL, INDEX IDX_5B878E756976FEC0 (kingdom_id), UNIQUE INDEX uniq_kingdom_tick_type_scheduled (kingdom_id, tick_type, scheduled_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE kingdom_tick_log ADD CONSTRAINT FK_5B878E756976FEC0 FOREIGN KEY (kingdom_id) REFERENCES kingdom (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE kingdom_tick_log DROP FOREIGN KEY FK_5B878E756976FEC0');
        $this->addSql('DROP TABLE kingdom_tick_log');
    }
}
