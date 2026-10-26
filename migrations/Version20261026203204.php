<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026203204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Défis de LAN : parcours des joueurs et manches réglées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lan_player_run (id INT AUTO_INCREMENT NOT NULL, puuid VARCHAR(255) NOT NULL, rerolls_left INT NOT NULL, score INT NOT NULL, offer JSON DEFAULT NULL, active_card VARCHAR(32) DEFAULT NULL, locked_game_id VARCHAR(64) DEFAULT NULL, lan_event_id INT NOT NULL, INDEX IDX_400E7F79B1C72398 (lan_event_id), UNIQUE INDEX uniq_lan_player_run (lan_event_id, puuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lan_round_result (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, game_id VARCHAR(64) NOT NULL, card VARCHAR(32) NOT NULL, verdict VARCHAR(16) NOT NULL, points INT NOT NULL, round_bonus TINYINT NOT NULL, settled_at DATETIME NOT NULL, run_id INT NOT NULL, INDEX IDX_4CA98D2C84E3FEC4 (run_id), INDEX idx_lan_round_settled_at (settled_at), UNIQUE INDEX uniq_lan_round_position (run_id, position), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lan_player_run ADD CONSTRAINT FK_400E7F79B1C72398 FOREIGN KEY (lan_event_id) REFERENCES lan_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lan_round_result ADD CONSTRAINT FK_4CA98D2C84E3FEC4 FOREIGN KEY (run_id) REFERENCES lan_player_run (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lan_player_run DROP FOREIGN KEY FK_400E7F79B1C72398');
        $this->addSql('ALTER TABLE lan_round_result DROP FOREIGN KEY FK_4CA98D2C84E3FEC4');
        $this->addSql('DROP TABLE lan_player_run');
        $this->addSql('DROP TABLE lan_round_result');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
