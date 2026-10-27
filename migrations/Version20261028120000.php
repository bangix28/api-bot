<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Défis de LAN : files acceptées par LAN, et file de chaque partie au lieu de son mode';
    }

    public function up(Schema $schema): void
    {
        // Les LAN existantes gardent ce qu'elles acceptaient : l'ARAM du chaos.
        $this->addSql('ALTER TABLE lan_event ADD accepted_queue_ids JSON DEFAULT NULL');
        $this->addSql("UPDATE lan_event SET accepted_queue_ids = '[2400]'");
        $this->addSql('ALTER TABLE lan_event MODIFY accepted_queue_ids JSON NOT NULL');

        // Seul KIWI était accepté jusqu'ici : toutes les parties enregistrées sont des ARAM du chaos.
        $this->addSql('DROP INDEX idx_lan_game_lookup ON lan_game');
        $this->addSql('ALTER TABLE lan_game ADD queue_id INT DEFAULT 2400 NOT NULL');
        $this->addSql('ALTER TABLE lan_game ALTER queue_id DROP DEFAULT');
        $this->addSql('ALTER TABLE lan_game DROP game_mode');
        $this->addSql('CREATE INDEX idx_lan_game_lookup ON lan_game (lan_event_id, queue_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_lan_game_lookup ON lan_game');
        $this->addSql("ALTER TABLE lan_game ADD game_mode VARCHAR(32) DEFAULT 'KIWI' NOT NULL");
        $this->addSql("UPDATE lan_game SET game_mode = CASE queue_id WHEN 2400 THEN 'KIWI' ELSE 'CLASSIC' END");
        $this->addSql('ALTER TABLE lan_game ALTER game_mode DROP DEFAULT');
        $this->addSql('ALTER TABLE lan_game DROP queue_id');
        $this->addSql('CREATE INDEX idx_lan_game_lookup ON lan_game (lan_event_id, game_mode)');
        $this->addSql('ALTER TABLE lan_event DROP accepted_queue_ids');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
