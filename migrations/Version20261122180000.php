<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark notes written by players and link conditions to a body part';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE note ADD written_by_player BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT note_content_length CHECK (char_length(content) <= 10000)');
        $this->addSql('ALTER TABLE player_condition ADD body_part VARCHAR(10) DEFAULT NULL');
        $this->addSql("ALTER TABLE player_condition ADD CONSTRAINT player_condition_body_part_valid CHECK (body_part IS NULL OR body_part IN ('head', 'torso', 'left_arm', 'right_arm', 'left_leg', 'right_leg'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_condition DROP CONSTRAINT player_condition_body_part_valid');
        $this->addSql('ALTER TABLE player_condition DROP body_part');
        $this->addSql('ALTER TABLE note DROP CONSTRAINT note_content_length');
        $this->addSql('ALTER TABLE note DROP written_by_player');
    }
}
