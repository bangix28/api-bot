<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026212819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agent de LAN : demandes d\'appairage et jetons d\'agent';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agent_access_token (id INT AUTO_INCREMENT NOT NULL, digest VARCHAR(64) NOT NULL, discord_id VARCHAR(32) NOT NULL, discord_username VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, lan_event_id INT NOT NULL, UNIQUE INDEX UNIQ_C0EE52A3D12A0B2 (digest), INDEX IDX_C0EE52A3B1C72398 (lan_event_id), INDEX idx_agent_token_owner (discord_id, lan_event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE agent_device_authorization (id INT AUTO_INCREMENT NOT NULL, device_code_digest VARCHAR(64) NOT NULL, user_code VARCHAR(8) NOT NULL, expires_at DATETIME NOT NULL, last_polled_at DATETIME DEFAULT NULL, approved_discord_id VARCHAR(32) DEFAULT NULL, approved_discord_username VARCHAR(64) DEFAULT NULL, consumed TINYINT DEFAULT 0 NOT NULL, approved_lan_event_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_DC51024814ACD6 (device_code_digest), INDEX IDX_DC510248FFF76936 (approved_lan_event_id), INDEX idx_agent_device_user_code (user_code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE agent_access_token ADD CONSTRAINT FK_C0EE52A3B1C72398 FOREIGN KEY (lan_event_id) REFERENCES lan_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE agent_device_authorization ADD CONSTRAINT FK_DC510248FFF76936 FOREIGN KEY (approved_lan_event_id) REFERENCES lan_event (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agent_access_token DROP FOREIGN KEY FK_C0EE52A3B1C72398');
        $this->addSql('ALTER TABLE agent_device_authorization DROP FOREIGN KEY FK_DC510248FFF76936');
        $this->addSql('DROP TABLE agent_access_token');
        $this->addSql('DROP TABLE agent_device_authorization');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
