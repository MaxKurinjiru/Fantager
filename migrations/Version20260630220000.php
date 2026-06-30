<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make seller_team_id and listing_id nullable on marketplace_transaction and add entity_name column';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE marketplace_transaction DROP FOREIGN KEY FK_48741494F26261BF');
        $this->addSql('ALTER TABLE marketplace_transaction DROP FOREIGN KEY FK_48741494D4619D1A');
        $this->addSql('ALTER TABLE marketplace_transaction CHANGE seller_team_id seller_team_id INT DEFAULT NULL, CHANGE listing_id listing_id INT DEFAULT NULL, ADD entity_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE marketplace_transaction ADD CONSTRAINT FK_48741494F26261BF FOREIGN KEY (seller_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE marketplace_transaction ADD CONSTRAINT FK_48741494D4619D1A FOREIGN KEY (listing_id) REFERENCES marketplace_listing (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE marketplace_transaction DROP FOREIGN KEY FK_48741494F26261BF');
        $this->addSql('ALTER TABLE marketplace_transaction DROP FOREIGN KEY FK_48741494D4619D1A');
        $this->addSql('ALTER TABLE marketplace_transaction CHANGE seller_team_id seller_team_id INT NOT NULL, CHANGE listing_id listing_id INT NOT NULL, DROP entity_name');
        $this->addSql('ALTER TABLE marketplace_transaction ADD CONSTRAINT FK_48741494F26261BF FOREIGN KEY (seller_team_id) REFERENCES team (id)');
        $this->addSql('ALTER TABLE marketplace_transaction ADD CONSTRAINT FK_48741494D4619D1A FOREIGN KEY (listing_id) REFERENCES marketplace_listing (id)');
    }
}
