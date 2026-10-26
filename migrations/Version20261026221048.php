<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026221048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agent de LAN : parties reconstituées et relevés reçus';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agent_report (id INT AUTO_INCREMENT NOT NULL, token_digest VARCHAR(64) NOT NULL, session_id VARCHAR(64) NOT NULL, sequence INT NOT NULL, received_at DATETIME NOT NULL, final_payload LONGTEXT DEFAULT NULL, game_id VARCHAR(80) NOT NULL, INDEX IDX_9AC820A2E48FD905 (game_id), INDEX idx_agent_report_session (token_digest, session_id), INDEX idx_agent_report_received_at (token_digest, received_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lan_game (game_id VARCHAR(80) NOT NULL, game_mode VARCHAR(32) NOT NULL, players_fingerprint VARCHAR(1024) NOT NULL, estimated_start_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, last_report_at DATETIME NOT NULL, settled_scores LONGTEXT DEFAULT NULL, lan_event_id INT NOT NULL, INDEX IDX_4575F56CB1C72398 (lan_event_id), INDEX idx_lan_game_lookup (lan_event_id, game_mode), INDEX idx_lan_game_status (status), PRIMARY KEY (game_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE agent_report ADD CONSTRAINT FK_9AC820A2E48FD905 FOREIGN KEY (game_id) REFERENCES lan_game (game_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lan_game ADD CONSTRAINT FK_4575F56CB1C72398 FOREIGN KEY (lan_event_id) REFERENCES lan_event (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_report DROP FOREIGN KEY FK_9AC820A2E48FD905');
        $this->addSql('ALTER TABLE lan_game DROP FOREIGN KEY FK_4575F56CB1C72398');
        $this->addSql('DROP TABLE agent_report');
        $this->addSql('DROP TABLE lan_game');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
