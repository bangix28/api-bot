<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026205648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Défis de LAN : cartes saboteur (malédiction, historique des sabotages)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lan_sabotage (id INT AUTO_INCREMENT NOT NULL, card VARCHAR(32) NOT NULL, saboteur_puuid VARCHAR(255) NOT NULL, target_puuid VARCHAR(255) NOT NULL, occurred_at DATETIME NOT NULL, lan_event_id INT NOT NULL, INDEX IDX_4E7E75FEB1C72398 (lan_event_id), INDEX idx_lan_sabotage_occurred_at (occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE lan_sabotage ADD CONSTRAINT FK_4E7E75FEB1C72398 FOREIGN KEY (lan_event_id) REFERENCES lan_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lan_player_run ADD cursed TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE lan_round_result ADD curse_applied TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lan_sabotage DROP FOREIGN KEY FK_4E7E75FEB1C72398');
        $this->addSql('DROP TABLE lan_sabotage');
        $this->addSql('ALTER TABLE lan_player_run DROP cursed');
        $this->addSql('ALTER TABLE lan_round_result DROP curse_applied');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
