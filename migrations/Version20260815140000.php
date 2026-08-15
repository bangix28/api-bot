<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reprend l'historique quotidien dans le journal horodaté de la course.
 *
 * summoner_elo_snapshot devient la source unique de la Ranked Race. Les points
 * repris sont horodatés à 03:00, heure réelle du cron daily-elo, donc leur
 * instant de capture effectif. Une fenêtre à cheval sur la bascule mélange
 * ainsi des points quotidiens et des points à 30 minutes : ce sont des relevés
 * comme les autres, la logique de segments les traite sans cas particulier.
 *
 * Les lignes historiques sans détail de rang (antérieures à la Ranked Race,
 * tier NULL) sont écartées ici une fois pour toutes — la nouvelle table est
 * NOT NULL, elle n'hérite pas de cette dette.
 *
 * summoner_elo_daily n'est pas modifiée : elle continue d'alimenter la courbe
 * publique /elo-daily. La migration est donc réversible sans perte.
 */
final class Version20260815140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reprend summoner_elo_daily dans summoner_elo_snapshot (bascule de la course)';
    }

    public function up(Schema $schema): void
    {
        // INSERT IGNORE : la table est déjà alimentée par le cron depuis son
        // déploiement. Si un point à 03:00 existe déjà pour un compte et une
        // file, l'unique (compte, file, instant) le protège sans faire échouer
        // la reprise — la migration est donc rejouable.
        $this->addSql(<<<'SQL'
            INSERT IGNORE INTO summoner_elo_snapshot
                (riot_account_id, queue_type, captured_at, tier, division, league_points, wins, losses)
            SELECT riot_account_id, queue_type, TIMESTAMP(date_score, '03:00:00'),
                   tier, division, league_points, wins, losses
            FROM summoner_elo_daily
            WHERE tier IS NOT NULL
              AND division IS NOT NULL
              AND league_points IS NOT NULL
              AND wins IS NOT NULL
              AND losses IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        // Ne supprime que les points repris (03:00 pile), pas ceux écrits par
        // le cron. Un point du cron tombant exactement à 03:00:00 serait perdu :
        // fenêtre de 30 minutes sur 48, et la donnée reste dans summoner_elo_daily.
        $this->addSql("DELETE FROM summoner_elo_snapshot WHERE TIME(captured_at) = '03:00:00'");
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
