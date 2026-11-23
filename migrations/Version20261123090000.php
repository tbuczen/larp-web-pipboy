<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store each account\'s chosen interface language';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD locale VARCHAR(5) DEFAULT NULL');
        $this->addSql("ALTER TABLE app_user ADD CONSTRAINT app_user_locale_valid CHECK (locale IS NULL OR locale IN ('en', 'pl'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP CONSTRAINT app_user_locale_valid');
        $this->addSql('ALTER TABLE app_user DROP locale');
    }
}
