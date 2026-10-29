<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029105600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joueurs : sessions portées par un jeton de rafraîchissement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE player_refresh_token (id INT AUTO_INCREMENT NOT NULL, digest VARCHAR(64) NOT NULL, discord_id VARCHAR(32) NOT NULL, discord_username VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_12AF5D21D12A0B2 (digest), INDEX idx_player_refresh_token_owner (discord_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE player_refresh_token');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
