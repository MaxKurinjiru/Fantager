<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add status to item and buyout_price_gold to marketplace_listing.
 */
final class Version20260613200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status to item and buyout_price_gold to marketplace_listing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item ADD status VARCHAR(15) DEFAULT \'available\' NOT NULL');
        $this->addSql('ALTER TABLE marketplace_listing ADD buyout_price_gold INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item DROP status');
        $this->addSql('ALTER TABLE marketplace_listing DROP buyout_price_gold');
    }
}
