<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove crafting system tables, kingdom crafting_boost column, and forge facilities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crafting_queue DROP FOREIGN KEY FK_302C8645296CD8AE');
        $this->addSql('ALTER TABLE crafting_queue DROP FOREIGN KEY FK_302C864559D8A214');
        $this->addSql('DROP TABLE crafting_queue');
        $this->addSql('DROP TABLE crafting_recipe');
        $this->addSql('ALTER TABLE kingdom DROP crafting_boost');
        $this->addSql("DELETE FROM headquarters_facility WHERE type = 'forge'");
        $this->addSql("DELETE FROM team_financial_record WHERE type = 'crafting_cost'");
        $this->addSql("DELETE FROM activity_log WHERE type = 'item_crafted'");
    }

    public function down(Schema $schema): void
    {
        // Not reversible — crafting was removed from the codebase.
    }
}
