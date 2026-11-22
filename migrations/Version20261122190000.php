<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the condition body part used by the removed STAT figure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_condition DROP CONSTRAINT player_condition_body_part_valid');
        $this->addSql('ALTER TABLE player_condition DROP body_part');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_condition ADD body_part VARCHAR(10) DEFAULT NULL');
        $this->addSql("ALTER TABLE player_condition ADD CONSTRAINT player_condition_body_part_valid CHECK (body_part IS NULL OR body_part IN ('head', 'torso', 'left_arm', 'right_arm', 'left_leg', 'right_leg'))");
    }
}
