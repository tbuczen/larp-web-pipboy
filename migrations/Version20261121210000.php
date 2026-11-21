<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let invitations make the invited account an organizer or superadministrator';
    }

    public function up(Schema $schema): void
    {
        // Existing invitations keep their meaning: they invite players.
        $this->addSql("ALTER TABLE event_invitation ADD role VARCHAR(20) DEFAULT 'player' NOT NULL");
        $this->addSql("ALTER TABLE event_invitation ADD CONSTRAINT event_invitation_role_valid CHECK (role IN ('player', 'organizer', 'super_admin'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_invitation DROP CONSTRAINT event_invitation_role_valid');
        $this->addSql('ALTER TABLE event_invitation DROP role');
    }
}
