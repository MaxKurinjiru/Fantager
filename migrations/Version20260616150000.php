<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Simplify hero status enum: drop training/tired/injured, add recovering';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE hero SET status = 'available' WHERE status = 'training'");
        $this->addSql("UPDATE hero SET status = 'recovering' WHERE status IN ('tired', 'injured')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE hero SET status = 'training' WHERE status = 'available' AND trainer_id IS NOT NULL");
        $this->addSql("UPDATE hero SET status = 'tired' WHERE status = 'recovering'");
    }
}
