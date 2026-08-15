<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Journal horodaté des rangs, alimenté toutes les 30 minutes par refreshSummoners.
 *
 * Création de table seule : le backfill depuis summoner_elo_daily et la bascule
 * de la lecture de la course arrivent dans une migration ultérieure. Tant que
 * personne ne lit cette table, la remplir est sans risque.
 *
 * summoner_elo_daily n'est pas modifiée : elle continue de servir la courbe
 * publique /elo-daily, dont la pagination désactivée suppose 1 point/jour.
 */
final class Version20260815130524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée summoner_elo_snapshot : points de course horodatés (30 min)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE summoner_elo_snapshot (id INT AUTO_INCREMENT NOT NULL, queue_type VARCHAR(20) NOT NULL, captured_at DATETIME NOT NULL, tier VARCHAR(15) NOT NULL, division VARCHAR(10) NOT NULL, league_points INT NOT NULL, wins INT NOT NULL, losses INT NOT NULL, riot_account_id INT NOT NULL, INDEX IDX_68E9CEE0C6EAB37D (riot_account_id), INDEX idx_elo_snapshot_queue_at (queue_type, captured_at), UNIQUE INDEX uniq_elo_snapshot_account_queue_at (riot_account_id, queue_type, captured_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE summoner_elo_snapshot ADD CONSTRAINT FK_68E9CEE0C6EAB37D FOREIGN KEY (riot_account_id) REFERENCES riot_account (id)');
    }

    public function down(Schema $schema): void
    {
        // Réversible sans perte : aucune donnée existante n'a été déplacée.
        $this->addSql('ALTER TABLE summoner_elo_snapshot DROP FOREIGN KEY FK_68E9CEE0C6EAB37D');
        $this->addSql('DROP TABLE summoner_elo_snapshot');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
