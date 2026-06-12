<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to support trainer-centric training.
 */
final class Version20260612204500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configure trainer-centric training fields and relationships';
    }

    public function up(Schema $schema): void
    {
        // Add training focus columns to trainer
        $this->addSql('ALTER TABLE trainer ADD training_type VARCHAR(15) DEFAULT NULL, ADD target_attribute VARCHAR(20) DEFAULT NULL');

        // Add trainer association to hero
        $this->addSql('ALTER TABLE hero ADD trainer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE hero ADD CONSTRAINT FK_51CE07E8150A48F1 FOREIGN KEY (trainer_id) REFERENCES trainer (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_51CE07E8150A48F1 ON hero (trainer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hero DROP FOREIGN KEY FK_51CE07E8150A48F1');
        $this->addSql('DROP INDEX IDX_51CE07E8150A48F1 ON hero');
        $this->addSql('ALTER TABLE hero DROP trainer_id');
        $this->addSql('ALTER TABLE trainer DROP training_type, DROP target_attribute');
    }
}
