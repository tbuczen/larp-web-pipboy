<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a unique scannable code to every holodisk';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holodisk ADD code VARCHAR(64) DEFAULT NULL');
        // Existing holodisks get random 16-character codes; organizers can regenerate or replace them.
        $this->addSql("UPDATE holodisk SET code = upper(substr(md5(random()::text || clock_timestamp()::text || id::text), 1, 16))");
        $this->addSql('ALTER TABLE holodisk ALTER code SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_52B675C877153098 ON holodisk (code)');
        $this->addSql("ALTER TABLE holodisk ADD CONSTRAINT holodisk_code_format CHECK (code ~ '^[A-Z0-9]{6,64}$')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE holodisk DROP CONSTRAINT holodisk_code_format');
        $this->addSql('DROP INDEX UNIQ_52B675C877153098');
        $this->addSql('ALTER TABLE holodisk DROP code');
    }
}
