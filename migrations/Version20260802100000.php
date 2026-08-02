<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Prérequis de la Ranked Race : le snapshot quotidien devient multi-files
 * (solo + flex) et stocke le détail du rang (tier/division/LP/wins/losses),
 * indispensable pour la progression pondérée et le winrate de période.
 *
 * Les lignes historiques sont backfillées en RANKED_SOLO_5x5 (seule file
 * snapshotée jusqu'ici) ; leur détail de rang reste NULL car wins/losses
 * ne sont pas dérivables du score aplati.
 */
final class Version20260802100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'summoner_elo_daily : file (solo/flex) + détail du rang + contrainte unique (compte, jour, file)';
    }

    public function up(Schema $schema): void
    {
        // Dédoublonnage défensif : aucune contrainte DB n'existait, l'idempotence
        // applicative n'exclut pas un doublon (cron + déclenchement manuel /getDailyElo).
        // Les doublons supprimés ne sont pas restaurables par down().
        $this->addSql(<<<'SQL'
            DELETE t1 FROM summoner_elo_daily t1
            INNER JOIN summoner_elo_daily t2
                ON t1.riot_account_id = t2.riot_account_id
               AND t1.date_score = t2.date_score
               AND t1.id > t2.id
            SQL);

        // Le DEFAULT backfille l'existant en solo (seule file snapshotée jusqu'ici).
        $this->addSql(<<<'SQL'
            ALTER TABLE summoner_elo_daily
                ADD queue_type VARCHAR(20) DEFAULT 'RANKED_SOLO_5x5' NOT NULL,
                ADD tier VARCHAR(15) DEFAULT NULL,
                ADD division VARCHAR(10) DEFAULT NULL,
                ADD league_points INT DEFAULT NULL,
                ADD wins INT DEFAULT NULL,
                ADD losses INT DEFAULT NULL
            SQL);

        // Retire le DEFAULT une fois le backfill fait, pour coller au mapping Doctrine.
        $this->addSql('ALTER TABLE summoner_elo_daily ALTER queue_type DROP DEFAULT');

        $this->addSql('CREATE UNIQUE INDEX uniq_elo_daily_account_day_queue ON summoner_elo_daily (riot_account_id, date_score, queue_type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_elo_daily_account_day_queue ON summoner_elo_daily');
        $this->addSql(<<<'SQL'
            ALTER TABLE summoner_elo_daily
                DROP queue_type,
                DROP tier,
                DROP division,
                DROP league_points,
                DROP wins,
                DROP losses
            SQL);
    }
}
