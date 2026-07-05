<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260628111804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise les comptes non classés : tier/rank NULL ou "non classée" -> "UNRANKED", wins NULL -> 0';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE riot_account
            SET summoner_ranked_solo_tier = 'UNRANKED',
                summoner_ranked_solo_rank = 'UNRANKED',
                summoner_ranked_solo_wins = 0
            WHERE summoner_ranked_solo_tier IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE riot_account
            SET summoner_ranked_solo_tier = NULL,
                summoner_ranked_solo_rank = 'non classée',
                summoner_ranked_solo_wins = NULL
            WHERE summoner_ranked_solo_tier = 'UNRANKED'");
    }
}
