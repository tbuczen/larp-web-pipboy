<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let holodisks carry an uploaded image or audio file';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holodisk ADD media_filename VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE holodisk ADD media_type VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE holodisk ADD media_mime_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE holodisk ADD media_original_name VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE holodisk ADD CONSTRAINT holodisk_media_type_valid CHECK (media_type IS NULL OR media_type IN ('image', 'audio'))");
        // Media columns are set and cleared together.
        $this->addSql('ALTER TABLE holodisk ADD CONSTRAINT holodisk_media_complete CHECK ((media_filename IS NULL) = (media_type IS NULL) AND (media_filename IS NULL) = (media_mime_type IS NULL))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holodisk DROP CONSTRAINT holodisk_media_complete');
        $this->addSql('ALTER TABLE holodisk DROP CONSTRAINT holodisk_media_type_valid');
        $this->addSql('ALTER TABLE holodisk DROP media_filename, DROP media_type, DROP media_mime_type, DROP media_original_name');
    }
}
