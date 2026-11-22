<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the motion detector feature and the last shared position per player';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_feature_access DROP CONSTRAINT player_feature_valid');
        $this->addSql("ALTER TABLE player_feature_access ADD CONSTRAINT player_feature_valid CHECK (feature IN ('notes', 'chat', 'map', 'holodisks', 'conditions', 'motion'))");
        $this->addSql('CREATE TABLE player_position (latitude DOUBLE PRECISION NOT NULL, longitude DOUBLE PRECISION NOT NULL, accuracy INT NOT NULL, recorded_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, player_id INT NOT NULL, PRIMARY KEY (player_id))');
        $this->addSql('ALTER TABLE player_position ADD CONSTRAINT FK_PLAYER_POSITION_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE player_position ADD CONSTRAINT player_position_range CHECK (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180 AND accuracy >= 0)');
        $this->addSql('CREATE INDEX player_position_recorded_at ON player_position (recorded_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE player_position');
        $this->addSql("DELETE FROM player_feature_access WHERE feature = 'motion'");
        $this->addSql('ALTER TABLE player_feature_access DROP CONSTRAINT player_feature_valid');
        $this->addSql("ALTER TABLE player_feature_access ADD CONSTRAINT player_feature_valid CHECK (feature IN ('notes', 'chat', 'map', 'holodisks', 'conditions'))");
    }
}
