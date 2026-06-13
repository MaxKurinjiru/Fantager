<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to remove gold_cost and essence_cost from training_queue.
 */
final class Version20260613114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove gold_cost and essence_cost from training_queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_queue DROP gold_cost, DROP essence_cost');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE training_queue ADD gold_cost INT NOT NULL, ADD essence_cost INT DEFAULT 0 NOT NULL');
    }
}
