<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025220735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joueur Discord propriétaire d\'un compte Riot (discord_id, discord_username)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE riot_account ADD discord_id VARCHAR(32) DEFAULT NULL, ADD discord_username VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_riot_account_discord_id ON riot_account (discord_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_riot_account_discord_id ON riot_account');
        $this->addSql('ALTER TABLE riot_account DROP discord_id, DROP discord_username');
    }
}
