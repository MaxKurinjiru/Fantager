<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Store notification translation keys + params for read-time i18n (A6.4).
 * Existing rows keep frozen title/body; new rows write keys and leave title/body null.
 */
final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification title_key/body_key/params; make title/body nullable (A6.4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification ADD title_key VARCHAR(255) DEFAULT NULL, ADD body_key VARCHAR(255) DEFAULT NULL, ADD title_params JSON DEFAULT NULL, ADD body_params JSON DEFAULT NULL');
        $this->addSql("UPDATE notification SET title_params = '[]' WHERE title_params IS NULL");
        $this->addSql("UPDATE notification SET body_params = '[]' WHERE body_params IS NULL");
        $this->addSql('ALTER TABLE notification CHANGE title_params title_params JSON NOT NULL, CHANGE body_params body_params JSON NOT NULL, CHANGE title title VARCHAR(200) DEFAULT NULL, CHANGE body body LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE notification SET title = '' WHERE title IS NULL");
        $this->addSql("UPDATE notification SET body = '' WHERE body IS NULL");
        $this->addSql('ALTER TABLE notification CHANGE title title VARCHAR(200) NOT NULL, CHANGE body body LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE notification DROP title_key, DROP body_key, DROP title_params, DROP body_params');
    }
}
